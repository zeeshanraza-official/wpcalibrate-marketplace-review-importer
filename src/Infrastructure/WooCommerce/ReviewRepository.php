<?php
/**
 * WooCommerce Review Persistence Repository.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Infrastructure\WooCommerce
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Infrastructure\WooCommerce;

use WPCalibrate\MarketplaceReviewImporter\Admin\Settings\SettingsRepository;
use WPCalibrate\MarketplaceReviewImporter\Domain\Review\DuplicateChecker;
use WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewDTO;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReviewRepository
 */
final class ReviewRepository {

	/**
	 * Insert or update a review on a WooCommerce product.
	 *
	 * @param int       $target_product_id Target WooCommerce product ID.
	 * @param ReviewDTO $review Review value object.
	 * @param string    $import_id Import batch session identifier.
	 * @return array{status: string, comment_id: int} Status can be 'created', 'duplicate_skipped', 'updated', or 'failed'.
	 */
	public static function persist_review( int $target_product_id, ReviewDTO $review, string $import_id ): array {
		// 1. Check if product exists and supports comments/reviews.
		$post = get_post( $target_product_id );
		if ( ! $post || 'product' !== $post->post_type ) {
			return array(
				'status'     => 'failed',
				'comment_id' => 0,
			);
		}

		// Ensure review has content hash calculated.
		$review = $review->with_content_hash( $target_product_id );

		// 2. Check duplicate.
		$existing_comment_id = DuplicateChecker::find_duplicate_comment_id( $target_product_id, $review );
		$duplicate_behavior  = (string) SettingsRepository::get( 'wpcmri_duplicate_behavior', 'skip' );

		if ( $existing_comment_id > 0 ) {
			if ( 'update_meta' === $duplicate_behavior ) {
				// Only update metadata if this is an imported review, NEVER modify purely native store reviews.
				$is_imported = (bool) get_comment_meta( $existing_comment_id, '_wpcmri_source', true );
				if ( $is_imported ) {
					self::save_review_meta( $existing_comment_id, $review, $import_id, false );
					return array(
						'status'     => 'updated',
						'comment_id' => $existing_comment_id,
					);
				}
			}

			return array(
				'status'     => 'duplicate_skipped',
				'comment_id' => $existing_comment_id,
			);
		}

		// 3. Prepare comment data.
		$status = (string) SettingsRepository::get( 'wpcmri_default_status', '1' );

		$comment_date_gmt = ! empty( $review->original_review_date )
			? get_gmt_from_date( $review->original_review_date )
			: gmdate( 'Y-m-d H:i:s' );
		$comment_date     = ! empty( $review->original_review_date )
			? $review->original_review_date
			: current_time( 'mysql' );

		$comment_data = array(
			'comment_post_ID'      => $target_product_id,
			'comment_author'       => wp_strip_all_tags( $review->reviewer_display_name ),
			'comment_author_email' => '', // Never synthesize fake email addresses.
			'comment_author_url'   => '',
			'comment_content'      => wp_kses_post( $review->review_body ),
			'comment_type'         => 'review',
			'comment_parent'       => 0,
			'comment_author_IP'    => '127.0.0.1',
			'comment_agent'        => 'WPCalibrate Marketplace Review Importer',
			'comment_date'         => $comment_date,
			'comment_date_gmt'     => $comment_date_gmt,
			'comment_approved'     => $status,
		);

		/**
		 * Filter comment data before insertion.
		 *
		 * @param array<string, mixed> $comment_data Comment data to insert.
		 * @param ReviewDTO            $review Source review DTO.
		 * @param int                  $target_product_id Target product ID.
		 */
		$comment_data = apply_filters( 'wpcmri_before_insert_comment_data', $comment_data, $review, $target_product_id );

		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id || is_wp_error( $comment_id ) ) {
			Logger::error(
				'Failed to insert review comment',
				array(
					'product_id' => $target_product_id,
					'import_id'  => $import_id,
				)
			);
			return array(
				'status'     => 'failed',
				'comment_id' => 0,
			);
		}

		// 4. Save metadata.
		self::save_review_meta( (int) $comment_id, $review, $import_id, true );

		// 5. Sync WooCommerce product rating transients.
		self::sync_product_ratings( $target_product_id );

		return array(
			'status'     => 'created',
			'comment_id' => (int) $comment_id,
		);
	}

	/**
	 * Save namespaced and WooCommerce review metadata.
	 *
	 * @param int       $comment_id WordPress Comment ID.
	 * @param ReviewDTO $review Review DTO.
	 * @param string    $import_id Import job ID.
	 * @param bool      $is_new Whether this is a new comment.
	 * @return void
	 */
	private static function save_review_meta( int $comment_id, ReviewDTO $review, string $import_id, bool $is_new ): void {
		if ( $is_new ) {
			// WooCommerce standard review rating (1-5).
			update_comment_meta( $comment_id, 'rating', (string) $review->rating );

			// Explicitly ensure local verified-owner is NOT set.
			update_comment_meta( $comment_id, 'verified', 0 );
		}

		// Namespaced metadata.
		update_comment_meta( $comment_id, '_wpcmri_source', 'marketplace' );
		update_comment_meta( $comment_id, '_wpcmri_provider', $review->provider );
		update_comment_meta( $comment_id, '_wpcmri_marketplace', $review->marketplace );
		update_comment_meta( $comment_id, '_wpcmri_asin', $review->source_product_id );
		update_comment_meta( $comment_id, '_wpcmri_source_product_id', $review->source_product_id );
		update_comment_meta( $comment_id, '_wpcmri_source_review_id', $review->source_review_id );

		$store_urls = 'yes' === SettingsRepository::get( 'wpcmri_store_source_urls', 'yes' );
		if ( $store_urls && ! empty( $review->source_url ) ) {
			update_comment_meta( $comment_id, '_wpcmri_source_url', $review->source_url );
		}

		$store_titles = 'yes' === SettingsRepository::get( 'wpcmri_store_review_titles', 'yes' );
		if ( $store_titles && ! empty( $review->review_title ) ) {
			update_comment_meta( $comment_id, '_wpcmri_review_title', $review->review_title );
		}

		update_comment_meta( $comment_id, '_wpcmri_original_date', $review->original_review_date );
		update_comment_meta( $comment_id, '_wpcmri_source_verified_purchase', $review->source_verified_purchase ? '1' : '0' );
		update_comment_meta( $comment_id, '_wpcmri_helpful_votes', (string) $review->helpful_votes );
		update_comment_meta( $comment_id, '_wpcmri_content_hash', $review->content_hash );
		update_comment_meta( $comment_id, '_wpcmri_import_id', $import_id );

		if ( ! empty( $review->media_urls ) ) {
			update_comment_meta( $comment_id, '_wpcmri_media_urls', $review->media_urls );
		}

		if ( ! empty( $review->source_language ) ) {
			update_comment_meta( $comment_id, '_wpcmri_source_language', $review->source_language );
		}
	}

	/**
	 * Sync WooCommerce product rating counts and cache.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return void
	 */
	public static function sync_product_ratings( int $product_id ): void {
		if ( class_exists( '\WC_Comments' ) && method_exists( '\WC_Comments', 'clear_transients' ) ) {
			\WC_Comments::clear_transients( $product_id );
		}

		// Also trigger WooCommerce standard product lookup update if available.
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product && method_exists( $product, 'get_id' ) ) {
				clean_post_cache( $product_id );
			}
		}
	}
}
