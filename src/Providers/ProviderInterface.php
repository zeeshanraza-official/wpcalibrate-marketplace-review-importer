<?php
/**
 * External Review Provider Interface.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Providers
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Providers;

use WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewDTO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ProviderInterface
 */
interface ProviderInterface {

	/**
	 * Unique provider slug/id.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable provider name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Check if provider is enabled and configured with required credentials.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Validate credentials against remote provider API.
	 *
	 * @param string $api_key Credential string to validate.
	 * @return array{valid: bool, message: string}
	 */
	public function validate_credentials( string $api_key ): array;

	/**
	 * Validate if source parameter (ASIN or URL) is supported.
	 *
	 * @param string $source Source ASIN or product URL.
	 * @param string $marketplace Target marketplace domain.
	 * @return array{valid: bool, asin: string, marketplace: string, error: string}
	 */
	public function validate_source( string $source, string $marketplace = '' ): array;

	/**
	 * Fetch reviews from the external provider.
	 *
	 * @param string               $asin Amazon ASIN.
	 * @param string               $marketplace Amazon domain (e.g. amazon.com).
	 * @param int                  $page Pagination page number (starts at 1).
	 * @param array<string, mixed> $options Additional provider options.
	 * @return array{
	 *     success: bool,
	 *     reviews: array<ReviewDTO>,
	 *     total_available: int,
	 *     has_more: bool,
	 *     next_page: int,
	 *     error: string
	 * }
	 */
	public function fetch_reviews( string $asin, string $marketplace, int $page = 1, array $options = array() ): array;

	/**
	 * Normalize raw provider review array into internal ReviewDTO.
	 *
	 * @param array<string, mixed> $raw_review Provider item payload.
	 * @param string               $asin Product ASIN.
	 * @param string               $marketplace Marketplace host.
	 * @return ReviewDTO|null
	 */
	public function normalize_review( array $raw_review, string $asin, string $marketplace ): ?ReviewDTO;

	/**
	 * Check if marketplace host is supported.
	 *
	 * @param string $marketplace Marketplace code or host.
	 * @return bool
	 */
	public function supports_marketplace( string $marketplace ): bool;

	/**
	 * Get rate limit and quota information if provided by the external API.
	 *
	 * @return array<string, mixed>
	 */
	public function get_rate_limit_information(): array;
}
