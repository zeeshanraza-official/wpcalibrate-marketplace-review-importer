<?php
/**
 * Plugin Name: WPCalibrate Marketplace Review Importer
 * Plugin URI: https://wpcalibrate.com/
 * Description: Import legitimate third-party Amazon product reviews via CSV, JSON, or external providers like SerpApi directly into WooCommerce native product reviews.
 * Version: 1.0.0
 * Author: WPCalibrate
 * Author URI: https://wpcalibrate.com
 * Text Domain: wpcalibrate-marketplace-review-importer
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * WC requires at least: 8.0
 * WC tested up to: 11.2.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/zeeshanraza-official/wpcalibrate-marketplace-review-importer
 *
 * @package WPCalibrate\MarketplaceReviewImporter
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants.
define( 'WPCMRI_VERSION', '1.0.0' );
define( 'WPCMRI_SCHEMA_VERSION', '1.0.0' );
define( 'WPCMRI_FILE', __FILE__ );
define( 'WPCMRI_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCMRI_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCMRI_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPCMRI_MIN_PHP_VERSION', '8.2.0' );
define( 'WPCMRI_MIN_WP_VERSION', '6.5' );
define( 'WPCMRI_MIN_WC_VERSION', '8.0' );
define( 'WPCMRI_TEXT_DOMAIN', 'wpcalibrate-marketplace-review-importer' );

// Require Autoloader.
require_once WPCMRI_PATH . 'src/Autoloader.php';
\WPCalibrate\MarketplaceReviewImporter\Autoloader::register( WPCMRI_PATH . 'src' );

// Declare HPOS Compatibility.
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

// Activation & Deactivation Hooks.
register_activation_hook( __FILE__, array( \WPCalibrate\MarketplaceReviewImporter\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WPCalibrate\MarketplaceReviewImporter\Plugin::class, 'deactivate' ) );

// Initialize Plugin on plugins_loaded.
add_action(
	'plugins_loaded',
	static function (): void {
		\WPCalibrate\MarketplaceReviewImporter\Plugin::get_instance()->init();
	}
);
