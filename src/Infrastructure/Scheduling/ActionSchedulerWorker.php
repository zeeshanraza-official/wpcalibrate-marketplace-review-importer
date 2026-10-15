<?php
/**
 * Action Scheduler Background Job Worker.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Infrastructure\Scheduling
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Infrastructure\Scheduling;

use WPCalibrate\MarketplaceReviewImporter\Application\Import\ImportService;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ActionSchedulerWorker
 */
final class ActionSchedulerWorker {

	/**
	 * Action Scheduler hook name.
	 */
	public const HOOK_PROCESS_BATCH = 'wpcmri_process_import_batch';

	/**
	 * Register hook listeners.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( self::HOOK_PROCESS_BATCH, array( self::class, 'handle_batch' ), 10, 2 );
	}

	/**
	 * Check if Action Scheduler is available on this site.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'as_enqueue_async_action' ) || function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Enqueue an async batch action.
	 *
	 * @param string $import_id Import job ID.
	 * @param int    $offset Current item offset.
	 * @return bool
	 */
	public static function schedule_batch( string $import_id, int $offset = 0 ): bool {
		if ( ! self::is_available() ) {
			return false;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				self::HOOK_PROCESS_BATCH,
				array(
					'import_id' => $import_id,
					'offset'    => $offset,
				),
				'wpcmri'
			);
			return true;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + 1,
				self::HOOK_PROCESS_BATCH,
				array(
					'import_id' => $import_id,
					'offset'    => $offset,
				),
				'wpcmri'
			);
			return true;
		}

		return false;
	}

	/**
	 * Handle execution of an Action Scheduler batch.
	 *
	 * @param string $import_id Import job ID.
	 * @param int    $offset Item offset.
	 * @return void
	 */
	public static function handle_batch( string $import_id, int $offset = 0 ): void {
		Logger::info(
			'Action Scheduler batch worker started',
			array(
				'import_id' => $import_id,
				'offset'    => $offset,
			)
		);

		ImportService::process_batch( $import_id, $offset );
	}

	/**
	 * Unschedule all pending actions on deactivation or cancellation.
	 *
	 * @param string|null $import_id Specific import ID or null for all.
	 * @return void
	 */
	public static function unschedule_actions( ?string $import_id = null ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			if ( null !== $import_id ) {
				as_unschedule_all_actions( self::HOOK_PROCESS_BATCH, array( 'import_id' => $import_id ) );
			} else {
				as_unschedule_all_actions( self::HOOK_PROCESS_BATCH );
			}
		}
	}
}
