<?php
/**
 * JSON Import Parser, Schema Validator, and Preview Engine.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Application\Import
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Application\Import;

use WPCalibrate\MarketplaceReviewImporter\Domain\Review\DuplicateChecker;
use WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewDTO;
use WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class JsonImporter
 */
final class JsonImporter {

	/**
	 * Supported schema version.
	 */
	public const SCHEMA_VERSION = '1.0';

	/**
	 * Validate and parse a JSON string or file.
	 *
	 * @param string               $json_string Raw JSON content.
	 * @param int                  $target_product_id Target WooCommerce Product ID.
	 * @param array<string, mixed> $filters Filter criteria.
	 * @param int                  $preview_sample_limit Limit of sample records.
	 * @return array<string, mixed>
	 */
	public static function parse_and_preview(
		string $json_string,
		int $target_product_id,
		array $filters = array(),
		int $preview_sample_limit = 5
	): array {
		if ( empty( trim( $json_string ) ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Empty JSON data provided.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		$data = json_decode( $json_string, true );
		if ( null === $data || ! is_array( $data ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: json error message */
					__( 'Invalid JSON syntax: %s', 'wpcalibrate-marketplace-review-importer' ),
					json_last_error_msg()
				),
			);
		}

		// Support both schema-wrapped format { version: "1.0", reviews: [...] } and bare array of reviews [...].
		$reviews_list = array();
		$default_meta = array();

		if ( isset( $data['reviews'] ) && is_array( $data['reviews'] ) ) {
			$reviews_list = $data['reviews'];
			if ( ! empty( $data['marketplace'] ) ) {
				$default_meta['marketplace'] = $data['marketplace'];
			}
			if ( ! empty( $data['asin'] ) || ! empty( $data['source_product_id'] ) ) {
				$default_meta['source_product_id'] = $data['asin'] ?? $data['source_product_id'];
			}
		} elseif ( array_is_list( $data ) ) {
			$reviews_list = $data;
		} else {
			return array(
				'success' => false,
				'error'   => __( 'JSON schema error: Root must be an array or contain a "reviews" array property.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		$total_found      = count( $reviews_list );
		$valid_reviews    = array();
		$invalid_count    = 0;
		$duplicate_count  = 0;
		$rating_breakdown = array(
			1 => 0,
			2 => 0,
			3 => 0,
			4 => 0,
			5 => 0,
		);
		$dates            = array();
		$samples          = array();

		$max_records = (int) apply_filters( 'wpcmri_max_json_records', 5000 );
		$processed   = 0;

		foreach ( $reviews_list as $item ) {
			if ( ! is_array( $item ) ) {
				++$invalid_count;
				continue;
			}

			++$processed;
			if ( $processed > $max_records ) {
				break;
			}

			$merged = array_merge( $default_meta, $item );
			$dto    = ReviewValidator::validate_and_normalize( $merged, 'json' );

			if ( ! $dto ) {
				++$invalid_count;
				continue;
			}

			if ( ! ReviewValidator::passes_filters( $dto, $filters ) ) {
				continue;
			}

			$dto = $dto->with_content_hash( $target_product_id );

			if ( $target_product_id > 0 ) {
				$dup_id = DuplicateChecker::find_duplicate_comment_id( $target_product_id, $dto );
				if ( $dup_id > 0 ) {
					++$duplicate_count;
				}
			}

			$valid_reviews[] = $dto;
			if ( isset( $rating_breakdown[ $dto->rating ] ) ) {
				++$rating_breakdown[ $dto->rating ];
			}

			if ( ! empty( $dto->original_review_date ) ) {
				$dates[] = $dto->original_review_date;
			}

			if ( count( $samples ) < $preview_sample_limit ) {
				$samples[] = $dto->to_array();
			}
		}

		$min_date = ! empty( $dates ) ? min( $dates ) : '';
		$max_date = ! empty( $dates ) ? max( $dates ) : '';

		return array(
			'success'          => true,
			'total_found'      => $total_found,
			'valid_count'      => count( $valid_reviews ),
			'invalid_count'    => $invalid_count,
			'duplicate_count'  => $duplicate_count,
			'rating_breakdown' => $rating_breakdown,
			'date_range'       => array(
				'from' => $min_date,
				'to'   => $max_date,
			),
			'samples'          => $samples,
			'reviews'          => $valid_reviews,
		);
	}

	/**
	 * Output sample JSON template schema.
	 *
	 * @return void
	 */
	public static function output_template(): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wpcalibrate-reviews-template.json' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$template = array(
			'version'     => self::SCHEMA_VERSION,
			'marketplace' => 'amazon.com',
			'asin'        => 'B08N5WRWNW',
			'reviews'     => array(
				array(
					'rating'                   => 5,
					'review_text'              => 'Fantastic product! High quality components and arrived faster than expected.',
					'review_title'             => 'Exceeded Expectations',
					'reviewer_name'            => 'Jordan Taylor',
					'review_date'              => gmdate( 'Y-m-d' ),
					'source_review_id'         => 'R2X8Y7Z6W5',
					'source_url'               => 'https://www.amazon.com/gp/customer-reviews/R2X8Y7Z6W5',
					'verified_purchase_source' => true,
					'helpful_votes'            => 8,
					'image_urls'               => array(
						'https://example.com/review-photo-1.jpg',
					),
				),
				array(
					'rating'                   => 4,
					'review_text'              => 'Solid purchase, works as described. Instructions could be slightly clearer.',
					'review_title'             => 'Very good product',
					'reviewer_name'            => 'Samira Patel',
					'review_date'              => gmdate( 'Y-m-d', strtotime( '-2 days' ) ),
					'source_review_id'         => 'R9K8J7H6G5',
					'source_url'               => 'https://www.amazon.com/gp/customer-reviews/R9K8J7H6G5',
					'verified_purchase_source' => true,
					'helpful_votes'            => 2,
					'image_urls'               => array(),
				),
			),
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_json_encode( $template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}
}
