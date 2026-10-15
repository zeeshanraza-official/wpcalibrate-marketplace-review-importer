<?php
/**
 * Admin Menu and Navigation Controller.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Admin;

use WPCalibrate\MarketplaceReviewImporter\Admin\Screens\ScreenController;
use WPCalibrate\MarketplaceReviewImporter\Security\Permissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu
 */
final class Menu {

	/**
	 * Top-level WPCalibrate menu slug.
	 */
	public const PARENT_SLUG = 'wpcalibrate';

	/**
	 * Submenu page slug.
	 */
	public const PAGE_SLUG = 'wpcalibrate-marketplace-review-importer';

	/**
	 * Screen hook suffix stored after menu registration.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_head', array( $this, 'output_menu_icon_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register or reuse top-level WPCalibrate menu and add Marketplace Review Importer submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		if ( ! Permissions::can_manage_reviews() ) {
			return;
		}

		global $menu;

		$menu_icon = $this->get_branding_menu_icon();

		// Check if WPCalibrate top-level menu already exists.
		$parent_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				if ( isset( $item[2] ) && ( self::PARENT_SLUG === $item[2] || self::PAGE_SLUG === $item[2] ) ) {
					$parent_exists = true;
					// If the existing top-level menu icon is empty, generic dashicon, or broken data URI, restore official branding logo.
					if ( empty( $item[6] ) || 'dashicons-admin-generic' === $item[6] || str_starts_with( (string) $item[6], 'data:image/png' ) ) {
						$menu[ $key ][6] = $menu_icon;
					}
					break;
				}
			}
		}

		if ( ! $parent_exists ) {
			add_menu_page(
				'WPCalibrate',
				'WPCalibrate',
				Permissions::CAP_MANAGE_REVIEWS,
				self::PAGE_SLUG,
				array( $this, 'render_main_page' ),
				$menu_icon,
				58.4
			);
		}

		// Register submenu specifically for this plugin.
		$parent_slug       = $parent_exists ? self::PARENT_SLUG : self::PAGE_SLUG;
		$this->hook_suffix = (string) add_submenu_page(
			$parent_slug,
			__( 'Marketplace Review Importer', 'wpcalibrate-marketplace-review-importer' ),
			__( 'Review Importer', 'wpcalibrate-marketplace-review-importer' ),
			Permissions::CAP_MANAGE_REVIEWS,
			self::PAGE_SLUG,
			array( $this, 'render_main_page' )
		);
	}

	/**
	 * Output head styles so the WPCalibrate sidebar menu icon matches native WordPress styling on all admin pages.
	 *
	 * @return void
	 */
	public function output_menu_icon_styles(): void {
		?>
		<style type="text/css">
		#adminmenu li.toplevel_page_wpcalibrate div.wp-menu-image img,
		#adminmenu li.toplevel_page_wpcalibrate-marketplace-review-importer div.wp-menu-image img,
		#adminmenu li[id*="wpcalibrate"] div.wp-menu-image img,
		#adminmenu a[href*="wpcalibrate"] div.wp-menu-image img {
			width: 20px !important;
			height: 20px !important;
			max-width: 20px !important;
			max-height: 20px !important;
			padding: 7px 0 0 0 !important;
			box-sizing: content-box !important;
			display: inline-block !important;
			vertical-align: top !important;
			opacity: 0.82;
			transition: opacity 0.15s ease-in-out, filter 0.15s ease-in-out;
		}
		#adminmenu li.toplevel_page_wpcalibrate:hover div.wp-menu-image img,
		#adminmenu li.toplevel_page_wpcalibrate.wp-has-current-submenu div.wp-menu-image img,
		#adminmenu li.toplevel_page_wpcalibrate.current div.wp-menu-image img,
		#adminmenu li.toplevel_page_wpcalibrate-marketplace-review-importer:hover div.wp-menu-image img,
		#adminmenu li.toplevel_page_wpcalibrate-marketplace-review-importer.wp-has-current-submenu div.wp-menu-image img,
		#adminmenu li.toplevel_page_wpcalibrate-marketplace-review-importer.current div.wp-menu-image img,
		#adminmenu li[id*="wpcalibrate"]:hover div.wp-menu-image img,
		#adminmenu li[id*="wpcalibrate"].wp-has-current-submenu div.wp-menu-image img,
		#adminmenu li[id*="wpcalibrate"].current div.wp-menu-image img,
		#adminmenu a[href*="wpcalibrate"]:hover div.wp-menu-image img {
			opacity: 1 !important;
			filter: brightness(0) invert(1) !important;
		}
		</style>
		<?php
	}

	/**
	 * Get menu icon: URL to the authentic provided WPCalibrate branding logo.
	 *
	 * Uses direct URL rather than base64 data URI because WordPress core esc_url()
	 * strips 'data:image/png;base64,' protocols in menu-header.php, which causes broken image icons.
	 *
	 * @return string Image URL or Dashicon fallback.
	 */
	public function get_branding_menu_icon(): string {
		$icon_file = WPCMRI_PATH . 'branding/icon-menu-normal.png';
		if ( file_exists( $icon_file ) && is_readable( $icon_file ) ) {
			return WPCMRI_URL . 'branding/icon-menu-normal.png';
		}

		$fallback = WPCMRI_PATH . 'branding/icon-white.png';
		if ( file_exists( $fallback ) && is_readable( $fallback ) ) {
			return WPCMRI_URL . 'branding/icon-white.png';
		}

		return 'dashicons-star-filled';
	}

	/**
	 * Enqueue styles and scripts only on this plugin's admin screens.
	 *
	 * @param string $hook Page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Strictly restrict asset loading to our plugin's screen.
		if ( ! str_contains( $hook, self::PAGE_SLUG ) && ! ( 'toplevel_page_' . self::PARENT_SLUG === $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'wpcmri-admin-css',
			WPCMRI_URL . 'assets/css/admin.css',
			array(),
			WPCMRI_VERSION
		);

		// Enqueue RTL stylesheet automatically if is_rtl().
		if ( is_rtl() ) {
			wp_enqueue_style(
				'wpcmri-admin-rtl-css',
				WPCMRI_URL . 'assets/css/admin-rtl.css',
				array( 'wpcmri-admin-css' ),
				WPCMRI_VERSION
			);
		}

		wp_enqueue_script(
			'wpcmri-admin-js',
			WPCMRI_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WPCMRI_VERSION,
			true
		);

		$demo_json_content = '';
		$demo_json_file    = WPCMRI_PATH . 'assets/demo/demo-reviews.json';
		if ( file_exists( $demo_json_file ) && is_readable( $demo_json_file ) ) {
			$demo_json_content = (string) file_get_contents( $demo_json_file );
		}

		wp_localize_script(
			'wpcmri-admin-js',
			'wpcmriData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wpcmri_admin_action' ),
				'demoJson'  => $demo_json_content,
				'strings'   => array(
					'confirmCancel' => __( 'Are you sure you want to cancel this import?', 'wpcalibrate-marketplace-review-importer' ),
					'confirmDelete' => __( 'Are you sure you want to proceed?', 'wpcalibrate-marketplace-review-importer' ),
					'loading'       => __( 'Processing...', 'wpcalibrate-marketplace-review-importer' ),
					'demoLoaded'    => __( '15 demo reviews loaded into JSON box!', 'wpcalibrate-marketplace-review-importer' ),
				),
			)
		);
	}

	/**
	 * Render the main plugin admin interface.
	 *
	 * @return void
	 */
	public function render_main_page(): void {
		ScreenController::render();
	}
}
