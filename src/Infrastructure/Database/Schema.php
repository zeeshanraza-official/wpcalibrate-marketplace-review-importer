<?php
/**
 * Database Schema Manager.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Infrastructure\Database
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Infrastructure\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema
 */
final class Schema {

	/**
	 * Schema option key.
	 *
	 * @var string
	 */
	public const OPTION_SCHEMA_VERSION = 'wpcmri_schema_version';

	/**
	 * Get the imports table name.
	 *
	 * @return string
	 */
	public static function get_imports_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpcmri_imports';
	}

	/**
	 * Get table creation SQL statements for dbDelta.
	 *
	 * @return string
	 */
	public static function get_schema_sql(): string {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$imports_table   = self::get_imports_table();

		return "CREATE TABLE {$imports_table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  import_id varchar(64) NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  source_type varchar(32) NOT NULL,
  provider_id varchar(64) NOT NULL DEFAULT '',
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  marketplace varchar(32) NOT NULL DEFAULT '',
  asin varchar(32) NOT NULL DEFAULT '',
  total_discovered int(11) NOT NULL DEFAULT 0,
  imported_count int(11) NOT NULL DEFAULT 0,
  duplicate_count int(11) NOT NULL DEFAULT 0,
  skipped_count int(11) NOT NULL DEFAULT 0,
  failed_count int(11) NOT NULL DEFAULT 0,
  status varchar(32) NOT NULL DEFAULT 'pending',
  filter_criteria longtext NULL,
  error_summary text NULL,
  job_token varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY import_id (import_id),
  KEY product_id (product_id),
  KEY status (status)
) {$charset_collate};";
	}

	/**
	 * Execute schema creation and migrations via dbDelta.
	 *
	 * @return bool True if migration executed successfully.
	 */
	public static function install_or_upgrade(): bool {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$sql = self::get_schema_sql();
		dbDelta( $sql );

		update_option( self::OPTION_SCHEMA_VERSION, WPCMRI_SCHEMA_VERSION );
		return true;
	}

	/**
	 * Drop custom tables (used during complete uninstall when configured).
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;
		$table = self::get_imports_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::OPTION_SCHEMA_VERSION );
	}
}
