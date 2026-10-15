<?php
/**
 * Admin Notices Manager.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Admin\Notices
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Admin\Notices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminNotices
 */
final class AdminNotices {

	/**
	 * Flash notices transient key.
	 */
	private const TRANSIENT_KEY = 'wpcmri_flash_notices_';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( self::class, 'display_notices' ) );
	}

	/**
	 * Add a flash notice to show on next page load.
	 *
	 * @param string $message Notice message HTML.
	 * @param string $type Notice type ('success', 'warning', 'error', 'info').
	 * @param bool   $is_dismissible Whether notice is dismissible.
	 * @return void
	 */
	public static function add_flash( string $message, string $type = 'info', bool $is_dismissible = true ): void {
		$user_id = get_current_user_id();
		$key     = self::TRANSIENT_KEY . $user_id;
		$notices = get_transient( $key );

		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$notices[] = array(
			'message'     => $message,
			'type'        => $type,
			'dismissible' => $is_dismissible,
		);

		set_transient( $key, $notices, 60 );
	}

	/**
	 * Render and clear all pending flash notices.
	 *
	 * @return void
	 */
	public static function display_notices(): void {
		$user_id = get_current_user_id();
		$key     = self::TRANSIENT_KEY . $user_id;
		$notices = get_transient( $key );

		if ( ! is_array( $notices ) || empty( $notices ) ) {
			return;
		}

		delete_transient( $key );

		foreach ( $notices as $notice ) {
			$type        = esc_attr( $notice['type'] ?? 'info' );
			$dismissible = ! empty( $notice['dismissible'] ) ? 'is-dismissible' : '';
			$message     = wp_kses_post( $notice['message'] ?? '' );

			printf(
				'<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
				$type, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$dismissible, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$message // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}
	}

	/**
	 * Display missing WooCommerce requirement notice.
	 *
	 * @return void
	 */
	public static function display_woocommerce_missing_notice(): void {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>' . esc_html__( 'WPCalibrate Marketplace Review Importer:', 'wpcalibrate-marketplace-review-importer' ) . '</strong> ';
		echo esc_html__( 'WooCommerce is not active. This plugin requires WooCommerce to be installed and activated to import and manage product reviews.', 'wpcalibrate-marketplace-review-importer' );
		echo '</p></div>';
	}
}
