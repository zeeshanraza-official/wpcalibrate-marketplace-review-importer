<?php
/**
 * Amazon URL and ASIN Parser with Marketplace Allowlist.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Domain\Provider
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AmazonSourceParser
 */
final class AmazonSourceParser {

	/**
	 * Default allowlisted Amazon marketplace domains.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_MARKETPLACES = array(
		'amazon.com'    => 'United States (amazon.com)',
		'amazon.co.uk' => 'United Kingdom (amazon.co.uk)',
		'amazon.de'     => 'Germany (amazon.de)',
		'amazon.fr'     => 'France (amazon.fr)',
		'amazon.it'     => 'Italy (amazon.it)',
		'amazon.es'     => 'Spain (amazon.es)',
		'amazon.ca'     => 'Canada (amazon.ca)',
		'amazon.com.au' => 'Australia (amazon.com.au)',
		'amazon.co.jp'  => 'Japan (amazon.co.jp)',
		'amazon.in'     => 'India (amazon.in)',
		'amazon.com.mx' => 'Mexico (amazon.com.mx)',
		'amazon.com.br' => 'Brazil (amazon.com.br)',
		'amazon.nl'     => 'Netherlands (amazon.nl)',
		'amazon.se'     => 'Sweden (amazon.se)',
		'amazon.pl'     => 'Poland (amazon.pl)',
		'amazon.sg'     => 'Singapore (amazon.sg)',
		'amazon.ae'     => 'United Arab Emirates (amazon.ae)',
		'amazon.sa'     => 'Saudi Arabia (amazon.sa)',
	);

	/**
	 * Get all supported Amazon marketplace domains.
	 *
	 * @return array<string, string> Domain => Label.
	 */
	public static function get_supported_marketplaces(): array {
		/**
		 * Filters the list of supported Amazon marketplace domains.
		 *
		 * @param array<string, string> $marketplaces Allowlisted domains.
		 */
		return (array) apply_filters( 'wpcmri_supported_marketplaces', self::DEFAULT_MARKETPLACES );
	}

	/**
	 * Check if a marketplace domain is in the allowlist.
	 *
	 * @param string $domain Domain string.
	 * @return bool
	 */
	public static function is_marketplace_allowed( string $domain ): bool {
		$domain      = self::normalize_domain( $domain );
		$allowlisted = self::get_supported_marketplaces();
		return array_key_exists( $domain, $allowlisted );
	}

	/**
	 * Normalize domain string (e.g. remove http://, www., trailing slash).
	 *
	 * @param string $domain Input domain.
	 * @return string
	 */
	public static function normalize_domain( string $domain ): string {
		$domain = trim( strtolower( $domain ) );
		$domain = preg_replace( '#^https?://#i', '', $domain );
		$domain = preg_replace( '#^www\.#i', '', (string) $domain );
		$parts  = explode( '/', (string) $domain );
		return trim( $parts[0] );
	}

	/**
	 * Validate if a string is a valid ASIN format.
	 *
	 * @param string $asin ASIN candidate.
	 * @return bool
	 */
	public static function is_valid_asin( string $asin ): bool {
		$asin = strtoupper( trim( $asin ) );
		return 1 === preg_match( '/^[B0-9][A-Z0-9]{9}$/', $asin );
	}

	/**
	 * Parse input string which could be a raw ASIN or an Amazon product URL.
	 *
	 * @param string $input ASIN or URL.
	 * @param string $default_marketplace Fallback marketplace domain if not inferred.
	 * @return array{valid: bool, asin: string, marketplace: string, error: string}
	 */
	public static function parse( string $input, string $default_marketplace = 'amazon.com' ): array {
		$input = trim( $input );
		if ( empty( $input ) ) {
			return array(
				'valid'       => false,
				'asin'        => '',
				'marketplace' => '',
				'error'       => __( 'Please enter an Amazon product URL or ASIN.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		// 1. Direct ASIN input.
		if ( self::is_valid_asin( $input ) ) {
			$marketplace = self::normalize_domain( $default_marketplace );
			if ( ! self::is_marketplace_allowed( $marketplace ) ) {
				$marketplace = 'amazon.com';
			}

			return array(
				'valid'       => true,
				'asin'        => strtoupper( $input ),
				'marketplace' => $marketplace,
				'error'       => '',
			);
		}

		// 2. URL parsing.
		$parsed_url = wp_parse_url( $input );
		if ( ! is_array( $parsed_url ) || empty( $parsed_url['host'] ) ) {
			return array(
				'valid'       => false,
				'asin'        => '',
				'marketplace' => '',
				'error'       => __( 'Invalid ASIN format or malformed URL.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		$host = self::normalize_domain( $parsed_url['host'] );
		if ( ! self::is_marketplace_allowed( $host ) ) {
			return array(
				'valid'       => false,
				'asin'        => '',
				'marketplace' => $host,
				'error'       => sprintf(
					/* translators: %s: unsupported domain */
					__( 'Marketplace domain "%s" is not in the allowlist of supported Amazon domains.', 'wpcalibrate-marketplace-review-importer' ),
					$host
				),
			);
		}

		// Extract ASIN from path using standard Amazon URL patterns:
		// /dp/B08N5WRWNW
		// /gp/product/B08N5WRWNW
		// /product-reviews/B08N5WRWNW
		// /exec/obidos/ASIN/B08N5WRWNW
		$path = $parsed_url['path'] ?? '';
		$asin = '';

		if ( preg_match( '#/(?:dp|gp/product|product-reviews|ASIN)/([B0-9][A-Z0-9]{9})(?:[/?]|$)#i', $path, $matches ) ) {
			$asin = strtoupper( $matches[1] );
		}

		if ( empty( $asin ) || ! self::is_valid_asin( $asin ) ) {
			return array(
				'valid'       => false,
				'asin'        => '',
				'marketplace' => $host,
				'error'       => __( 'Could not locate a valid 10-character Amazon ASIN in the provided URL.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		return array(
			'valid'       => true,
			'asin'        => $asin,
			'marketplace' => $host,
			'error'       => '',
		);
	}
}
