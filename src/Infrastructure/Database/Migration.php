<?php
/**
 * Database Migration Handler.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Infrastructure\Database
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Infrastructure\Database;

use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migration
 */
final class Migration {

	/**
	 * Run migrations if needed.
	 *
	 * @return void
	 */
	public static function check_and_migrate(): void {
		$installed_version = get_option( Schema::OPTION_SCHEMA_VERSION, '0.0.0' );

		if ( version_compare( (string) $installed_version, WPCMRI_SCHEMA_VERSION, '<' ) ) {
			Logger::info(
				'Running database migration',
				array(
					'from_version' => $installed_version,
					'to_version'   => WPCMRI_SCHEMA_VERSION,
				)
			);

			$success = Schema::install_or_upgrade();

			if ( $success ) {
				Logger::info(
					'Database migration completed successfully',
					array(
						'installed_version' => WPCMRI_SCHEMA_VERSION,
					)
				);
			} else {
				Logger::error( 'Database migration failed' );
			}
		}
	}
}
