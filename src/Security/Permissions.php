<?php
/**
 * Permissions and Capabilities handler.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Security
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Permissions
 */
final class Permissions {

	/**
	 * Capability required for importing and viewing reviews.
	 *
	 * @var string
	 */
	public const CAP_MANAGE_REVIEWS = 'manage_woocommerce';

	/**
	 * Capability required for administrative settings, API keys, and licenses.
	 *
	 * @var string
	 */
	public const CAP_MANAGE_SETTINGS = 'manage_options';

	/**
	 * Check if current user can import and view reviews.
	 *
	 * @return bool
	 */
	public static function can_manage_reviews(): bool {
		/**
		 * Filters the capability required to manage and import reviews.
		 *
		 * @param string $capability Default capability.
		 */
		$capability = apply_filters( 'wpcmri_manage_reviews_capability', self::CAP_MANAGE_REVIEWS );
		return current_user_can( $capability ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can manage administrative settings and API credentials.
	 *
	 * @return bool
	 */
	public static function can_manage_settings(): bool {
		/**
		 * Filters the capability required to manage settings and credentials.
		 *
		 * @param string $capability Default capability.
		 */
		$capability = apply_filters( 'wpcmri_manage_settings_capability', self::CAP_MANAGE_SETTINGS );
		return current_user_can( $capability );
	}

	/**
	 * Verify standard WordPress nonce.
	 *
	 * @param string $action Action name.
	 * @param string $query_arg Query parameter or POST key containing nonce.
	 * @return bool
	 */
	public static function verify_nonce( string $action, string $query_arg = '_wpnonce' ): bool {
		$nonce = '';
		if ( isset( $_REQUEST[ $query_arg ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST[ $query_arg ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return ! empty( $nonce ) && (bool) wp_verify_nonce( $nonce, $action );
	}
}
