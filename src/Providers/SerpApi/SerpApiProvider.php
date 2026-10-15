<?php
/**
 * SerpApi Amazon Reviews Provider Adapter.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Providers\SerpApi
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Providers\SerpApi;

use WPCalibrate\MarketplaceReviewImporter\Admin\Settings\SettingsRepository;
use WPCalibrate\MarketplaceReviewImporter\Domain\Provider\AmazonSourceParser;
use WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewDTO;
use WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewValidator;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Logging\Logger;
use WPCalibrate\MarketplaceReviewImporter\Providers\ProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SerpApiProvider
 */
final class SerpApiProvider implements ProviderInterface {

	/**
	 * Provider identifier.
	 */
	public const ID = 'serpapi';

	/**
	 * SerpApi base API endpoint.
	 */
	private const API_ENDPOINT = 'https://serpapi.com/search.json';

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'SerpApi (Amazon Reviews)';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		$enabled = 'yes' === SettingsRepository::get( 'wpcmri_provider_serpapi_enabled', 'no' );
		$key     = (string) SettingsRepository::get( 'wpcmri_provider_serpapi_api_key', '' );
		return $enabled && ! empty( trim( $key ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate_credentials( string $api_key ): array {
		$api_key = trim( $api_key );
		if ( empty( $api_key ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'API key cannot be empty.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		// Validate using account endpoint or test search query with minimal output.
		$url      = add_query_arg(
			array(
				'engine'   => 'amazon_reviews',
				'asin'     => 'B08N5WRWNW',
				'api_key'  => $api_key,
				'page'     => 1,
				'num'      => 1,
			),
			self::API_ENDPOINT
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'httpversion' => '1.1',
				'user-agent'  => 'WPCalibrate Marketplace Review Importer/' . WPCMRI_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'valid'   => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Connection to SerpApi failed: %s', 'wpcalibrate-marketplace-review-importer' ),
					$response->get_error_message()
				),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 401 === $status_code || 403 === $status_code || ( isset( $data['error'] ) && str_contains( strtolower( (string) $data['error'] ), 'invalid api key' ) ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Invalid SerpApi API key. Please check your credentials at SerpApi.com.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		if ( 429 === $status_code || ( isset( $data['error'] ) && str_contains( strtolower( (string) $data['error'] ), 'run out of searches' ) ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'SerpApi monthly search quota or rate limit has been exceeded.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		if ( $status_code >= 400 ) {
			$err = isset( $data['error'] ) ? (string) $data['error'] : sprintf( 'HTTP %d error', $status_code );
			return array(
				'valid'   => false,
				'message' => sprintf(
					/* translators: %s: provider error */
					__( 'SerpApi error: %s', 'wpcalibrate-marketplace-review-importer' ),
					$err
				),
			);
		}

		return array(
			'valid'   => true,
			'message' => __( 'Connection successful! SerpApi credentials are valid.', 'wpcalibrate-marketplace-review-importer' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate_source( string $source, string $marketplace = '' ): array {
		return AmazonSourceParser::parse( $source, $marketplace );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_marketplace( string $marketplace ): bool {
		return AmazonSourceParser::is_marketplace_allowed( $marketplace );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_reviews( string $asin, string $marketplace, int $page = 1, array $options = array() ): array {
		if ( ! $this->is_configured() ) {
			return array(
				'success'         => false,
				'reviews'         => array(),
				'total_available' => 0,
				'has_more'        => false,
				'next_page'       => 1,
				'error'           => __( 'SerpApi provider is not enabled or API key is not configured in settings.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		$api_key = (string) SettingsRepository::get( 'wpcmri_provider_serpapi_api_key', '' );
		$domain  = AmazonSourceParser::normalize_domain( $marketplace );

		if ( ! $this->supports_marketplace( $domain ) ) {
			return array(
				'success'         => false,
				'reviews'         => array(),
				'total_available' => 0,
				'has_more'        => false,
				'next_page'       => 1,
				'error'           => sprintf(
					/* translators: %s: domain */
					__( 'Amazon domain "%s" is not supported.', 'wpcalibrate-marketplace-review-importer' ),
					$domain
				),
			);
		}

		$query_args = array(
			'engine'        => 'amazon_reviews',
			'asin'          => $asin,
			'amazon_domain' => $domain,
			'api_key'       => $api_key,
			'page'          => max( 1, $page ),
		);

		if ( ! empty( $options['sort_by'] ) ) {
			$query_args['sort_by'] = sanitize_key( (string) $options['sort_by'] );
		}

		$url = add_query_arg( $query_args, self::API_ENDPOINT );

		Logger::info(
			'Initiating SerpApi fetch',
			array(
				'asin'        => $asin,
				'domain'      => $domain,
				'page'        => $page,
				'provider_id' => self::ID,
			)
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 25,
				'httpversion' => '1.1',
				'user-agent'  => 'WPCalibrate Marketplace Review Importer/' . WPCMRI_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			Logger::error(
				'SerpApi request failed',
				array(
					'error' => $error_message,
					'asin'  => $asin,
				)
			);

			return array(
				'success'         => false,
				'reviews'         => array(),
				'total_available' => 0,
				'has_more'        => false,
				'next_page'       => $page,
				'error'           => sprintf(
					/* translators: %s: connection error */
					__( 'SerpApi network error: %s', 'wpcalibrate-marketplace-review-importer' ),
					$error_message
				),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 401 === $status_code || 403 === $status_code ) {
			return array(
				'success'         => false,
				'reviews'         => array(),
				'total_available' => 0,
				'has_more'        => false,
				'next_page'       => $page,
				'error'           => __( 'SerpApi authentication error: API key is invalid or inactive.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		if ( 429 === $status_code || ( isset( $data['error'] ) && str_contains( strtolower( (string) $data['error'] ), 'run out of searches' ) ) ) {
			return array(
				'success'         => false,
				'reviews'         => array(),
				'total_available' => 0,
				'has_more'        => false,
				'next_page'       => $page,
				'error'           => __( 'SerpApi monthly quota exceeded or rate limit reached.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		if ( null === $data || ! is_array( $data ) ) {
			return array(
				'success'         => false,
				'reviews'         => array(),
				'total_available' => 0,
				'has_more'        => false,
				'next_page'       => $page,
				'error'           => __( 'Malformed JSON response received from SerpApi.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		if ( isset( $data['error'] ) ) {
			return array(
				'success'         => false,
				'reviews'         => array(),
				'total_available' => 0,
				'has_more'        => false,
				'next_page'       => $page,
				'error'           => sprintf(
					/* translators: %s: provider error */
					__( 'SerpApi error: %s', 'wpcalibrate-marketplace-review-importer' ),
					(string) $data['error']
				),
			);
		}

		$raw_reviews = $data['reviews'] ?? array();
		if ( ! is_array( $raw_reviews ) ) {
			$raw_reviews = array();
		}

		$normalized_reviews = array();
		foreach ( $raw_reviews as $raw_review ) {
			if ( ! is_array( $raw_review ) ) {
				continue;
			}
			$dto = $this->normalize_review( $raw_review, $asin, $domain );
			if ( $dto ) {
				$normalized_reviews[] = $dto;
			}
		}

		$total_results = (int) ( $data['search_information']['total_results'] ?? count( $normalized_reviews ) );
		$has_next_page = ! empty( $data['serpapi_pagination']['next'] );

		return array(
			'success'         => true,
			'reviews'         => $normalized_reviews,
			'total_available' => $total_results,
			'has_more'        => $has_next_page,
			'next_page'       => $has_next_page ? $page + 1 : $page,
			'error'           => '',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function normalize_review( array $raw_review, string $asin, string $marketplace ): ?ReviewDTO {
		// SerpApi Amazon Reviews format fields:
		// id, title, body / snippet, rating, date, verified_purchase, user { name }, helpful_votes, images
		$review_id = (string) ( $raw_review['id'] ?? '' );
		$title     = (string) ( $raw_review['title'] ?? '' );
		$body      = (string) ( $raw_review['body'] ?? ( $raw_review['snippet'] ?? '' ) );
		$rating    = $raw_review['rating'] ?? 5;
		$date_str  = (string) ( $raw_review['date'] ?? '' );
		$verified  = (bool) ( $raw_review['verified_purchase'] ?? false );
		$source_url = (string) ( $raw_review['link'] ?? '' );

		$user_name = '';
		if ( isset( $raw_review['user']['name'] ) ) {
			$user_name = (string) $raw_review['user']['name'];
		}

		$helpful_votes = 0;
		if ( isset( $raw_review['helpful_votes'] ) ) {
			if ( is_numeric( $raw_review['helpful_votes'] ) ) {
				$helpful_votes = (int) $raw_review['helpful_votes'];
			} elseif ( is_string( $raw_review['helpful_votes'] ) ) {
				if ( preg_match( '/(\d+)/', $raw_review['helpful_votes'], $matches ) ) {
					$helpful_votes = (int) $matches[1];
				}
			}
		}

		$images = array();
		if ( isset( $raw_review['images'] ) && is_array( $raw_review['images'] ) ) {
			foreach ( $raw_review['images'] as $img ) {
				if ( is_string( $img ) && ! empty( $img ) ) {
					$images[] = $img;
				} elseif ( is_array( $img ) && ! empty( $img['link'] ) ) {
					$images[] = (string) $img['link'];
				}
			}
		}

		$payload = array(
			'provider'                 => self::ID,
			'source_review_id'         => $review_id,
			'source_product_id'        => $asin,
			'asin'                     => $asin,
			'marketplace'              => $marketplace,
			'source_url'               => $source_url,
			'reviewer_name'            => $user_name,
			'review_title'             => $title,
			'review_text'              => $body,
			'rating'                   => $rating,
			'review_date'              => $date_str,
			'verified_purchase_source' => $verified,
			'helpful_votes'            => $helpful_votes,
			'image_urls'               => $images,
		);

		return ReviewValidator::validate_and_normalize( $payload, self::ID );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_rate_limit_information(): array {
		return array(
			'provider'     => self::ID,
			'quota_policy' => 'External provider terms and limits apply based on administrator account at SerpApi.com.',
		);
	}
}
