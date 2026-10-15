<?php
/**
 * Admin Screens Controller and View Renderer.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Admin\Screens
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Admin\Screens;

use WPCalibrate\MarketplaceReviewImporter\Admin\Menu;
use WPCalibrate\MarketplaceReviewImporter\Admin\Notices\AdminNotices;
use WPCalibrate\MarketplaceReviewImporter\Admin\Settings\SettingsRepository;
use WPCalibrate\MarketplaceReviewImporter\Application\Import\CsvImporter;
use WPCalibrate\MarketplaceReviewImporter\Application\Import\ImportService;
use WPCalibrate\MarketplaceReviewImporter\Application\Import\JsonImporter;
use WPCalibrate\MarketplaceReviewImporter\Domain\Provider\AmazonSourceParser;
use WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewDTO;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Database\ImportRepository;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Files\UploadHandler;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Scheduling\ActionSchedulerWorker;
use WPCalibrate\MarketplaceReviewImporter\Providers\ProviderRegistry;
use WPCalibrate\MarketplaceReviewImporter\Security\Permissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ScreenController
 */
final class ScreenController {

	/**
	 * Main render entry point.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! Permissions::can_manage_reviews() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wpcalibrate-marketplace-review-importer' ) );
		}

		self::handle_actions();

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'overview';
		$valid_tabs  = array( 'overview', 'import', 'history', 'providers', 'settings', 'license', 'support' );
		if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
			$current_tab = 'overview';
		}

		?>
		<div class="wrap wpcmri-admin-wrap">
			<!-- Modern Premium Header Banner -->
			<header class="wpcmri-hero-header">
				<div class="wpcmri-hero-left">
					<div class="wpcmri-hero-badge">
						<?php
						$dark_icon_path = WPCMRI_PATH . 'branding/icon-dark.png';
						if ( file_exists( $dark_icon_path ) ) {
							echo '<img src="' . esc_url( WPCMRI_URL . 'branding/icon-dark.png' ) . '" alt="WPCalibrate" class="wpcmri-hero-logo" />';
						} else {
							echo '<span class="dashicons dashicons-star-filled wpcmri-hero-fallback-icon"></span>';
						}
						?>
					</div>
					<div class="wpcmri-hero-text">
						<div class="wpcmri-title-row">
							<h1 class="wpcmri-hero-title"><?php esc_html_e( 'Marketplace Review Importer', 'wpcalibrate-marketplace-review-importer' ); ?></h1>
							<span class="wpcmri-badge wpcmri-badge-version">v<?php echo esc_html( WPCMRI_VERSION ); ?></span>
							<span class="wpcmri-badge wpcmri-badge-pro-ready"><?php esc_html_e( 'HPOS Ready', 'wpcalibrate-marketplace-review-importer' ); ?></span>
						</div>
						<p class="wpcmri-hero-subtitle">
							<?php esc_html_e( 'Import legitimate Amazon customer reviews via free CSV, JSON, or automated SerpApi directly into WooCommerce native product reviews.', 'wpcalibrate-marketplace-review-importer' ); ?>
						</p>
					</div>
				</div>
				<div class="wpcmri-hero-actions">
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'import' ), admin_url( 'admin.php' ) ) ); ?>" class="wpcmri-btn wpcmri-btn-primary">
						<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'New Import', 'wpcalibrate-marketplace-review-importer' ); ?>
					</a>
					<a href="https://wpcalibrate.com/" target="_blank" rel="noopener noreferrer" class="wpcmri-btn wpcmri-btn-ghost">
						<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Documentation', 'wpcalibrate-marketplace-review-importer' ); ?>
					</a>
				</div>
			</header>

			<!-- Modern Segmented Pill Tabs Navigation -->
			<nav class="wpcmri-tabs-nav" aria-label="<?php esc_attr_e( 'Plugin Settings Tabs', 'wpcalibrate-marketplace-review-importer' ); ?>">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'overview' ), admin_url( 'admin.php' ) ) ); ?>" class="wpcmri-tab-link <?php echo 'overview' === $current_tab ? 'is-active' : ''; ?>">
					<span class="dashicons dashicons-info-outline"></span>
					<span class="wpcmri-tab-label"><?php esc_html_e( 'Overview', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'import' ), admin_url( 'admin.php' ) ) ); ?>" class="wpcmri-tab-link <?php echo 'import' === $current_tab ? 'is-active' : ''; ?>">
					<span class="dashicons dashicons-upload"></span>
					<span class="wpcmri-tab-label"><?php esc_html_e( 'Import Reviews', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'history' ), admin_url( 'admin.php' ) ) ); ?>" class="wpcmri-tab-link <?php echo 'history' === $current_tab ? 'is-active' : ''; ?>">
					<span class="dashicons dashicons-backup"></span>
					<span class="wpcmri-tab-label"><?php esc_html_e( 'Import History', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'providers' ), admin_url( 'admin.php' ) ) ); ?>" class="wpcmri-tab-link <?php echo 'providers' === $current_tab ? 'is-active' : ''; ?>">
					<span class="dashicons dashicons-networking"></span>
					<span class="wpcmri-tab-label"><?php esc_html_e( 'Providers', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'settings' ), admin_url( 'admin.php' ) ) ); ?>" class="wpcmri-tab-link <?php echo 'settings' === $current_tab ? 'is-active' : ''; ?>">
					<span class="dashicons dashicons-admin-settings"></span>
					<span class="wpcmri-tab-label"><?php esc_html_e( 'Settings', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'license' ), admin_url( 'admin.php' ) ) ); ?>" class="wpcmri-tab-link <?php echo 'license' === $current_tab ? 'is-active' : ''; ?>">
					<span class="dashicons dashicons-admin-network"></span>
					<span class="wpcmri-tab-label"><?php esc_html_e( 'License', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'support' ), admin_url( 'admin.php' ) ) ); ?>" class="wpcmri-tab-link <?php echo 'support' === $current_tab ? 'is-active' : ''; ?>">
					<span class="dashicons dashicons-sos"></span>
					<span class="wpcmri-tab-label"><?php esc_html_e( 'Support', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>
			</nav>

			<main class="wpcmri-tab-body">
				<?php
				switch ( $current_tab ) {
					case 'overview':
						self::render_tab_overview();
						break;
					case 'history':
						self::render_tab_history();
						break;
					case 'providers':
						self::render_tab_providers();
						break;
					case 'settings':
						self::render_tab_settings();
						break;
					case 'license':
						self::render_tab_license();
						break;
					case 'support':
						self::render_tab_support();
						break;
					case 'import':
					default:
						self::render_tab_import();
						break;
				}
				?>
			</main>
		</div>
		<?php
	}

	/**
	 * Handle incoming POST/GET actions with nonces and capability checks.
	 *
	 * @return void
	 */
	private static function handle_actions(): void {
		// 1. Download templates actions.
		if ( isset( $_GET['action'] ) ) {
			$action = sanitize_key( (string) $_GET['action'] );

			if ( 'download_csv_template' === $action ) {
				check_admin_referer( 'wpcmri_download_template' );
				CsvImporter::output_template();
			}

			if ( 'download_json_template' === $action ) {
				check_admin_referer( 'wpcmri_download_template' );
				JsonImporter::output_template();
			}

			if ( 'download_demo_csv' === $action ) {
				check_admin_referer( 'wpcmri_download_demo' );
				self::output_demo_file( 'csv' );
			}

			if ( 'download_demo_json' === $action ) {
				check_admin_referer( 'wpcmri_download_demo' );
				self::output_demo_file( 'json' );
			}

			if ( 'cancel_import' === $action && isset( $_GET['import_id'] ) ) {
				check_admin_referer( 'wpcmri_cancel_import_' . sanitize_key( $_GET['import_id'] ) );
				if ( Permissions::can_manage_reviews() ) {
					ImportService::cancel_import( sanitize_key( $_GET['import_id'] ) );
					AdminNotices::add_flash( __( 'Import job cancelled successfully.', 'wpcalibrate-marketplace-review-importer' ), 'info' );
					wp_safe_redirect( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'history' ), admin_url( 'admin.php' ) ) );
					exit;
				}
			}
		}

		// 2. Settings form submission.
		if ( isset( $_POST['wpcmri_save_settings'] ) ) {
			check_admin_referer( 'wpcmri_save_settings_action' );
			if ( ! Permissions::can_manage_settings() ) {
				AdminNotices::add_flash( __( 'You do not have permission to update settings.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
				return;
			}

			$defaults = array(
				'wpcmri_default_status'             => isset( $_POST['wpcmri_default_status'] ) && '0' === $_POST['wpcmri_default_status'] ? '0' : '1',
				'wpcmri_max_reviews_per_import'     => max( 1, (int) ( $_POST['wpcmri_max_reviews_per_import'] ?? 100 ) ),
				'wpcmri_batch_size'                 => max( 5, (int) ( $_POST['wpcmri_batch_size'] ?? 20 ) ),
				'wpcmri_history_retention_days'     => max( 0, (int) ( $_POST['wpcmri_history_retention_days'] ?? 60 ) ),
				'wpcmri_temp_retention_hours'       => max( 1, (int) ( $_POST['wpcmri_temp_retention_hours'] ?? 24 ) ),
				'wpcmri_store_review_titles'        => ! empty( $_POST['wpcmri_store_review_titles'] ) ? 'yes' : 'no',
				'wpcmri_store_source_urls'          => ! empty( $_POST['wpcmri_store_source_urls'] ) ? 'yes' : 'no',
				'wpcmri_enable_logging'             => ! empty( $_POST['wpcmri_enable_logging'] ) ? 'yes' : 'no',
				'wpcmri_uninstall_cleanup'          => isset( $_POST['wpcmri_uninstall_cleanup'] ) && 'delete' === $_POST['wpcmri_uninstall_cleanup'] ? 'delete' : 'keep',
				'wpcmri_default_min_rating'         => max( 1, min( 5, (int) ( $_POST['wpcmri_default_min_rating'] ?? 1 ) ) ),
				'wpcmri_default_max_rating'         => max( 1, min( 5, (int) ( $_POST['wpcmri_default_max_rating'] ?? 5 ) ) ),
				'wpcmri_default_min_length'         => max( 0, (int) ( $_POST['wpcmri_default_min_length'] ?? 0 ) ),
				'wpcmri_duplicate_behavior'         => isset( $_POST['wpcmri_duplicate_behavior'] ) && 'update_meta' === $_POST['wpcmri_duplicate_behavior'] ? 'update_meta' : 'skip',
				'wpcmri_default_marketplace'        => sanitize_text_field( (string) ( $_POST['wpcmri_default_marketplace'] ?? 'amazon.com' ) ),
				'wpcmri_enable_frontend_styling'    => ! empty( $_POST['wpcmri_enable_frontend_styling'] ) ? 'yes' : 'no',
				'wpcmri_show_review_summary'        => ! empty( $_POST['wpcmri_show_review_summary'] ) ? 'yes' : 'no',
				'wpcmri_show_marketplace_badge'     => ! empty( $_POST['wpcmri_show_marketplace_badge'] ) ? 'yes' : 'no',
				'wpcmri_show_review_media'          => ! empty( $_POST['wpcmri_show_review_media'] ) ? 'yes' : 'no',
				'wpcmri_show_helpful_votes'         => ! empty( $_POST['wpcmri_show_helpful_votes'] ) ? 'yes' : 'no',
			);

			foreach ( $defaults as $k => $v ) {
				SettingsRepository::set( $k, $v );
			}

			AdminNotices::add_flash( __( 'Settings saved successfully.', 'wpcalibrate-marketplace-review-importer' ), 'success' );
		}

		// 3. Provider Settings & Connection Test.
		if ( isset( $_POST['wpcmri_save_provider_settings'] ) ) {
			check_admin_referer( 'wpcmri_save_provider_action' );
			if ( ! Permissions::can_manage_settings() ) {
				AdminNotices::add_flash( __( 'Permission denied.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
				return;
			}

			$enabled = ! empty( $_POST['wpcmri_provider_serpapi_enabled'] ) ? 'yes' : 'no';
			SettingsRepository::set( 'wpcmri_provider_serpapi_enabled', $enabled );

			$submitted_key = trim( (string) ( $_POST['wpcmri_provider_serpapi_api_key'] ?? '' ) );
			if ( ! empty( $submitted_key ) && ! str_contains( $submitted_key, '***' ) ) {
				SettingsRepository::set( 'wpcmri_provider_serpapi_api_key', sanitize_text_field( $submitted_key ) );
			} elseif ( empty( $submitted_key ) ) {
				SettingsRepository::set( 'wpcmri_provider_serpapi_api_key', '' );
			}

			if ( isset( $_POST['test_connection'] ) ) {
				$key_to_test = SettingsRepository::get( 'wpcmri_provider_serpapi_api_key', '' );
				$provider    = ProviderRegistry::get_instance()->get_provider( 'serpapi' );
				if ( $provider ) {
					$test_result = $provider->validate_credentials( (string) $key_to_test );
					if ( $test_result['valid'] ) {
						AdminNotices::add_flash( $test_result['message'], 'success' );
					} else {
						AdminNotices::add_flash( $test_result['message'], 'error' );
					}
				}
			} else {
				AdminNotices::add_flash( __( 'Provider settings updated.', 'wpcalibrate-marketplace-review-importer' ), 'success' );
			}
		}

		// 4. License Save.
		if ( isset( $_POST['wpcmri_save_license'] ) ) {
			check_admin_referer( 'wpcmri_save_license_action' );
			if ( ! Permissions::can_manage_settings() ) {
				AdminNotices::add_flash( __( 'Permission denied.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
				return;
			}

			$license_key = trim( (string) ( $_POST['wpcmri_license_key'] ?? '' ) );
			if ( ! str_contains( $license_key, '***' ) ) {
				SettingsRepository::set( 'wpcmri_license_key', sanitize_text_field( $license_key ) );
			}

			AdminNotices::add_flash( __( 'License key saved. Note: Remote license verification service is not configured during development; all core review import features remain fully active.', 'wpcalibrate-marketplace-review-importer' ), 'info' );
		}
	}

	/**
	 * Render Tab 0: Overview (How to use, Purpose, and Method).
	 *
	 * @return void
	 */
	private static function render_tab_overview(): void {
		$csv_dl_url       = wp_nonce_url( add_query_arg( array( 'action' => 'download_csv_template' ), admin_url( 'admin.php?page=' . Menu::PAGE_SLUG ) ), 'wpcmri_download_template' );
		$json_dl_url      = wp_nonce_url( add_query_arg( array( 'action' => 'download_json_template' ), admin_url( 'admin.php?page=' . Menu::PAGE_SLUG ) ), 'wpcmri_download_template' );
		$demo_csv_dl_url  = wp_nonce_url( add_query_arg( array( 'action' => 'download_demo_csv' ), admin_url( 'admin.php?page=' . Menu::PAGE_SLUG ) ), 'wpcmri_download_demo' );
		$demo_json_dl_url = wp_nonce_url( add_query_arg( array( 'action' => 'download_demo_json' ), admin_url( 'admin.php?page=' . Menu::PAGE_SLUG ) ), 'wpcmri_download_demo' );
		$import_url       = add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'import' ), admin_url( 'admin.php' ) );
		$history_url      = add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'history' ), admin_url( 'admin.php' ) );
		$serp_url         = add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'providers' ), admin_url( 'admin.php' ) );

		?>
		<div class="wpcmri-overview-container">
			<!-- Quick Intro Card -->
			<section class="wpcmri-section-card wpcmri-overview-welcome">
				<div class="wpcmri-welcome-content">
					<span class="wpcmri-kicker"><?php esc_html_e( 'Getting Started Guide', 'wpcalibrate-marketplace-review-importer' ); ?></span>
					<h2><?php esc_html_e( 'Transform Your Store with Authentic Social Proof', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
					<p class="wpcmri-overview-lead">
						<?php esc_html_e( 'Marketplace Review Importer provides a reliable, ethical, and high-performance pipeline to bring customer feedback from marketplaces like Amazon directly into your WooCommerce products. Unlike risky scrapers, our plugin uses native WordPress comment storage, strict 4-tier deduplication, and optional authenticated APIs.', 'wpcalibrate-marketplace-review-importer' ); ?>
					</p>
					<div class="wpcmri-overview-cta-group">
						<a href="<?php echo esc_url( $import_url ); ?>" class="wpcmri-btn wpcmri-btn-primary wpcmri-btn-lg">
							<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Start Your First Import', 'wpcalibrate-marketplace-review-importer' ); ?>
						</a>
						<a href="<?php echo esc_url( $demo_csv_dl_url ); ?>" class="wpcmri-btn wpcmri-btn-secondary">
							<span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e( 'Download Demo CSV (15 Reviews)', 'wpcalibrate-marketplace-review-importer' ); ?>
						</a>
						<a href="<?php echo esc_url( $demo_json_dl_url ); ?>" class="wpcmri-btn wpcmri-btn-secondary">
							<span class="dashicons dashicons-media-code"></span> <?php esc_html_e( 'Download Demo JSON', 'wpcalibrate-marketplace-review-importer' ); ?>
						</a>
					</div>
				</div>
			</section>

			<!-- 3 Core Pillars -->
			<section class="wpcmri-pillars-grid">
				<div class="wpcmri-pillar-card">
					<div class="wpcmri-pillar-icon" style="background:#eff6ff;color:#2563eb;">
						<span class="dashicons dashicons-heart"></span>
					</div>
					<h3><?php esc_html_e( '1. Purpose & Social Proof', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
					<p><?php esc_html_e( 'Build instant buyer confidence by transferring legitimate product reviews you own or sell on Amazon. Reviews populate native WooCommerce catalog stars and customer review lists.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>
				<div class="wpcmri-pillar-card">
					<div class="wpcmri-pillar-icon" style="background:#ecfdf5;color:#059669;">
						<span class="dashicons dashicons-media-spreadsheet"></span>
					</div>
					<h3><?php esc_html_e( '2. 100% Free Core Engine', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
					<p><?php esc_html_e( 'Import unlimited reviews using standard CSV spreadsheets or structured JSON files. No external accounts, no paid subscriptions, and no recurring fees required for core importing.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>
				<div class="wpcmri-pillar-card">
					<div class="wpcmri-pillar-icon" style="background:#fef3c7;color:#d97706;">
						<span class="dashicons dashicons-shield"></span>
					</div>
					<h3><?php esc_html_e( '3. Zero-Spoofing Ethics', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
					<p><?php esc_html_e( 'We never generate fake reviews or falsely mark third-party reviews as local WooCommerce verified owners. Provenance is transparently preserved in namespaced metadata.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>
			</section>

			<!-- Step-by-Step Methodology -->
			<section class="wpcmri-section-card">
				<div class="wpcmri-section-header">
					<div>
						<h2><?php esc_html_e( 'How to Use: The 5-Step Import Method', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
						<p class="wpcmri-section-desc"><?php esc_html_e( 'Follow this structured workflow to safely preview, deduplicate, and import marketplace reviews into your WooCommerce catalog.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
					</div>
				</div>

				<div class="wpcmri-steps-timeline">
					<div class="wpcmri-step-item">
						<div class="wpcmri-step-num">1</div>
						<div class="wpcmri-step-content">
							<h4><?php esc_html_e( 'Select Target WooCommerce Product', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
							<p><?php esc_html_e( 'Navigate to "Import Reviews" and select the destination WooCommerce product. Reviews attach natively to the parent product comments so standard WooCommerce rating stars display across shop pages.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>

					<div class="wpcmri-step-item">
						<div class="wpcmri-step-num">2</div>
						<div class="wpcmri-step-content">
							<h4><?php esc_html_e( 'Choose Your Preferred Ingestion Source', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
							<p><?php esc_html_e( 'Select between CSV file upload, versioned JSON payload, or automated Amazon ASIN lookup via the pluggable SerpApi provider.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
							<div class="wpcmri-step-badges">
								<span class="wpcmri-mini-badge">CSV Spreadsheets</span>
								<span class="wpcmri-mini-badge">JSON Schema v1.0</span>
								<span class="wpcmri-mini-badge">SerpApi (Amazon ASIN)</span>
							</div>
						</div>
					</div>

					<div class="wpcmri-step-item">
						<div class="wpcmri-step-num">3</div>
						<div class="wpcmri-step-content">
							<h4><?php esc_html_e( 'Apply Smart Review Filters', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
							<p><?php esc_html_e( 'Configure star rating boundaries (1 to 5 stars), minimum character lengths, and verified-purchase filters. Negative reviews are not hidden by default to ensure unbiased, trustworthy review presentation.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>

					<div class="wpcmri-step-item">
						<div class="wpcmri-step-num">4</div>
						<div class="wpcmri-step-content">
							<h4><?php esc_html_e( 'Inspect Live Preview & Deduplication', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
							<p><?php esc_html_e( 'Before writing any data to your database, inspect the live rating breakdown, total discovered reviews, and duplicate count. Our 4-tier fingerprinting engine ensures repeat imports safely skip existing records.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>

					<div class="wpcmri-step-item">
						<div class="wpcmri-step-num">5</div>
						<div class="wpcmri-step-content">
							<h4><?php esc_html_e( 'Publish & Moderate via Native Tools', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
							<p><?php esc_html_e( 'Confirm the import. Small imports run synchronously, while large datasets run smoothly in background batches via Action Scheduler. Moderate and manage reviews anytime under WooCommerce > Products > Reviews or standard WordPress Comments.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>
				</div>
			</section>

			<!-- Feature Matrix Grid -->
			<section class="wpcmri-section-card">
				<div class="wpcmri-section-header">
					<div>
						<h2><?php esc_html_e( 'Built for WooCommerce Store Owners & Developers', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
						<p class="wpcmri-section-desc"><?php esc_html_e( 'Engineered with strict WordPress coding standards, high performance, and zero bloat.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
					</div>
				</div>

				<div class="wpcmri-features-grid">
					<div class="wpcmri-feature-box">
						<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span>
						<div>
							<strong><?php esc_html_e( 'WooCommerce HPOS Compatible', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
							<p><?php esc_html_e( 'Fully tested with High-Performance Order Storage (custom_order_tables) and Cart & Checkout Blocks.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>
					<div class="wpcmri-feature-box">
						<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span>
						<div>
							<strong><?php esc_html_e( 'Idempotent 4-Tier Fingerprinting', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
							<p><?php esc_html_e( 'Checks marketplace IDs, ASINs, source URLs, and normalized SHA-256 hashes to guarantee zero duplicate reviews.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>
					<div class="wpcmri-feature-box">
						<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span>
						<div>
							<strong><?php esc_html_e( 'Action Scheduler Background Processing', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
							<p><?php esc_html_e( 'Imports process in configurable batches with transient locking, avoiding PHP max_execution_time limits.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>
					<div class="wpcmri-feature-box">
						<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span>
						<div>
							<strong><?php esc_html_e( 'Automatic Rating Syncing', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
							<p><?php esc_html_e( 'Synchronizes WooCommerce product average ratings and rating counts automatically upon completion.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>
					<div class="wpcmri-feature-box">
						<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span>
						<div>
							<strong><?php esc_html_e( 'Masked Secret Credentials', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
							<p><?php esc_html_e( 'API keys are stored securely, masked in form fields, and automatically redacted from application logs.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>
					<div class="wpcmri-feature-box">
						<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span>
						<div>
							<strong><?php esc_html_e( 'Pluggable Architecture', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
							<p><?php esc_html_e( 'Developers can register additional third-party review data providers using the wpcmri_register_providers filter.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>
				</div>
			</section>

			<!-- Quick Actions Footer Callout -->
			<div class="wpcmri-footer-card">
				<div>
					<h3><?php esc_html_e( 'Ready to enrich your store products?', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
					<p><?php esc_html_e( 'Select your destination product and preview your review spreadsheet in less than 2 minutes.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>
				<div class="wpcmri-footer-actions">
					<a href="<?php echo esc_url( $import_url ); ?>" class="wpcmri-btn wpcmri-btn-primary wpcmri-btn-lg">
						<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import Reviews Now', 'wpcalibrate-marketplace-review-importer' ); ?>
					</a>
					<a href="<?php echo esc_url( $history_url ); ?>" class="wpcmri-btn wpcmri-btn-secondary">
						<span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'View Import History', 'wpcalibrate-marketplace-review-importer' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Tab 1: Import Reviews.
	 *
	 * @return void
	 */
	private static function render_tab_import(): void {
		$is_preview = isset( $_POST['wpcmri_stage'] ) && 'preview' === $_POST['wpcmri_stage'];
		$is_confirm = isset( $_POST['wpcmri_stage'] ) && 'confirm' === $_POST['wpcmri_stage'];

		if ( $is_confirm ) {
			self::execute_import_submission();
			return;
		}

		if ( $is_preview ) {
			self::render_preview_stage();
			return;
		}

		self::render_source_selection_stage();
	}

	/**
	 * Stage 1: Select WooCommerce product, source, and configure source options.
	 *
	 * @return void
	 */
	private static function render_source_selection_stage(): void {
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$marketplaces = AmazonSourceParser::get_supported_marketplaces();
		$serpapi      = ProviderRegistry::get_instance()->get_provider( 'serpapi' );
		$is_serp_on   = $serpapi && $serpapi->is_configured();

		?>
		<div class="wpcmri-section-card">
			<div class="wpcmri-section-header">
				<div>
					<h2><?php esc_html_e( 'Step 1: Choose Product & Review Source', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
					<p class="wpcmri-section-desc"><?php esc_html_e( 'Select the destination WooCommerce product and configure where to retrieve customer reviews from.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>
			</div>

			<form method="post" enctype="multipart/form-data" class="wpcmri-form">
				<?php wp_nonce_field( 'wpcmri_preview_action', 'wpcmri_nonce' ); ?>
				<input type="hidden" name="wpcmri_stage" value="preview" />

				<!-- Destination Product Picker -->
				<div class="wpcmri-form-group">
					<label for="target_product_id" class="wpcmri-form-label">
						<?php esc_html_e( 'Destination WooCommerce Product', 'wpcalibrate-marketplace-review-importer' ); ?>
						<span class="wpcmri-required">*</span>
					</label>
					<div class="wpcmri-select-wrapper">
						<select name="target_product_id" id="target_product_id" class="wpcmri-select" required>
							<option value=""><?php esc_html_e( '-- Search / Select a WooCommerce Product --', 'wpcalibrate-marketplace-review-importer' ); ?></option>
							<?php foreach ( $products as $prod ) : ?>
								<?php
								$wc_prod = function_exists( 'wc_get_product' ) ? wc_get_product( $prod->ID ) : null;
								$sku     = $wc_prod && method_exists( $wc_prod, 'get_sku' ) ? $wc_prod->get_sku() : '';
								$label   = sprintf( '#%d - %s%s', $prod->ID, $prod->post_title, ! empty( $sku ) ? ' (SKU: ' . $sku . ')' : '' );
								?>
								<option value="<?php echo esc_attr( (string) $prod->ID ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<p class="wpcmri-form-hint"><?php esc_html_e( 'Reviews attach to the parent WooCommerce product comments so star ratings reflect across the store catalog.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>

				<!-- Source Selection Interactive Cards -->
				<div class="wpcmri-form-group">
					<label class="wpcmri-form-label">
						<?php esc_html_e( 'Choose Review Data Source', 'wpcalibrate-marketplace-review-importer' ); ?>
						<span class="wpcmri-required">*</span>
					</label>

					<div class="wpcmri-source-cards-grid">
						<!-- CSV Option Card -->
						<label class="wpcmri-source-card is-selected" id="wpcmri-card-csv">
							<input type="radio" name="source_type" value="csv" checked="checked" class="wpcmri-source-radio" />
							<div class="wpcmri-source-card-header">
								<span class="dashicons dashicons-media-spreadsheet wpcmri-source-icon"></span>
								<span class="wpcmri-badge wpcmri-badge-free"><?php esc_html_e( '100% Free', 'wpcalibrate-marketplace-review-importer' ); ?></span>
							</div>
							<h4><?php esc_html_e( 'CSV Spreadsheet', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
							<p><?php esc_html_e( 'Upload standard CSV files containing customer reviews. Free and no external account required.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</label>

						<!-- JSON Option Card -->
						<label class="wpcmri-source-card" id="wpcmri-card-json">
							<input type="radio" name="source_type" value="json" class="wpcmri-source-radio" />
							<div class="wpcmri-source-card-header">
								<span class="dashicons dashicons-media-code wpcmri-source-icon"></span>
								<span class="wpcmri-badge wpcmri-badge-free"><?php esc_html_e( '100% Free', 'wpcalibrate-marketplace-review-importer' ); ?></span>
							</div>
							<h4><?php esc_html_e( 'JSON Payload', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
							<p><?php esc_html_e( 'Upload or paste structured JSON review files adhering to our versioned schema.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</label>

						<!-- SerpApi Option Card -->
						<label class="wpcmri-source-card <?php echo ! $is_serp_on ? 'is-disabled' : ''; ?>" id="wpcmri-card-serpapi">
							<input type="radio" name="source_type" value="serpapi" class="wpcmri-source-radio" <?php echo ! $is_serp_on ? 'disabled' : ''; ?> />
							<div class="wpcmri-source-card-header">
								<span class="dashicons dashicons-networking wpcmri-source-icon"></span>
								<?php if ( $is_serp_on ) : ?>
									<span class="wpcmri-badge wpcmri-badge-success"><?php esc_html_e( 'Ready', 'wpcalibrate-marketplace-review-importer' ); ?></span>
								<?php else : ?>
									<span class="wpcmri-badge wpcmri-badge-warning"><?php esc_html_e( 'Setup Needed', 'wpcalibrate-marketplace-review-importer' ); ?></span>
								<?php endif; ?>
							</div>
							<h4><?php esc_html_e( 'Amazon via SerpApi', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
							<p><?php esc_html_e( 'Automatically fetch reviews by entering an Amazon ASIN or product URL using your SerpApi key.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</label>
					</div>
				</div>

				<!-- Source Config Panels -->
				<div class="wpcmri-source-config-wrapper">
					<!-- CSV Panel -->
					<div id="wpcmri-panel-csv" class="wpcmri-source-panel">
						<div class="wpcmri-panel-heading">
							<h3><?php esc_html_e( 'Upload CSV Spreadsheet', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
							<div style="display:flex;gap:8px;flex-wrap:wrap;">
								<?php
								$csv_dl_url      = wp_nonce_url( add_query_arg( array( 'action' => 'download_csv_template' ), admin_url( 'admin.php?page=' . Menu::PAGE_SLUG ) ), 'wpcmri_download_template' );
								$demo_csv_dl_url = wp_nonce_url( add_query_arg( array( 'action' => 'download_demo_csv' ), admin_url( 'admin.php?page=' . Menu::PAGE_SLUG ) ), 'wpcmri_download_demo' );
								?>
								<a href="<?php echo esc_url( $demo_csv_dl_url ); ?>" class="wpcmri-btn wpcmri-btn-secondary wpcmri-btn-sm">
									<span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e( 'Download Demo CSV (15 Reviews)', 'wpcalibrate-marketplace-review-importer' ); ?>
								</a>
								<a href="<?php echo esc_url( $csv_dl_url ); ?>" class="wpcmri-btn wpcmri-btn-secondary wpcmri-btn-sm">
									<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download Template', 'wpcalibrate-marketplace-review-importer' ); ?>
								</a>
							</div>
						</div>
						<div class="wpcmri-dropzone">
							<span class="dashicons dashicons-upload wpcmri-dropzone-icon"></span>
							<h4><?php esc_html_e( 'Choose a CSV file from your computer', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
							<p class="wpcmri-form-hint"><?php esc_html_e( 'UTF-8 encoded .csv files up to 10MB supported.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
							<input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" class="wpcmri-file-input" />
						</div>
					</div>

					<!-- JSON Panel -->
					<div id="wpcmri-panel-json" class="wpcmri-source-panel" style="display:none;">
						<div class="wpcmri-panel-heading">
							<h3><?php esc_html_e( 'Upload or Paste JSON Data', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
							<div style="display:flex;gap:8px;flex-wrap:wrap;">
								<?php
								$json_dl_url      = wp_nonce_url( add_query_arg( array( 'action' => 'download_json_template' ), admin_url( 'admin.php?page=' . Menu::PAGE_SLUG ) ), 'wpcmri_download_template' );
								$demo_json_dl_url = wp_nonce_url( add_query_arg( array( 'action' => 'download_demo_json' ), admin_url( 'admin.php?page=' . Menu::PAGE_SLUG ) ), 'wpcmri_download_demo' );
								?>
								<a href="<?php echo esc_url( $demo_json_dl_url ); ?>" class="wpcmri-btn wpcmri-btn-secondary wpcmri-btn-sm">
									<span class="dashicons dashicons-media-code"></span> <?php esc_html_e( 'Download Demo JSON', 'wpcalibrate-marketplace-review-importer' ); ?>
								</a>
								<a href="<?php echo esc_url( $json_dl_url ); ?>" class="wpcmri-btn wpcmri-btn-secondary wpcmri-btn-sm">
									<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download Schema', 'wpcalibrate-marketplace-review-importer' ); ?>
								</a>
							</div>
						</div>
						<div class="wpcmri-dropzone" style="margin-bottom:16px;">
							<span class="dashicons dashicons-media-code wpcmri-dropzone-icon"></span>
							<h4><?php esc_html_e( 'Upload a JSON file', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
							<input type="file" name="json_file" id="json_file" accept=".json,application/json" class="wpcmri-file-input" />
						</div>
						<div class="wpcmri-form-group">
							<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
								<label for="raw_json" class="wpcmri-form-label" style="margin-bottom:0;"><?php esc_html_e( 'Or Paste JSON Content Directly', 'wpcalibrate-marketplace-review-importer' ); ?></label>
								<button type="button" class="wpcmri-btn wpcmri-btn-secondary wpcmri-btn-sm" id="wpcmri-load-demo-json-btn">
									<span class="dashicons dashicons-database-add"></span> <?php esc_html_e( 'Load 15 Demo Reviews into Box', 'wpcalibrate-marketplace-review-importer' ); ?>
								</button>
							</div>
							<textarea name="raw_json" id="raw_json" rows="6" class="wpcmri-textarea wpcmri-code" placeholder="<?php esc_attr_e( 'Paste UTF-8 JSON review array or schema here, or click Load 15 Demo Reviews above...', 'wpcalibrate-marketplace-review-importer' ); ?>"></textarea>
						</div>
					</div>

					<!-- SerpApi Panel -->
					<div id="wpcmri-panel-serpapi" class="wpcmri-source-panel" style="display:none;">
						<div class="wpcmri-panel-heading">
							<h3><?php esc_html_e( 'Amazon Product Lookup via SerpApi', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
						</div>
						<div class="wpcmri-grid-2">
							<div class="wpcmri-form-group">
								<label for="amazon_source" class="wpcmri-form-label"><?php esc_html_e( 'Amazon Product URL or ASIN', 'wpcalibrate-marketplace-review-importer' ); ?> <span class="wpcmri-required">*</span></label>
								<input type="text" name="amazon_source" id="amazon_source" class="wpcmri-input" placeholder="e.g. B08N5WRWNW or https://www.amazon.com/dp/B08N5WRWNW" />
								<p class="wpcmri-form-hint"><?php esc_html_e( 'Accepts any standard Amazon product URL or 10-character ASIN.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
							</div>
							<div class="wpcmri-form-group">
								<label for="amazon_marketplace" class="wpcmri-form-label"><?php esc_html_e( 'Amazon Marketplace Domain', 'wpcalibrate-marketplace-review-importer' ); ?></label>
								<select name="amazon_marketplace" id="amazon_marketplace" class="wpcmri-select">
									<?php foreach ( $marketplaces as $domain => $label ) : ?>
										<option value="<?php echo esc_attr( $domain ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="wpcmri-form-group" style="max-width:200px;">
							<label for="serpapi_page" class="wpcmri-form-label"><?php esc_html_e( 'Page Number', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<input type="number" name="serpapi_page" id="serpapi_page" value="1" min="1" max="100" class="wpcmri-input" />
						</div>
					</div>
				</div>

				<!-- Review Filters -->
				<div class="wpcmri-filters-card">
					<h3><?php esc_html_e( 'Pre-Import Review Filters', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
					<p class="wpcmri-form-hint"><?php esc_html_e( 'Refine which reviews should be imported. By default, all star ratings are included to preserve honest buyer sentiment.', 'wpcalibrate-marketplace-review-importer' ); ?></p>

					<div class="wpcmri-grid-3">
						<div class="wpcmri-form-group">
							<label class="wpcmri-form-label"><?php esc_html_e( 'Star Rating Range', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<div class="wpcmri-inline-selects">
								<select name="filter_min_rating" id="filter_min_rating" class="wpcmri-select wpcmri-select-sm">
									<option value="1">Min: 1 Star</option>
									<option value="2">Min: 2 Stars</option>
									<option value="3">Min: 3 Stars</option>
									<option value="4">Min: 4 Stars</option>
									<option value="5">Min: 5 Stars</option>
								</select>
								<span class="wpcmri-to"><?php esc_html_e( 'to', 'wpcalibrate-marketplace-review-importer' ); ?></span>
								<select name="filter_max_rating" id="filter_max_rating" class="wpcmri-select wpcmri-select-sm">
									<option value="5" selected>Max: 5 Stars</option>
									<option value="4">Max: 4 Stars</option>
									<option value="3">Max: 3 Stars</option>
									<option value="2">Max: 2 Stars</option>
									<option value="1">Max: 1 Star</option>
								</select>
							</div>
						</div>

						<div class="wpcmri-form-group">
							<label for="filter_min_length" class="wpcmri-form-label"><?php esc_html_e( 'Minimum Text Length', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<div class="wpcmri-input-suffix">
								<input type="number" name="filter_min_length" id="filter_min_length" value="0" min="0" class="wpcmri-input" />
								<span class="wpcmri-suffix"><?php esc_html_e( 'characters', 'wpcalibrate-marketplace-review-importer' ); ?></span>
							</div>
						</div>

						<div class="wpcmri-form-group">
							<label class="wpcmri-form-label"><?php esc_html_e( 'Special Criteria', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<div class="wpcmri-checkbox-list">
								<label class="wpcmri-check-label">
									<input type="checkbox" name="filter_verified_only" value="1" />
									<span><?php esc_html_e( 'Verified purchase at source only', 'wpcalibrate-marketplace-review-importer' ); ?></span>
								</label>
								<label class="wpcmri-check-label">
									<input type="checkbox" name="filter_media_only" value="1" />
									<span><?php esc_html_e( 'With customer photos/media only', 'wpcalibrate-marketplace-review-importer' ); ?></span>
								</label>
							</div>
						</div>
					</div>
				</div>

				<div class="wpcmri-submit-row">
					<button type="submit" class="wpcmri-btn wpcmri-btn-primary wpcmri-btn-lg">
						<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Preview & Validate Reviews', 'wpcalibrate-marketplace-review-importer' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Stage 2: Preview parsed reviews, statistics, duplicates, and confirm button.
	 *
	 * @return void
	 */
	private static function render_preview_stage(): void {
		if ( ! Permissions::verify_nonce( 'wpcmri_preview_action', 'wpcmri_nonce' ) ) {
			AdminNotices::add_flash( __( 'Security check failed. Please try again.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
			self::render_source_selection_stage();
			return;
		}

		$product_id = isset( $_POST['target_product_id'] ) ? (int) $_POST['target_product_id'] : 0;
		if ( $product_id <= 0 ) {
			AdminNotices::add_flash( __( 'Please select a valid destination WooCommerce product.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
			self::render_source_selection_stage();
			return;
		}

		$source_type = sanitize_key( (string) ( $_POST['source_type'] ?? 'csv' ) );
		$filters     = array(
			'min_rating'    => isset( $_POST['filter_min_rating'] ) ? (int) $_POST['filter_min_rating'] : 1,
			'max_rating'    => isset( $_POST['filter_max_rating'] ) ? (int) $_POST['filter_max_rating'] : 5,
			'min_length'    => isset( $_POST['filter_min_length'] ) ? (int) $_POST['filter_min_length'] : 0,
			'verified_only' => ! empty( $_POST['filter_verified_only'] ),
			'media_only'    => ! empty( $_POST['filter_media_only'] ),
		);

		$parsed_result = null;
		$meta          = array(
			'product_id'      => $product_id,
			'source_type'     => $source_type,
			'filter_criteria' => $filters,
		);

		if ( 'csv' === $source_type ) {
			if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
				AdminNotices::add_flash( __( 'Please upload a CSV file.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
				self::render_source_selection_stage();
				return;
			}

			$upload = UploadHandler::handle_upload( $_FILES['csv_file'], array( 'csv' ) );
			if ( ! $upload['success'] ) {
				AdminNotices::add_flash( $upload['error'], 'error' );
				self::render_source_selection_stage();
				return;
			}

			$headers       = CsvImporter::get_headers( $upload['path'] );
			$mapping       = CsvImporter::guess_mapping( $headers );
			$parsed_result = CsvImporter::parse_and_preview( $upload['path'], $mapping, $product_id, $filters );
			if ( file_exists( $upload['path'] ) ) {
				unlink( $upload['path'] );
			}
		} elseif ( 'json' === $source_type ) {
			$json_content = '';
			if ( ! empty( $_FILES['json_file']['tmp_name'] ) ) {
				$upload = UploadHandler::handle_upload( $_FILES['json_file'], array( 'json' ) );
				if ( ! $upload['success'] ) {
					AdminNotices::add_flash( $upload['error'], 'error' );
					self::render_source_selection_stage();
					return;
				}
				$json_content = (string) file_get_contents( $upload['path'] );
				if ( file_exists( $upload['path'] ) ) {
					unlink( $upload['path'] );
				}
			} elseif ( ! empty( $_POST['raw_json'] ) ) {
				$json_content = wp_unslash( (string) $_POST['raw_json'] );
			}

			if ( empty( trim( $json_content ) ) ) {
				AdminNotices::add_flash( __( 'Please provide a JSON file or paste JSON content.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
				self::render_source_selection_stage();
				return;
			}

			$parsed_result = JsonImporter::parse_and_preview( $json_content, $product_id, $filters );
		} elseif ( 'serpapi' === $source_type ) {
			$raw_source  = trim( (string) ( $_POST['amazon_source'] ?? '' ) );
			$marketplace = trim( (string) ( $_POST['amazon_marketplace'] ?? 'amazon.com' ) );
			$page        = max( 1, (int) ( $_POST['serpapi_page'] ?? 1 ) );

			$parsed_source = AmazonSourceParser::parse( $raw_source, $marketplace );
			if ( ! $parsed_source['valid'] ) {
				AdminNotices::add_flash( $parsed_source['error'], 'error' );
				self::render_source_selection_stage();
				return;
			}

			$provider = ProviderRegistry::get_instance()->get_provider( 'serpapi' );
			if ( ! $provider || ! $provider->is_configured() ) {
				AdminNotices::add_flash( __( 'SerpApi provider is not configured.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
				self::render_source_selection_stage();
				return;
			}

			$fetch_result = $provider->fetch_reviews( $parsed_source['asin'], $parsed_source['marketplace'], $page );
			if ( ! $fetch_result['success'] ) {
				AdminNotices::add_flash( $fetch_result['error'], 'error' );
				self::render_source_selection_stage();
				return;
			}

			$meta['asin']        = $parsed_source['asin'];
			$meta['marketplace'] = $parsed_source['marketplace'];
			$meta['provider_id'] = 'serpapi';

			$filtered_reviews = array();
			$duplicates_count = 0;
			$rating_breakdown = array( 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 );
			$dates            = array();
			$samples          = array();

			foreach ( $fetch_result['reviews'] as $rev ) {
				if ( ! \WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewValidator::passes_filters( $rev, $filters ) ) {
					continue;
				}

				$rev = $rev->with_content_hash( $product_id );
				$dup = \WPCalibrate\MarketplaceReviewImporter\Domain\Review\DuplicateChecker::find_duplicate_comment_id( $product_id, $rev );
				if ( $dup > 0 ) {
					++$duplicates_count;
				}

				$filtered_reviews[] = $rev;
				if ( isset( $rating_breakdown[ $rev->rating ] ) ) {
					++$rating_breakdown[ $rev->rating ];
				}
				if ( ! empty( $rev->original_review_date ) ) {
					$dates[] = $rev->original_review_date;
				}
				if ( count( $samples ) < 5 ) {
					$samples[] = $rev->to_array();
				}
			}

			$parsed_result = array(
				'success'          => true,
				'total_found'      => count( $fetch_result['reviews'] ),
				'valid_count'      => count( $filtered_reviews ),
				'invalid_count'    => 0,
				'duplicate_count'  => $duplicates_count,
				'rating_breakdown' => $rating_breakdown,
				'date_range'       => array(
					'from' => ! empty( $dates ) ? min( $dates ) : '',
					'to'   => ! empty( $dates ) ? max( $dates ) : '',
				),
				'samples'          => $samples,
				'reviews'          => $filtered_reviews,
			);
		}

		if ( ! $parsed_result || ! $parsed_result['success'] ) {
			AdminNotices::add_flash( $parsed_result['error'] ?? __( 'Failed to parse review data.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
			self::render_source_selection_stage();
			return;
		}

		$session_token = wp_generate_password( 20, false, false );
		set_transient( 'wpcmri_preview_' . $session_token, array(
			'product_id'  => $product_id,
			'source_type' => $source_type,
			'meta'        => $meta,
			'reviews'     => $parsed_result['reviews'],
		), 3600 );

		$product = get_post( $product_id );
		?>
		<div class="wpcmri-section-card">
			<div class="wpcmri-section-header">
				<div>
					<h2><?php esc_html_e( 'Step 2: Preview & Deduplication Summary', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
					<p class="wpcmri-section-desc">
						<?php
						printf(
							/* translators: 1: product title, 2: product ID */
							esc_html__( 'Target Destination Product: %1$s (ID: #%2$d)', 'wpcalibrate-marketplace-review-importer' ),
							'<strong>' . esc_html( $product->post_title ?? '' ) . '</strong>',
							(int) $product_id
						);
						?>
					</p>
				</div>
			</div>

			<!-- Metrics Dashboard Grid -->
			<div class="wpcmri-metrics-grid">
				<div class="wpcmri-metric-card wpcmri-metric-primary">
					<div class="wpcmri-metric-top">
						<span class="wpcmri-metric-label"><?php esc_html_e( 'Discovered', 'wpcalibrate-marketplace-review-importer' ); ?></span>
						<span class="dashicons dashicons-search wpcmri-metric-icon"></span>
					</div>
					<div class="wpcmri-metric-value"><?php echo (int) $parsed_result['total_found']; ?></div>
					<div class="wpcmri-metric-footer"><?php esc_html_e( 'Total rows in dataset', 'wpcalibrate-marketplace-review-importer' ); ?></div>
				</div>

				<div class="wpcmri-metric-card wpcmri-metric-success">
					<div class="wpcmri-metric-top">
						<span class="wpcmri-metric-label"><?php esc_html_e( 'Ready to Import', 'wpcalibrate-marketplace-review-importer' ); ?></span>
						<span class="dashicons dashicons-yes-alt wpcmri-metric-icon"></span>
					</div>
					<div class="wpcmri-metric-value"><?php echo (int) $parsed_result['valid_count']; ?></div>
					<div class="wpcmri-metric-footer"><?php esc_html_e( 'Passed validation & filters', 'wpcalibrate-marketplace-review-importer' ); ?></div>
				</div>

				<div class="wpcmri-metric-card wpcmri-metric-warning">
					<div class="wpcmri-metric-top">
						<span class="wpcmri-metric-label"><?php esc_html_e( 'Existing Duplicates', 'wpcalibrate-marketplace-review-importer' ); ?></span>
						<span class="dashicons dashicons-warning wpcmri-metric-icon"></span>
					</div>
					<div class="wpcmri-metric-value"><?php echo (int) $parsed_result['duplicate_count']; ?></div>
					<div class="wpcmri-metric-footer"><?php esc_html_e( 'Safely identified & skipped', 'wpcalibrate-marketplace-review-importer' ); ?></div>
				</div>

				<div class="wpcmri-metric-card wpcmri-metric-danger">
					<div class="wpcmri-metric-top">
						<span class="wpcmri-metric-label"><?php esc_html_e( 'Invalid Records', 'wpcalibrate-marketplace-review-importer' ); ?></span>
						<span class="dashicons dashicons-dismiss wpcmri-metric-icon"></span>
					</div>
					<div class="wpcmri-metric-value"><?php echo (int) $parsed_result['invalid_count']; ?></div>
					<div class="wpcmri-metric-footer"><?php esc_html_e( 'Missing required rating or text', 'wpcalibrate-marketplace-review-importer' ); ?></div>
				</div>
			</div>

			<!-- Star Rating Breakdown Visualizer -->
			<div class="wpcmri-preview-card">
				<h3><?php esc_html_e( 'Star Rating Breakdown in this Batch', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
				<div class="wpcmri-rating-bars">
					<?php for ( $stars = 5; $stars >= 1; $stars-- ) : ?>
						<?php
						$count   = $parsed_result['rating_breakdown'][ $stars ] ?? 0;
						$percent = $parsed_result['valid_count'] > 0 ? round( ( $count / $parsed_result['valid_count'] ) * 100 ) : 0;
						?>
						<div class="wpcmri-rating-row">
							<span class="wpcmri-stars-label"><?php echo esc_html( str_repeat( '★', $stars ) ); ?></span>
							<div class="wpcmri-bar-track">
								<div class="wpcmri-bar-fill" style="width: <?php echo (int) $percent; ?>%;"></div>
							</div>
							<span class="wpcmri-count-label"><strong><?php echo (int) $count; ?></strong> (<?php echo (int) $percent; ?>%)</span>
						</div>
					<?php endfor; ?>
				</div>
			</div>

			<!-- Sample Records Table -->
			<?php if ( ! empty( $parsed_result['samples'] ) ) : ?>
				<div class="wpcmri-preview-card">
					<h3><?php esc_html_e( 'Sample Reviews Preview (First 5 records)', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
					<div class="wpcmri-table-wrapper">
						<table class="wpcmri-table">
							<thead>
								<tr>
									<th style="width: 100px;"><?php esc_html_e( 'Rating', 'wpcalibrate-marketplace-review-importer' ); ?></th>
									<th style="width: 160px;"><?php esc_html_e( 'Reviewer', 'wpcalibrate-marketplace-review-importer' ); ?></th>
									<th><?php esc_html_e( 'Review Title & Content', 'wpcalibrate-marketplace-review-importer' ); ?></th>
									<th style="width: 120px;"><?php esc_html_e( 'Date', 'wpcalibrate-marketplace-review-importer' ); ?></th>
									<th style="width: 110px;"><?php esc_html_e( 'Verification', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $parsed_result['samples'] as $sample ) : ?>
									<tr>
										<td>
											<span class="wpcmri-star-badge"><?php echo esc_html( str_repeat( '★', (int) $sample['rating'] ) ); ?></span>
										</td>
										<td>
											<strong><?php echo esc_html( $sample['reviewer_display_name'] ); ?></strong>
										</td>
										<td>
											<?php if ( ! empty( $sample['review_title'] ) ) : ?>
												<div class="wpcmri-sample-title"><?php echo esc_html( $sample['review_title'] ); ?></div>
											<?php endif; ?>
											<div class="wpcmri-sample-body"><?php echo esc_html( wp_trim_words( $sample['review_body'], 25 ) ); ?></div>
										</td>
										<td>
											<span class="wpcmri-date-text"><?php echo esc_html( substr( (string) $sample['original_review_date'], 0, 10 ) ); ?></span>
										</td>
										<td>
											<?php if ( ! empty( $sample['source_verified_purchase'] ) ) : ?>
												<span class="wpcmri-pill wpcmri-pill-success"><?php esc_html_e( 'Verified Source', 'wpcalibrate-marketplace-review-importer' ); ?></span>
											<?php else : ?>
												<span class="wpcmri-pill wpcmri-pill-neutral"><?php esc_html_e( 'Standard', 'wpcalibrate-marketplace-review-importer' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>

			<form method="post" class="wpcmri-confirm-form">
				<?php wp_nonce_field( 'wpcmri_confirm_action', 'wpcmri_confirm_nonce' ); ?>
				<input type="hidden" name="wpcmri_stage" value="confirm" />
				<input type="hidden" name="wpcmri_session_token" value="<?php echo esc_attr( $session_token ); ?>" />

				<div class="wpcmri-submit-row">
					<button type="submit" class="wpcmri-btn wpcmri-btn-primary wpcmri-btn-lg">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php
						printf(
							/* translators: %d: count to import */
							esc_html__( 'Confirm & Import %d Reviews Now', 'wpcalibrate-marketplace-review-importer' ),
							(int) $parsed_result['valid_count']
						);
						?>
					</button>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'import' ), admin_url( 'admin.php' ) ) ); ?>" class="wpcmri-btn wpcmri-btn-secondary">
						<?php esc_html_e( 'Cancel', 'wpcalibrate-marketplace-review-importer' ); ?>
					</a>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Stage 3: Confirm execution and trigger import.
	 *
	 * @return void
	 */
	private static function execute_import_submission(): void {
		if ( ! Permissions::verify_nonce( 'wpcmri_confirm_action', 'wpcmri_confirm_nonce' ) ) {
			AdminNotices::add_flash( __( 'Security token expired. Please try again.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
			self::render_source_selection_stage();
			return;
		}

		$session_token = sanitize_key( (string) ( $_POST['wpcmri_session_token'] ?? '' ) );
		$session_data  = get_transient( 'wpcmri_preview_' . $session_token );

		if ( ! is_array( $session_data ) || empty( $session_data['reviews'] ) ) {
			AdminNotices::add_flash( __( 'Import session expired. Please preview your file again.', 'wpcalibrate-marketplace-review-importer' ), 'error' );
			self::render_source_selection_stage();
			return;
		}

		delete_transient( 'wpcmri_preview_' . $session_token );

		$product_id  = (int) $session_data['product_id'];
		$source_type = (string) $session_data['source_type'];
		$meta        = (array) $session_data['meta'];
		$reviews     = (array) $session_data['reviews'];

		$result = ImportService::start_import( $product_id, $source_type, $reviews, $meta );

		if ( ! $result['success'] ) {
			AdminNotices::add_flash( $result['message'], 'error' );
			self::render_source_selection_stage();
			return;
		}

		$product_edit_url    = get_edit_post_link( $product_id );
		$product_view_url    = get_permalink( $product_id );
		$product_reviews_url = admin_url( 'edit-comments.php?post_type=product&p=' . $product_id );
		$history_url         = add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'history' ), admin_url( 'admin.php' ) );

		?>
		<div class="wpcmri-section-card wpcmri-success-card">
			<div class="wpcmri-success-icon-wrap">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<h2><?php esc_html_e( 'Review Import Initiated Successfully!', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
			<p class="wpcmri-lead-text"><?php echo esc_html( $result['message'] ); ?></p>

			<div class="wpcmri-action-box">
				<h4><?php esc_html_e( 'Manage & View Reviews:', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
				<div class="wpcmri-links-group">
					<a href="<?php echo esc_url( $product_reviews_url ); ?>" class="wpcmri-btn wpcmri-btn-secondary">
						<span class="dashicons dashicons-admin-comments"></span> <?php esc_html_e( 'Moderate Reviews in WordPress Comments', 'wpcalibrate-marketplace-review-importer' ); ?>
					</a>
					<?php if ( $product_edit_url ) : ?>
						<a href="<?php echo esc_url( $product_edit_url ); ?>" class="wpcmri-btn wpcmri-btn-secondary">
							<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit WooCommerce Product', 'wpcalibrate-marketplace-review-importer' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $product_view_url ) : ?>
						<a href="<?php echo esc_url( $product_view_url ); ?>" class="wpcmri-btn wpcmri-btn-secondary" target="_blank" rel="noopener noreferrer">
							<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'View Product on Storefront', 'wpcalibrate-marketplace-review-importer' ); ?>
						</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $history_url ); ?>" class="wpcmri-btn wpcmri-btn-primary">
						<span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'View Import History & Progress', 'wpcalibrate-marketplace-review-importer' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Tab 2: Import History.
	 *
	 * @return void
	 */
	private static function render_tab_history(): void {
		$page    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$limit   = 20;
		$offset  = ( $page - 1 ) * $limit;
		$history = ImportRepository::get_paginated( $limit, $offset );

		?>
		<div class="wpcmri-section-card">
			<div class="wpcmri-section-header">
				<div>
					<h2><?php esc_html_e( 'Import Audit History & Execution Logs', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
					<p class="wpcmri-section-desc"><?php esc_html_e( 'Review past import jobs, duplicate detection metrics, and background processing status.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>
			</div>

			<?php if ( empty( $history['items'] ) ) : ?>
				<div class="wpcmri-empty-state">
					<span class="dashicons dashicons-backup wpcmri-empty-icon"></span>
					<h3><?php esc_html_e( 'No Import Jobs Recorded Yet', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
					<p><?php esc_html_e( 'Your past imports and background batch logs will automatically appear here once initiated.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => Menu::PAGE_SLUG, 'tab' => 'import' ), admin_url( 'admin.php' ) ) ); ?>" class="wpcmri-btn wpcmri-btn-primary">
						<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Create Your First Import', 'wpcalibrate-marketplace-review-importer' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="wpcmri-table-wrapper">
					<table class="wpcmri-table">
						<thead>
							<tr>
								<th style="width: 140px;"><?php esc_html_e( 'Import ID', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<th style="width: 130px;"><?php esc_html_e( 'Date & Time', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<th><?php esc_html_e( 'Destination Product', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Source', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Discovered', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Imported', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Duplicates', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Failed', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<th style="width: 120px;"><?php esc_html_e( 'Status', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Actions', 'wpcalibrate-marketplace-review-importer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $history['items'] as $item ) : ?>
								<?php
								$product = get_post( (int) $item['product_id'] );
								$p_title = $product ? $product->post_title : sprintf( '#%d (Deleted)', (int) $item['product_id'] );
								$status_pill = match ( $item['status'] ) {
									'completed'             => 'wpcmri-pill-success',
									'completed_with_errors' => 'wpcmri-pill-warning',
									'processing', 'queued'  => 'wpcmri-pill-info',
									'failed'                => 'wpcmri-pill-danger',
									default                 => 'wpcmri-pill-neutral',
								};
								?>
								<tr>
									<td><code class="wpcmri-code-inline"><?php echo esc_html( $item['import_id'] ); ?></code></td>
									<td><span class="wpcmri-date-text"><?php echo esc_html( $item['created_at'] ); ?></span></td>
									<td>
										<strong>
											<a href="<?php echo esc_url( admin_url( 'edit-comments.php?post_type=product&p=' . (int) $item['product_id'] ) ); ?>" class="wpcmri-product-link">
												<?php echo esc_html( $p_title ); ?>
											</a>
										</strong>
										<?php if ( ! empty( $item['asin'] ) ) : ?>
											<span class="wpcmri-asin-tag"><?php echo esc_html( 'ASIN: ' . $item['asin'] ); ?></span>
										<?php endif; ?>
									</td>
									<td><span class="wpcmri-mini-badge"><?php echo esc_html( strtoupper( (string) $item['source_type'] ) ); ?></span></td>
									<td><?php echo (int) $item['total_discovered']; ?></td>
									<td><strong style="color:#059669;"><?php echo (int) $item['imported_count']; ?></strong></td>
									<td><?php echo (int) $item['duplicate_count']; ?></td>
									<td>
										<?php if ( (int) $item['failed_count'] > 0 ) : ?>
											<span style="color:#e11d48;font-weight:700;"><?php echo (int) $item['failed_count']; ?></span>
										<?php else : ?>
											0
										<?php endif; ?>
									</td>
									<td>
										<span class="wpcmri-pill <?php echo esc_attr( $status_pill ); ?>">
											<?php echo esc_html( str_replace( '_', ' ', (string) $item['status'] ) ); ?>
										</span>
									</td>
									<td>
										<?php if ( in_array( $item['status'], array( 'processing', 'queued' ), true ) ) : ?>
											<?php
											$cancel_url = wp_nonce_url(
												add_query_arg(
													array(
														'action'    => 'cancel_import',
														'import_id' => $item['import_id'],
													),
													admin_url( 'admin.php?page=' . Menu::PAGE_SLUG . '&tab=history' )
												),
												'wpcmri_cancel_import_' . $item['import_id']
											);
											?>
											<a href="<?php echo esc_url( $cancel_url ); ?>" class="wpcmri-btn wpcmri-btn-danger wpcmri-btn-sm" onclick="return confirm('<?php esc_attr_e( 'Cancel this active background import job?', 'wpcalibrate-marketplace-review-importer' ); ?>');">
												<?php esc_html_e( 'Cancel', 'wpcalibrate-marketplace-review-importer' ); ?>
											</a>
										<?php else : ?>
											<a href="<?php echo esc_url( admin_url( 'edit-comments.php?post_type=product&p=' . (int) $item['product_id'] ) ); ?>" class="wpcmri-btn wpcmri-btn-secondary wpcmri-btn-sm">
												<?php esc_html_e( 'Reviews', 'wpcalibrate-marketplace-review-importer' ); ?>
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php
				$total_pages = ceil( $history['total'] / $limit );
				if ( $total_pages > 1 ) {
					echo '<div class="wpcmri-pagination">';
					echo paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo; Previous',
							'next_text' => 'Next &raquo;',
							'total'     => $total_pages,
							'current'   => $page,
						)
					);
					echo '</div>';
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Tab 3: Providers.
	 *
	 * @return void
	 */
	private static function render_tab_providers(): void {
		$serp_enabled = 'yes' === SettingsRepository::get( 'wpcmri_provider_serpapi_enabled', 'no' );
		$stored_key   = (string) SettingsRepository::get( 'wpcmri_provider_serpapi_api_key', '' );
		$masked_key   = SettingsRepository::mask_secret( $stored_key );

		?>
		<div class="wpcmri-section-card">
			<div class="wpcmri-section-header">
				<div>
					<h2><?php esc_html_e( 'External Review Data Providers', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
					<p class="wpcmri-section-desc"><?php esc_html_e( 'Manage optional third-party integrations for automated marketplace review discovery.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>
			</div>

			<!-- Disclosure Notice Card -->
			<div class="wpcmri-alert-box wpcmri-alert-info">
				<span class="dashicons dashicons-shield wpcmri-alert-icon"></span>
				<div>
					<h4><?php esc_html_e( 'Third-Party Service Disclosure & Privacy Notice', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
					<p>
						<?php
						esc_html_e(
							'SerpApi is an optional external service. When enabled and triggered by an administrator, lookup queries are sent to SerpApi (serpapi.com) containing only the target ASIN and domain using your own private API key. Standard CSV and JSON importing function 100% offline without any external account. SerpApi terms, pricing, and quotas belong to SerpApi, LLC.',
							'wpcalibrate-marketplace-review-importer'
						);
						?>
					</p>
				</div>
			</div>

			<form method="post" class="wpcmri-form">
				<?php wp_nonce_field( 'wpcmri_save_provider_action' ); ?>
				<input type="hidden" name="wpcmri_save_provider_settings" value="1" />

				<div class="wpcmri-provider-card">
					<div class="wpcmri-provider-header">
						<div class="wpcmri-provider-brand">
							<span class="dashicons dashicons-networking wpcmri-provider-icon"></span>
							<div>
								<h3><?php esc_html_e( 'SerpApi (Amazon Product Reviews)', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
								<p class="wpcmri-form-hint"><?php esc_html_e( 'Direct, legitimate Amazon reviews API using documented search endpoints.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
							</div>
						</div>
						<div class="wpcmri-provider-status">
							<?php if ( $serp_enabled && ! empty( $stored_key ) ) : ?>
								<span class="wpcmri-pill wpcmri-pill-success"><?php esc_html_e( 'Configured & Enabled', 'wpcalibrate-marketplace-review-importer' ); ?></span>
							<?php else : ?>
								<span class="wpcmri-pill wpcmri-pill-neutral"><?php esc_html_e( 'Disabled', 'wpcalibrate-marketplace-review-importer' ); ?></span>
							<?php endif; ?>
						</div>
					</div>

					<div class="wpcmri-provider-fields">
						<div class="wpcmri-form-group">
							<label class="wpcmri-check-label wpcmri-toggle-label">
								<input type="checkbox" name="wpcmri_provider_serpapi_enabled" value="1" <?php checked( $serp_enabled ); ?> />
								<strong><?php esc_html_e( 'Enable SerpApi Provider for Amazon review imports', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
							</label>
						</div>

						<div class="wpcmri-form-group">
							<label for="wpcmri_provider_serpapi_api_key" class="wpcmri-form-label">
								<?php esc_html_e( 'Private SerpApi Secret Key', 'wpcalibrate-marketplace-review-importer' ); ?>
							</label>
							<div class="wpcmri-password-wrap">
								<input type="password" name="wpcmri_provider_serpapi_api_key" id="wpcmri_provider_serpapi_api_key" value="<?php echo esc_attr( $masked_key ); ?>" class="wpcmri-input" placeholder="<?php esc_attr_e( 'Paste your secret SerpApi key...', 'wpcalibrate-marketplace-review-importer' ); ?>" autocomplete="off" />
							</div>
							<p class="wpcmri-form-hint">
								<?php
								printf(
									/* translators: %s: external link */
									esc_html__( 'Keys are masked and never logged. Obtain or review your key at %s.', 'wpcalibrate-marketplace-review-importer' ),
									'<a href="https://serpapi.com/" target="_blank" rel="noopener noreferrer" class="wpcmri-link">SerpApi.com</a>'
								);
								?>
							</p>
						</div>
					</div>
				</div>

				<div class="wpcmri-submit-row">
					<button type="submit" class="wpcmri-btn wpcmri-btn-primary">
						<?php esc_html_e( 'Save Provider Settings', 'wpcalibrate-marketplace-review-importer' ); ?>
					</button>
					<button type="submit" name="test_connection" value="1" class="wpcmri-btn wpcmri-btn-secondary">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Test Connection to SerpApi', 'wpcalibrate-marketplace-review-importer' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Tab 4: Settings.
	 *
	 * @return void
	 */
	private static function render_tab_settings(): void {
		$status       = (string) SettingsRepository::get( 'wpcmri_default_status', '1' );
		$max_reviews  = (int) SettingsRepository::get( 'wpcmri_max_reviews_per_import', 100 );
		$batch_size   = (int) SettingsRepository::get( 'wpcmri_batch_size', 20 );
		$retention_d  = (int) SettingsRepository::get( 'wpcmri_history_retention_days', 60 );
		$retention_h  = (int) SettingsRepository::get( 'wpcmri_temp_retention_hours', 24 );
		$titles       = 'yes' === SettingsRepository::get( 'wpcmri_store_review_titles', 'yes' );
		$urls         = 'yes' === SettingsRepository::get( 'wpcmri_store_source_urls', 'yes' );
		$logging      = 'yes' === SettingsRepository::get( 'wpcmri_enable_logging', 'yes' );
		$cleanup      = (string) SettingsRepository::get( 'wpcmri_uninstall_cleanup', 'keep' );
		$dup_behavior = (string) SettingsRepository::get( 'wpcmri_duplicate_behavior', 'skip' );
		$default_mkt  = (string) SettingsRepository::get( 'wpcmri_default_marketplace', 'amazon.com' );

		$frontend_styling = 'yes' === SettingsRepository::get( 'wpcmri_enable_frontend_styling', 'yes' );
		$show_summary     = 'yes' === SettingsRepository::get( 'wpcmri_show_review_summary', 'yes' );
		$show_badge       = 'yes' === SettingsRepository::get( 'wpcmri_show_marketplace_badge', 'yes' );
		$show_media       = 'yes' === SettingsRepository::get( 'wpcmri_show_review_media', 'yes' );
		$show_helpful     = 'yes' === SettingsRepository::get( 'wpcmri_show_helpful_votes', 'yes' );

		$marketplaces = AmazonSourceParser::get_supported_marketplaces();

		?>
		<div class="wpcmri-section-card">
			<div class="wpcmri-section-header">
				<div>
					<h2><?php esc_html_e( 'Plugin Configuration & Defaults', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
					<p class="wpcmri-section-desc"><?php esc_html_e( 'Fine-tune review moderation, deduplication behavior, batch performance, and retention rules.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>
			</div>

			<form method="post" class="wpcmri-form">
				<?php wp_nonce_field( 'wpcmri_save_settings_action' ); ?>
				<input type="hidden" name="wpcmri_save_settings" value="1" />

				<!-- Group 1: Review Moderation & Storage -->
				<div class="wpcmri-settings-group">
					<h3><?php esc_html_e( 'Moderation & Metadata Settings', 'wpcalibrate-marketplace-review-importer' ); ?></h3>

					<div class="wpcmri-grid-2">
						<div class="wpcmri-form-group">
							<label for="wpcmri_default_status" class="wpcmri-form-label"><?php esc_html_e( 'Default Moderation Status', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<select name="wpcmri_default_status" id="wpcmri_default_status" class="wpcmri-select">
								<option value="1" <?php selected( $status, '1' ); ?>><?php esc_html_e( 'Approved (Visible immediately on storefront)', 'wpcalibrate-marketplace-review-importer' ); ?></option>
								<option value="0" <?php selected( $status, '0' ); ?>><?php esc_html_e( 'Hold for Moderation (Pending admin review)', 'wpcalibrate-marketplace-review-importer' ); ?></option>
							</select>
							<p class="wpcmri-form-hint"><?php esc_html_e( 'Controls whether imported comments publish immediately or require manual moderation.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>

						<div class="wpcmri-form-group">
							<label for="wpcmri_duplicate_behavior" class="wpcmri-form-label"><?php esc_html_e( 'Duplicate Handling Behavior', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<select name="wpcmri_duplicate_behavior" id="wpcmri_duplicate_behavior" class="wpcmri-select">
								<option value="skip" <?php selected( $dup_behavior, 'skip' ); ?>><?php esc_html_e( 'Skip Duplicates (Leave existing comment unchanged)', 'wpcalibrate-marketplace-review-importer' ); ?></option>
								<option value="update_meta" <?php selected( $dup_behavior, 'update_meta' ); ?>><?php esc_html_e( 'Update Source Metadata Only (Preserves existing review text)', 'wpcalibrate-marketplace-review-importer' ); ?></option>
							</select>
							<p class="wpcmri-form-hint"><?php esc_html_e( 'Locally written WooCommerce reviews are never overwritten.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>
					</div>

					<div class="wpcmri-form-group">
						<label class="wpcmri-form-label"><?php esc_html_e( 'Additional Metadata Storage', 'wpcalibrate-marketplace-review-importer' ); ?></label>
						<div class="wpcmri-checkbox-list">
							<label class="wpcmri-check-label">
								<input type="checkbox" name="wpcmri_store_review_titles" value="1" <?php checked( $titles ); ?> />
								<span><?php esc_html_e( 'Store review titles in comment meta (_wpcmri_review_title)', 'wpcalibrate-marketplace-review-importer' ); ?></span>
							</label>
							<label class="wpcmri-check-label">
								<input type="checkbox" name="wpcmri_store_source_urls" value="1" <?php checked( $urls ); ?> />
								<span><?php esc_html_e( 'Store source review URLs in comment meta (_wpcmri_source_url)', 'wpcalibrate-marketplace-review-importer' ); ?></span>
							</label>
						</div>
					</div>
				</div>

				<!-- Group 2: Storefront & Frontend Review Display -->
				<div class="wpcmri-settings-group">
					<h3><?php esc_html_e( 'Storefront & Reviews Presentation', 'wpcalibrate-marketplace-review-importer' ); ?></h3>

					<div class="wpcmri-form-group">
						<label class="wpcmri-check-label wpcmri-toggle-label">
							<input type="checkbox" name="wpcmri_enable_frontend_styling" value="1" <?php checked( $frontend_styling ); ?> />
							<strong><?php esc_html_e( 'Enable Modern Frontend Reviews & Review Form Styling', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
						</label>
						<p class="wpcmri-form-hint"><?php esc_html_e( 'Styles the single-product review list, review cards, rating breakdown bar, and review submission form with a sleek contemporary aesthetic.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
					</div>

					<div class="wpcmri-checkbox-list">
						<label class="wpcmri-check-label">
							<input type="checkbox" name="wpcmri_show_review_summary" value="1" <?php checked( $show_summary ); ?> />
							<span><?php esc_html_e( 'Display Customer Reviews & Ratings Summary Card (Average score, stars, and 1-5 star distribution bars)', 'wpcalibrate-marketplace-review-importer' ); ?></span>
						</label>

						<label class="wpcmri-check-label">
							<input type="checkbox" name="wpcmri_show_marketplace_badge" value="1" <?php checked( $show_badge ); ?> />
							<span><?php esc_html_e( 'Display "Verified Amazon Purchase" / "Verified Marketplace Purchase" badge in review author metadata', 'wpcalibrate-marketplace-review-importer' ); ?></span>
						</label>

						<label class="wpcmri-check-label">
							<input type="checkbox" name="wpcmri_show_review_media" value="1" <?php checked( $show_media ); ?> />
							<span><?php esc_html_e( 'Display Customer Review Photos gallery when review images are present', 'wpcalibrate-marketplace-review-importer' ); ?></span>
						</label>

						<label class="wpcmri-check-label">
							<input type="checkbox" name="wpcmri_show_helpful_votes" value="1" <?php checked( $show_helpful ); ?> />
							<span><?php esc_html_e( 'Display Helpful Votes count tag and marketplace attribution link on review items', 'wpcalibrate-marketplace-review-importer' ); ?></span>
						</label>
					</div>
				</div>

				<!-- Group 3: Performance & Limits -->
				<div class="wpcmri-settings-group">
					<h3><?php esc_html_e( 'Performance & Batch Limits', 'wpcalibrate-marketplace-review-importer' ); ?></h3>

					<div class="wpcmri-grid-3">
						<div class="wpcmri-form-group">
							<label for="wpcmri_batch_size" class="wpcmri-form-label"><?php esc_html_e( 'Background Batch Size', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<input type="number" name="wpcmri_batch_size" id="wpcmri_batch_size" value="<?php echo esc_attr( (string) $batch_size ); ?>" min="5" max="200" class="wpcmri-input" />
							<p class="wpcmri-form-hint"><?php esc_html_e( 'Reviews processed per batch iteration.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>

						<div class="wpcmri-form-group">
							<label for="wpcmri_max_reviews_per_import" class="wpcmri-form-label"><?php esc_html_e( 'Default Maximum Per Import', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<input type="number" name="wpcmri_max_reviews_per_import" id="wpcmri_max_reviews_per_import" value="<?php echo esc_attr( (string) $max_reviews ); ?>" min="1" max="5000" class="wpcmri-input" />
						</div>

						<div class="wpcmri-form-group">
							<label for="wpcmri_default_marketplace" class="wpcmri-form-label"><?php esc_html_e( 'Default Marketplace', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<select name="wpcmri_default_marketplace" id="wpcmri_default_marketplace" class="wpcmri-select">
								<?php foreach ( $marketplaces as $domain => $lbl ) : ?>
									<option value="<?php echo esc_attr( $domain ); ?>" <?php selected( $default_mkt, $domain ); ?>><?php echo esc_html( $lbl ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<!-- Group 3: Retention & Diagnostics -->
				<div class="wpcmri-settings-group">
					<h3><?php esc_html_e( 'Data Retention & Uninstall Behavior', 'wpcalibrate-marketplace-review-importer' ); ?></h3>

					<div class="wpcmri-grid-2">
						<div class="wpcmri-form-group">
							<label for="wpcmri_history_retention_days" class="wpcmri-form-label"><?php esc_html_e( 'History Retention (Days)', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<input type="number" name="wpcmri_history_retention_days" id="wpcmri_history_retention_days" value="<?php echo esc_attr( (string) $retention_d ); ?>" min="0" max="365" class="wpcmri-input" />
							<p class="wpcmri-form-hint"><?php esc_html_e( '0 keeps import history records indefinitely.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
						</div>

						<div class="wpcmri-form-group">
							<label for="wpcmri_temp_retention_hours" class="wpcmri-form-label"><?php esc_html_e( 'Temp File Retention (Hours)', 'wpcalibrate-marketplace-review-importer' ); ?></label>
							<input type="number" name="wpcmri_temp_retention_hours" id="wpcmri_temp_retention_hours" value="<?php echo esc_attr( (string) $retention_h ); ?>" min="1" max="168" class="wpcmri-input" />
						</div>
					</div>

					<div class="wpcmri-form-group">
						<label class="wpcmri-check-label">
							<input type="checkbox" name="wpcmri_enable_logging" value="1" <?php checked( $logging ); ?> />
							<strong><?php esc_html_e( 'Enable sanitized diagnostic logging (writes to WooCommerce Logs or error_log)', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
						</label>
					</div>

					<div class="wpcmri-form-group">
						<label class="wpcmri-form-label"><?php esc_html_e( 'Uninstall Data Retention Policy', 'wpcalibrate-marketplace-review-importer' ); ?></label>
						<div class="wpcmri-radio-group">
							<label class="wpcmri-radio-card">
								<input type="radio" name="wpcmri_uninstall_cleanup" value="keep" <?php checked( 'keep', $cleanup ); ?> />
								<div>
									<strong><?php esc_html_e( 'Keep all reviews & configuration (Recommended)', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
									<p class="wpcmri-form-hint"><?php esc_html_e( 'Preserves imported reviews, history, and options if the plugin is deleted.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
								</div>
							</label>

							<label class="wpcmri-radio-card">
								<input type="radio" name="wpcmri_uninstall_cleanup" value="delete" <?php checked( 'delete', $cleanup ); ?> />
								<div>
									<strong style="color:#e11d48;"><?php esc_html_e( 'Delete plugin settings and import history on uninstall', 'wpcalibrate-marketplace-review-importer' ); ?></strong>
									<p class="wpcmri-form-hint"><?php esc_html_e( 'Imported customer reviews remain in your WooCommerce database as site content.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
								</div>
							</label>
						</div>
					</div>
				</div>

				<div class="wpcmri-submit-row">
					<button type="submit" class="wpcmri-btn wpcmri-btn-primary">
						<?php esc_html_e( 'Save All Settings', 'wpcalibrate-marketplace-review-importer' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Tab 5: License.
	 *
	 * @return void
	 */
	private static function render_tab_license(): void {
		$license_key = (string) SettingsRepository::get( 'wpcmri_license_key', '' );
		$masked_key  = SettingsRepository::mask_secret( $license_key );

		?>
		<div class="wpcmri-section-card">
			<div class="wpcmri-section-header">
				<div>
					<h2><?php esc_html_e( 'WPCalibrate License Management', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
					<p class="wpcmri-section-desc"><?php esc_html_e( 'Manage your extension license key and marketplace connection.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>
			</div>

			<div class="wpcmri-alert-box wpcmri-alert-success">
				<span class="dashicons dashicons-yes-alt wpcmri-alert-icon"></span>
				<div>
					<h4><?php esc_html_e( 'Free Community Edition Active', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
					<p>
						<?php
						esc_html_e(
							'All core functionality — including CSV review importing, JSON schema review importing, and SerpApi integration with your own key — is 100% free and unrestricted. Remote license management is prepared for future WPCalibrate enterprise services.',
							'wpcalibrate-marketplace-review-importer'
						);
						?>
					</p>
				</div>
			</div>

			<form method="post" class="wpcmri-form">
				<?php wp_nonce_field( 'wpcmri_save_license_action' ); ?>
				<input type="hidden" name="wpcmri_save_license" value="1" />

				<div class="wpcmri-form-group">
					<label class="wpcmri-form-label"><?php esc_html_e( 'License Status', 'wpcalibrate-marketplace-review-importer' ); ?></label>
					<div>
						<span class="wpcmri-pill wpcmri-pill-success"><?php esc_html_e( 'Community Edition (Active & Unrestricted)', 'wpcalibrate-marketplace-review-importer' ); ?></span>
					</div>
				</div>

				<div class="wpcmri-form-group">
					<label for="wpcmri_license_key" class="wpcmri-form-label"><?php esc_html_e( 'License Key', 'wpcalibrate-marketplace-review-importer' ); ?></label>
					<input type="password" name="wpcmri_license_key" id="wpcmri_license_key" value="<?php echo esc_attr( $masked_key ); ?>" class="wpcmri-input" placeholder="<?php esc_attr_e( 'Optional license key...', 'wpcalibrate-marketplace-review-importer' ); ?>" autocomplete="off" />
					<p class="wpcmri-form-hint"><?php esc_html_e( 'Prepared interface for future WPCalibrate license server integration.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>

				<div class="wpcmri-submit-row">
					<button type="submit" class="wpcmri-btn wpcmri-btn-primary"><?php esc_html_e( 'Save License Key', 'wpcalibrate-marketplace-review-importer' ); ?></button>
					<a href="https://marketplace.wpcalibrate.com/" target="_blank" rel="noopener noreferrer" class="wpcmri-btn wpcmri-btn-secondary">
						<span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Visit WPCalibrate Marketplace', 'wpcalibrate-marketplace-review-importer' ); ?>
					</a>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Tab 6: Support.
	 *
	 * @return void
	 */
	private static function render_tab_support(): void {
		global $wp_version;

		$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Not Installed / Inactive', 'wpcalibrate-marketplace-review-importer' );
		$php_ver    = PHP_VERSION;
		$upload_dir = UploadHandler::get_upload_dir();
		$writable   = is_writable( $upload_dir );
		$as_active  = ActionSchedulerWorker::is_available();

		?>
		<div class="wpcmri-section-card">
			<div class="wpcmri-section-header">
				<div>
					<h2><?php esc_html_e( 'WPCalibrate Support & Contact', 'wpcalibrate-marketplace-review-importer' ); ?></h2>
					<p class="wpcmri-section-desc"><?php esc_html_e( 'Get expert documentation, technical assistance, and commercial support directly from the WPCalibrate engineering team.', 'wpcalibrate-marketplace-review-importer' ); ?></p>
				</div>
			</div>

			<!-- Contact Cards Grid -->
			<div class="wpcmri-contact-grid">
				<a href="mailto:support@wpcalibrate.com" class="wpcmri-contact-card">
					<div class="wpcmri-contact-icon" style="background:#eff6ff;color:#2563eb;">
						<span class="dashicons dashicons-email-alt"></span>
					</div>
					<h4><?php esc_html_e( 'Email Support', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
					<span class="wpcmri-contact-val">support@wpcalibrate.com</span>
					<span class="wpcmri-contact-action"><?php esc_html_e( 'Send message &rarr;', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>

				<a href="https://wpcalibrate.com" target="_blank" rel="noopener noreferrer" class="wpcmri-contact-card">
					<div class="wpcmri-contact-icon" style="background:#ecfdf5;color:#059669;">
						<span class="dashicons dashicons-admin-site-alt3"></span>
					</div>
					<h4><?php esc_html_e( 'Official Website', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
					<span class="wpcmri-contact-val">wpcalibrate.com</span>
					<span class="wpcmri-contact-action"><?php esc_html_e( 'Visit website &rarr;', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>

				<a href="https://marketplace.wpcalibrate.com/" target="_blank" rel="noopener noreferrer" class="wpcmri-contact-card">
					<div class="wpcmri-contact-icon" style="background:#fdf4ff;color:#9333ea;">
						<span class="dashicons dashicons-cart"></span>
					</div>
					<h4><?php esc_html_e( 'Plugin Marketplace', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
					<span class="wpcmri-contact-val">marketplace.wpcalibrate.com</span>
					<span class="wpcmri-contact-action"><?php esc_html_e( 'Browse plugins &rarr;', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>

				<a href="https://wa.me/447474795976" target="_blank" rel="noopener noreferrer" class="wpcmri-contact-card">
					<div class="wpcmri-contact-icon" style="background:#fff7ed;color:#ea580c;">
						<span class="dashicons dashicons-phone"></span>
					</div>
					<h4><?php esc_html_e( 'Phone & WhatsApp', 'wpcalibrate-marketplace-review-importer' ); ?></h4>
					<span class="wpcmri-contact-val">+447474795976</span>
					<span class="wpcmri-contact-action"><?php esc_html_e( 'Chat on WhatsApp &rarr;', 'wpcalibrate-marketplace-review-importer' ); ?></span>
				</a>
			</div>

			<!-- System Audit Table -->
			<div class="wpcmri-system-audit-wrap">
				<h3><?php esc_html_e( 'Environment & System Diagnostics', 'wpcalibrate-marketplace-review-importer' ); ?></h3>
				<div class="wpcmri-table-wrapper">
					<table class="wpcmri-table wpcmri-audit-table">
						<tbody>
							<tr>
								<th><?php esc_html_e( 'Plugin Version', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<td><code class="wpcmri-code-inline"><?php echo esc_html( WPCMRI_VERSION ); ?></code></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'WordPress Core Version', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<td><code class="wpcmri-code-inline"><?php echo esc_html( $wp_version ); ?></code></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'WooCommerce Version', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<td><code class="wpcmri-code-inline"><?php echo esc_html( $wc_version ); ?></code></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'PHP Runtime Version', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<td><code class="wpcmri-code-inline"><?php echo esc_html( $php_ver ); ?></code></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Action Scheduler Queue', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<td>
									<?php if ( $as_active ) : ?>
										<span class="wpcmri-pill wpcmri-pill-success"><?php esc_html_e( 'Active & Available', 'wpcalibrate-marketplace-review-importer' ); ?></span>
									<?php else : ?>
										<span class="wpcmri-pill wpcmri-pill-warning"><?php esc_html_e( 'Not Loaded (Small imports will run synchronously)', 'wpcalibrate-marketplace-review-importer' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Temporary Upload Directory', 'wpcalibrate-marketplace-review-importer' ); ?></th>
								<td>
									<?php if ( $writable ) : ?>
										<span class="wpcmri-pill wpcmri-pill-success"><?php esc_html_e( 'Writable', 'wpcalibrate-marketplace-review-importer' ); ?></span>
									<?php else : ?>
										<span class="wpcmri-pill wpcmri-pill-danger"><?php esc_html_e( 'Not Writable', 'wpcalibrate-marketplace-review-importer' ); ?></span>
									<?php endif; ?>
									<br /><span class="wpcmri-form-hint"><?php echo esc_html( $upload_dir ); ?></span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output demo dataset file as a direct browser download.
	 *
	 * @param string $type Format type: 'csv' or 'json'.
	 * @return void
	 */
	private static function output_demo_file( string $type ): void {
		if ( ! Permissions::can_manage_reviews() ) {
			wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-marketplace-review-importer' ) );
		}

		$file_path = 'csv' === $type
			? WPCMRI_PATH . 'assets/demo/demo-reviews.csv'
			: WPCMRI_PATH . 'assets/demo/demo-reviews.json';

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			wp_die( esc_html__( 'Demo file not found.', 'wpcalibrate-marketplace-review-importer' ) );
		}

		$filename = 'csv' === $type ? 'wpcalibrate-demo-reviews.csv' : 'wpcalibrate-demo-reviews.json';
		$mime     = 'csv' === $type ? 'text/csv; charset=utf-8' : 'application/json; charset=utf-8';

		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'Content-Length: ' . (string) filesize( $file_path ) );

		readfile( $file_path );
		exit;
	}
}
