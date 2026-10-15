<?php
/**
 * Settings Repository and Defaults Manager.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Admin\Settings
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsRepository
 */
final class SettingsRepository {

	/**
	 * Default settings array.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULTS = array(
		'wpcmri_default_status'             => '1', // 1 = approved, 0 = hold/moderation
		'wpcmri_max_reviews_per_import'     => 100,
		'wpcmri_batch_size'                 => 20,
		'wpcmri_history_retention_days'     => 60,
		'wpcmri_temp_retention_hours'       => 24,
		'wpcmri_store_review_titles'        => 'yes',
		'wpcmri_store_source_urls'          => 'yes',
		'wpcmri_enable_logging'             => 'yes',
		'wpcmri_uninstall_cleanup'          => 'keep', // 'keep' or 'delete'
		'wpcmri_default_min_rating'         => 1,
		'wpcmri_default_max_rating'         => 5,
		'wpcmri_default_min_length'         => 0,
		'wpcmri_duplicate_behavior'         => 'skip', // 'skip' or 'update_meta'
		'wpcmri_default_marketplace'        => 'amazon.com',
		'wpcmri_provider_serpapi_enabled'   => 'no',
		'wpcmri_provider_serpapi_api_key'   => '',
		'wpcmri_license_key'                => '',
		'wpcmri_license_status'             => 'not_configured',
		'wpcmri_enable_frontend_styling'    => 'yes',
		'wpcmri_show_review_summary'        => 'yes',
		'wpcmri_show_marketplace_badge'     => 'yes',
		'wpcmri_show_review_media'          => 'yes',
		'wpcmri_show_helpful_votes'         => 'yes',
	);

	/**
	 * Get a setting value with fallback to default.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $fallback Optional fallback value.
	 * @return mixed
	 */
	public static function get( string $key, mixed $fallback = null ): mixed {
		$default = self::DEFAULTS[ $key ] ?? $fallback;
		return get_option( $key, $default );
	}

	/**
	 * Update a setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Value to store.
	 * @return bool
	 */
	public static function set( string $key, mixed $value ): bool {
		return update_option( $key, $value );
	}

	/**
	 * Delete a setting.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function delete( string $key ): bool {
		return delete_option( $key );
	}

	/**
	 * Initialize default settings if not already set.
	 *
	 * @return void
	 */
	public static function init_defaults(): void {
		foreach ( self::DEFAULTS as $key => $default_val ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $default_val );
			}
		}
	}

	/**
	 * Mask sensitive credentials (e.g. API keys or license keys) for safe display.
	 *
	 * @param string $secret Plain secret string.
	 * @return string Masked string.
	 */
	public static function mask_secret( string $secret ): string {
		$len = strlen( $secret );
		if ( 0 === $len ) {
			return '';
		}

		if ( $len <= 6 ) {
			return str_repeat( '*', $len );
		}

		$start = substr( $secret, 0, 3 );
		$end   = substr( $secret, -3 );
		return $start . str_repeat( '*', max( 4, $len - 6 ) ) . $end;
	}

	/**
	 * Get all managed option keys for cleanup.
	 *
	 * @return array<string>
	 */
	public static function get_all_option_keys(): array {
		return array_keys( self::DEFAULTS );
	}
}
