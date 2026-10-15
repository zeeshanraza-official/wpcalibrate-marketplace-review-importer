<?php
/**
 * Import Orchestration Service.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Application\Import
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Application\Import;

use WPCalibrate\MarketplaceReviewImporter\Admin\Settings\SettingsRepository;
use WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewDTO;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Database\ImportRepository;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Files\UploadHandler;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Logging\Logger;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Scheduling\ActionSchedulerWorker;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\WooCommerce\ReviewRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ImportService
 */
final class ImportService {

	/**
	 * Get temporary file path for import job review items.
	 *
	 * @param string $import_id Unique import job ID.
	 * @return string File path.
	 */
	public static function get_job_items_filepath( string $import_id ): string {
		return UploadHandler::get_upload_dir() . 'job_items_' . sanitize_file_name( $import_id ) . '.json';
	}

	/**
	 * Start a review import job.
	 *
	 * @param int                  $product_id Target WooCommerce Product ID.
	 * @param string               $source_type Source type ('csv', 'json', 'serpapi').
	 * @param array<ReviewDTO>     $reviews Array of validated ReviewDTO objects.
	 * @param array<string, mixed> $meta Additional metadata (provider_id, marketplace, asin, filter_criteria).
	 * @return array{success: bool, import_id: string, is_async: bool, message: string}
	 */
	public static function start_import( int $product_id, string $source_type, array $reviews, array $meta = array() ): array {
		if ( empty( $reviews ) ) {
			return array(
				'success'   => false,
				'import_id' => '',
				'is_async'  => false,
				'message'   => __( 'No valid reviews provided for import.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		$post = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type ) {
			return array(
				'success'   => false,
				'import_id' => '',
				'is_async'  => false,
				'message'   => __( 'Selected destination WooCommerce product was not found.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		$import_id   = 'imp_' . wp_generate_password( 16, false, false );
		$total_count = count( $reviews );

		// Save review items to temporary JSON file.
		$items_data = array_map( static fn( ReviewDTO $dto ) => $dto->to_array(), $reviews );
		$file_path  = self::get_job_items_filepath( $import_id );
		file_put_contents( $file_path, wp_json_encode( $items_data ) );

		$batch_size = (int) SettingsRepository::get( 'wpcmri_batch_size', 20 );
		if ( $batch_size <= 0 ) {
			$batch_size = 20;
		}

		$is_async = ( $total_count > $batch_size ) && ActionSchedulerWorker::is_available();
		$status   = $is_async ? 'queued' : 'processing';

		// Create database job record.
		ImportRepository::create(
			array(
				'import_id'        => $import_id,
				'product_id'       => $product_id,
				'source_type'      => $source_type,
				'provider_id'      => $meta['provider_id'] ?? ( 'serpapi' === $source_type ? 'serpapi' : '' ),
				'marketplace'      => $meta['marketplace'] ?? '',
				'asin'             => $meta['asin'] ?? '',
				'total_discovered' => $total_count,
				'status'           => $status,
				'filter_criteria'  => $meta['filter_criteria'] ?? array(),
			)
		);

		Logger::info(
			'Started import job',
			array(
				'import_id'   => $import_id,
				'product_id'  => $product_id,
				'source_type' => $source_type,
				'total_count' => $total_count,
				'is_async'    => $is_async,
			)
		);

		if ( $is_async ) {
			ActionSchedulerWorker::schedule_batch( $import_id, 0 );
			return array(
				'success'   => true,
				'import_id' => $import_id,
				'is_async'  => true,
				'message'   => sprintf(
					/* translators: %d: total reviews count */
					__( 'Import job queued for %d reviews. Processing in background.', 'wpcalibrate-marketplace-review-importer' ),
					$total_count
				),
			);
		}

		// Run synchronous execution for small imports.
		self::process_batch( $import_id, 0 );

		return array(
			'success'   => true,
			'import_id' => $import_id,
			'is_async'  => false,
			'message'   => __( 'Import completed successfully.', 'wpcalibrate-marketplace-review-importer' ),
		);
	}

	/**
	 * Process a batch of reviews for an import job.
	 *
	 * @param string $import_id Unique import ID.
	 * @param int    $offset Item offset.
	 * @return void
	 */
	public static function process_batch( string $import_id, int $offset = 0 ): void {
		$job = ImportRepository::get_by_import_id( $import_id );
		if ( ! $job ) {
			Logger::error( 'Import job not found for batch execution', array( 'import_id' => $import_id ) );
			return;
		}

		if ( 'cancelled' === $job['status'] ) {
			self::cleanup_job_items( $import_id );
			return;
		}

		// Prevent overlapping executions using a transient lock.
		$lock_key = 'wpcmri_lock_' . $import_id;
		if ( get_transient( $lock_key ) ) {
			// Reschedule if currently locked.
			ActionSchedulerWorker::schedule_batch( $import_id, $offset );
			return;
		}
		set_transient( $lock_key, '1', 60 );

		// Update status to processing if still queued.
		if ( 'queued' === $job['status'] ) {
			ImportRepository::update_by_import_id( $import_id, array( 'status' => 'processing' ) );
		}

		$file_path = self::get_job_items_filepath( $import_id );
		if ( ! file_exists( $file_path ) ) {
			delete_transient( $lock_key );
			ImportRepository::update_by_import_id(
				$import_id,
				array(
					'status'        => 'failed',
					'error_summary' => __( 'Import review dataset file was missing or expired.', 'wpcalibrate-marketplace-review-importer' ),
				)
			);
			return;
		}

		$raw_content = file_get_contents( $file_path );
		$all_items   = json_decode( (string) $raw_content, true );
		if ( ! is_array( $all_items ) ) {
			delete_transient( $lock_key );
			ImportRepository::update_by_import_id(
				$import_id,
				array(
					'status'        => 'failed',
					'error_summary' => __( 'Corrupt review items file during import processing.', 'wpcalibrate-marketplace-review-importer' ),
				)
			);
			return;
		}

		$batch_size = (int) SettingsRepository::get( 'wpcmri_batch_size', 20 );
		if ( $batch_size <= 0 ) {
			$batch_size = 20;
		}

		$batch_items = array_slice( $all_items, $offset, $batch_size );
		$product_id  = (int) $job['product_id'];

		$imported   = 0;
		$duplicate  = 0;
		$skipped    = 0;
		$failed     = 0;

		foreach ( $batch_items as $item_array ) {
			$dto = new ReviewDTO(
				provider: (string) ( $item_array['provider'] ?? '' ),
				source_review_id: (string) ( $item_array['source_review_id'] ?? '' ),
				source_product_id: (string) ( $item_array['source_product_id'] ?? '' ),
				marketplace: (string) ( $item_array['marketplace'] ?? '' ),
				source_url: (string) ( $item_array['source_url'] ?? '' ),
				reviewer_display_name: (string) ( $item_array['reviewer_display_name'] ?? '' ),
				review_title: (string) ( $item_array['review_title'] ?? '' ),
				review_body: (string) ( $item_array['review_body'] ?? '' ),
				rating: (int) ( $item_array['rating'] ?? 5 ),
				original_review_date: (string) ( $item_array['original_review_date'] ?? '' ),
				imported_at: (string) ( $item_array['imported_at'] ?? gmdate( 'Y-m-d H:i:s' ) ),
				source_verified_purchase: (bool) ( $item_array['source_verified_purchase'] ?? false ),
				helpful_votes: (int) ( $item_array['helpful_votes'] ?? 0 ),
				media_urls: (array) ( $item_array['media_urls'] ?? array() ),
				source_language: (string) ( $item_array['source_language'] ?? '' ),
				content_hash: (string) ( $item_array['content_hash'] ?? '' )
			);

			$result = ReviewRepository::persist_review( $product_id, $dto, $import_id );

			switch ( $result['status'] ) {
				case 'created':
				case 'updated':
					++$imported;
					break;
				case 'duplicate_skipped':
					++$duplicate;
					break;
				case 'skipped':
					++$skipped;
					break;
				default:
					++$failed;
					break;
			}
		}

		ImportRepository::increment_counts( $import_id, $imported, $duplicate, $skipped, $failed );
		delete_transient( $lock_key );

		$next_offset = $offset + count( $batch_items );
		$total_count = count( $all_items );

		if ( $next_offset < $total_count ) {
			// Schedule next batch.
			if ( ActionSchedulerWorker::is_available() ) {
				ActionSchedulerWorker::schedule_batch( $import_id, $next_offset );
			} else {
				// Fallback recursion if synchronous continuation.
				self::process_batch( $import_id, $next_offset );
			}
		} else {
			// Job completed!
			$fresh_job = ImportRepository::get_by_import_id( $import_id );
			$final_status = ( $fresh_job && (int) $fresh_job['failed_count'] > 0 ) ? 'completed_with_errors' : 'completed';

			ImportRepository::update_by_import_id(
				$import_id,
				array( 'status' => $final_status )
			);

			self::cleanup_job_items( $import_id );

			/**
			 * Hook fired when an import completes.
			 *
			 * @param string $import_id Unique import ID.
			 * @param string $final_status 'completed' or 'completed_with_errors'.
			 */
			do_action( 'wpcmri_import_completed', $import_id, $final_status );

			Logger::info(
				'Import job completed',
				array(
					'import_id' => $import_id,
					'status'    => $final_status,
				)
			);
		}
	}

	/**
	 * Cancel an in-progress import job.
	 *
	 * @param string $import_id Unique import ID.
	 * @return bool
	 */
	public static function cancel_import( string $import_id ): bool {
		$job = ImportRepository::get_by_import_id( $import_id );
		if ( ! $job ) {
			return false;
		}

		ImportRepository::update_by_import_id(
			$import_id,
			array( 'status' => 'cancelled' )
		);

		ActionSchedulerWorker::unschedule_actions( $import_id );
		self::cleanup_job_items( $import_id );

		Logger::info( 'Import job cancelled by administrator', array( 'import_id' => $import_id ) );
		return true;
	}

	/**
	 * Remove temporary job items JSON file.
	 *
	 * @param string $import_id Unique import ID.
	 * @return void
	 */
	public static function cleanup_job_items( string $import_id ): void {
		$file_path = self::get_job_items_filepath( $import_id );
		if ( file_exists( $file_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFileSystem.unlink_unlink
			unlink( $file_path );
		}
	}
}
