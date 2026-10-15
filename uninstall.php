<?php
/**
 * WPCalibrate Marketplace Review Importer Uninstall Routine.
 *
 * Fired when the plugin is deleted via the WordPress admin interface.
 *
 * @package WPCalibrate\MarketplaceReviewImporter
 */

declare(strict_types=1);

// Prevent direct execution.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check administrator configured retention policy.
$cleanup_mode = get_option( 'wpcmri_uninstall_cleanup', 'keep' );

// If merchant chose to keep data, exit cleanly.
if ( 'delete' !== $cleanup_mode ) {
	return;
}

global $wpdb;

// 1. Delete custom database table wp_wpcmri_imports.
$imports_table = $wpdb->prefix . 'wpcmri_imports';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$imports_table}" );

// 2. Unschedule Action Scheduler actions.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wpcmri_process_import_batch' );
}

// 3. Remove all plugin options.
$options_to_delete = array(
	'wpcmri_version',
	'wpcmri_schema_version',
	'wpcmri_default_status',
	'wpcmri_max_reviews_per_import',
	'wpcmri_batch_size',
	'wpcmri_history_retention_days',
	'wpcmri_temp_retention_hours',
	'wpcmri_store_review_titles',
	'wpcmri_store_source_urls',
	'wpcmri_enable_logging',
	'wpcmri_uninstall_cleanup',
	'wpcmri_default_min_rating',
	'wpcmri_default_max_rating',
	'wpcmri_default_min_length',
	'wpcmri_duplicate_behavior',
	'wpcmri_default_marketplace',
	'wpcmri_provider_serpapi_enabled',
	'wpcmri_provider_serpapi_api_key',
	'wpcmri_license_key',
	'wpcmri_license_status',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// 4. Remove temporary uploads folder and files.
$upload_dir = wp_upload_dir();
$plugin_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'wpcalibrate-reviews';

if ( is_dir( $plugin_dir ) ) {
	$files = glob( $plugin_dir . DIRECTORY_SEPARATOR . '*' );
	if ( $files ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFileSystem.unlink_unlink
				@unlink( $file );
			}
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFileSystem.rmdir_rmdir
	@rmdir( $plugin_dir );
}

// Note: WooCommerce product reviews / comments are preserved because they are merchant-managed store content.
