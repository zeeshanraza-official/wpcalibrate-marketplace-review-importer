<?php
/**
 * Duplicate Review Checker and Fingerprint System.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Domain\Review
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Domain\Review;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DuplicateChecker
 */
final class DuplicateChecker {

	/**
	 * Meta key constants.
	 */
	public const META_PROVIDER         = '_wpcmri_provider';
	public const META_MARKETPLACE      = '_wpcmri_marketplace';
	public const META_ASIN             = '_wpcmri_asin';
	public const META_SOURCE_REVIEW_ID = '_wpcmri_source_review_id';
	public const META_SOURCE_URL       = '_wpcmri_source_url';
	public const META_CONTENT_HASH     = '_wpcmri_content_hash';

	/**
	 * In-memory cache of existing fingerprints per product ID for batch efficiency.
	 *
	 * @var array<int, array<string, int>>
	 */
	private static array $product_fingerprint_cache = array();

	/**
	 * Find existing duplicate comment ID for a review on a target product.
	 *
	 * @param int       $target_product_id WooCommerce product ID.
	 * @param ReviewDTO $review Review to check.
	 * @return int Comment ID if duplicate found, 0 otherwise.
	 */
	public static function find_duplicate_comment_id( int $target_product_id, ReviewDTO $review ): int {
		global $wpdb;

		// 1. Priority 1: Provider + Marketplace + Source Review ID.
		if ( ! empty( $review->provider ) && ! empty( $review->marketplace ) && ! empty( $review->source_review_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$found_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT c.comment_ID 
					FROM {$wpdb->comments} c
					INNER JOIN {$wpdb->commentmeta} m1 ON c.comment_ID = m1.comment_id AND m1.meta_key = %s AND m1.meta_value = %s
					INNER JOIN {$wpdb->commentmeta} m2 ON c.comment_ID = m2.comment_id AND m2.meta_key = %s AND m2.meta_value = %s
					INNER JOIN {$wpdb->commentmeta} m3 ON c.comment_ID = m3.comment_id AND m3.meta_key = %s AND m3.meta_value = %s
					WHERE c.comment_post_ID = %d
					LIMIT 1",
					self::META_PROVIDER,
					$review->provider,
					self::META_MARKETPLACE,
					$review->marketplace,
					self::META_SOURCE_REVIEW_ID,
					$review->source_review_id,
					$target_product_id
				)
			);
			if ( $found_id > 0 ) {
				return $found_id;
			}
		}

		// 2. Priority 2: Provider + ASIN + Source Review ID.
		if ( ! empty( $review->provider ) && ! empty( $review->source_product_id ) && ! empty( $review->source_review_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$found_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT c.comment_ID 
					FROM {$wpdb->comments} c
					INNER JOIN {$wpdb->commentmeta} m1 ON c.comment_ID = m1.comment_id AND m1.meta_key = %s AND m1.meta_value = %s
					INNER JOIN {$wpdb->commentmeta} m2 ON c.comment_ID = m2.comment_id AND m2.meta_key = %s AND m2.meta_value = %s
					INNER JOIN {$wpdb->commentmeta} m3 ON c.comment_ID = m3.comment_id AND m3.meta_key = %s AND m3.meta_value = %s
					WHERE c.comment_post_ID = %d
					LIMIT 1",
					self::META_PROVIDER,
					$review->provider,
					self::META_ASIN,
					$review->source_product_id,
					self::META_SOURCE_REVIEW_ID,
					$review->source_review_id,
					$target_product_id
				)
			);
			if ( $found_id > 0 ) {
				return $found_id;
			}
		}

		// 3. Priority 3: Stable Source URL.
		if ( ! empty( $review->source_url ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$found_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT c.comment_ID 
					FROM {$wpdb->comments} c
					INNER JOIN {$wpdb->commentmeta} m1 ON c.comment_ID = m1.comment_id AND m1.meta_key = %s AND m1.meta_value = %s
					WHERE c.comment_post_ID = %d
					LIMIT 1",
					self::META_SOURCE_URL,
					$review->source_url,
					$target_product_id
				)
			);
			if ( $found_id > 0 ) {
				return $found_id;
			}
		}

		// 4. Priority 4: Content Hash.
		$hash = ! empty( $review->content_hash )
			? $review->content_hash
			: ReviewDTO::generate_content_hash(
				$target_product_id,
				$review->reviewer_display_name,
				$review->original_review_date,
				$review->rating,
				$review->review_body
			);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$found_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT c.comment_ID 
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} m1 ON c.comment_ID = m1.comment_id AND m1.meta_key = %s AND m1.meta_value = %s
				WHERE c.comment_post_ID = %d
				LIMIT 1",
				self::META_CONTENT_HASH,
				$hash,
				$target_product_id
			)
		);

		return $found_id > 0 ? $found_id : 0;
	}

	/**
	 * Clear in-memory product fingerprint cache.
	 *
	 * @param int|null $product_id Optional specific product ID to clear.
	 * @return void
	 */
	public static function clear_cache( ?int $product_id = null ): void {
		if ( null !== $product_id ) {
			unset( self::$product_fingerprint_cache[ $product_id ] );
		} else {
			self::$product_fingerprint_cache = array();
		}
	}
}
