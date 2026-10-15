<?php
/**
 * Main Plugin Orchestrator and Lifecycle Manager.
 *
 * @package WPCalibrate\MarketplaceReviewImporter
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter;

use WPCalibrate\MarketplaceReviewImporter\Admin\Menu;
use WPCalibrate\MarketplaceReviewImporter\Admin\Notices\AdminNotices;
use WPCalibrate\MarketplaceReviewImporter\Admin\Settings\SettingsRepository;
use WPCalibrate\MarketplaceReviewImporter\Frontend\ReviewDisplay;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Database\Migration;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Database\Schema;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Files\UploadHandler;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Logging\Logger;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Scheduling\ActionSchedulerWorker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Admin Menu instance.
	 *
	 * @var Menu|null
	 */
	private ?Menu $menu = null;

	/**
	 * Whether WooCommerce is currently detected and active.
	 *
	 * @var bool
	 */
	private bool $woocommerce_active = false;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Plugin initialization on plugins_loaded.
	 *
	 * @return void
	 */
	public function init(): void {
		// 1. PHP Version check.
		if ( version_compare( PHP_VERSION, WPCMRI_MIN_PHP_VERSION, '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
						esc_html__( 'WPCalibrate Marketplace Review Importer:', 'wpcalibrate-marketplace-review-importer' ),
						sprintf(
							/* translators: 1: required version, 2: current version */
							esc_html__( 'PHP %1$s or higher is required. Your server is running PHP %2$s.', 'wpcalibrate-marketplace-review-importer' ),
							esc_html( WPCMRI_MIN_PHP_VERSION ),
							esc_html( PHP_VERSION )
						)
					);
				}
			);
			return;
		}

		// 2. Load textdomain.
		load_plugin_textdomain(
			WPCMRI_TEXT_DOMAIN,
			false,
			dirname( WPCMRI_BASENAME ) . '/languages'
		);

		// 3. Detect WooCommerce.
		$this->woocommerce_active = class_exists( 'WooCommerce' );

		// 4. Initialize Admin Notices.
		AdminNotices::init();

		if ( ! $this->woocommerce_active ) {
			add_action( 'admin_notices', array( AdminNotices::class, 'display_woocommerce_missing_notice' ) );
			// If in admin, still allow viewing the menu to see the notice and settings shell gracefully.
		}

		// 5. Check and run database migrations.
		Migration::check_and_migrate();

		// 6. Initialize Action Scheduler background worker.
		ActionSchedulerWorker::init();

		// 7. Initialize Admin Menu.
		if ( is_admin() ) {
			$this->menu = new Menu();
			$this->menu->init();
		}

		// 8. Initialize Frontend Review Presentation & Layout Engine.
		ReviewDisplay::init();

		// 9. Register developer helper filters and optional shortcodes.
		$this->register_public_hooks();
	}

	/**
	 * Register developer filters and shortcodes (non-intrusive to themes).
	 *
	 * @return void
	 */
	private function register_public_hooks(): void {
		// Filter for theme developers to retrieve imported review title.
		add_filter(
			'wpcmri_get_review_title',
			static function ( string $title, int $comment_id ): string {
				$stored = get_comment_meta( $comment_id, '_wpcmri_review_title', true );
				return ! empty( $stored ) ? (string) $stored : $title;
			},
			10,
			2
		);

		// Optional shortcode for source badge if a merchant/theme author desires it.
		add_shortcode(
			'wpcmri_source_badge',
			static function ( array $atts = array() ): string {
				$comment_id = isset( $atts['comment_id'] ) ? (int) $atts['comment_id'] : get_comment_ID();
				if ( ! $comment_id ) {
					return '';
				}

				$source = get_comment_meta( $comment_id, '_wpcmri_source', true );
				if ( ! $source ) {
					return '';
				}

				$provider = get_comment_meta( $comment_id, '_wpcmri_provider', true );
				$verified = get_comment_meta( $comment_id, '_wpcmri_source_verified_purchase', true );

				$badge_html = sprintf(
					'<span class="wpcmri-source-badge" data-provider="%1$s">%2$s%3$s</span>',
					esc_attr( (string) $provider ),
					esc_html__( 'Imported Marketplace Review', 'wpcalibrate-marketplace-review-importer' ),
					'1' === $verified ? ' (' . esc_html__( 'Verified at source', 'wpcalibrate-marketplace-review-importer' ) . ')' : ''
				);

				/**
				 * Filter source badge markup.
				 *
				 * @param string $badge_html HTML output.
				 * @param int    $comment_id Comment ID.
				 */
				return (string) apply_filters( 'wpcmri_source_badge_html', $badge_html, $comment_id );
			}
		);
	}

	/**
	 * Activation hook callback.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, WPCMRI_MIN_PHP_VERSION, '<' ) ) {
			wp_die(
				sprintf(
					/* translators: 1: required version, 2: current version */
					esc_html__( 'WPCalibrate Marketplace Review Importer requires PHP %1$s or higher. Your server is running PHP %2$s.', 'wpcalibrate-marketplace-review-importer' ),
					esc_html( WPCMRI_MIN_PHP_VERSION ),
					esc_html( PHP_VERSION )
				)
			);
		}

		// Initialize default options.
		SettingsRepository::init_defaults();

		// Run database schema installation.
		Schema::install_or_upgrade();

		// Record current versions.
		update_option( 'wpcmri_version', WPCMRI_VERSION );

		Logger::info( 'Plugin activated successfully' );
	}

	/**
	 * Deactivation hook callback.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Unschedule any pending Action Scheduler background actions.
		ActionSchedulerWorker::unschedule_actions();

		// Clean up any remaining temporary upload files.
		UploadHandler::cleanup_aged_files();

		Logger::info( 'Plugin deactivated' );
	}
}
