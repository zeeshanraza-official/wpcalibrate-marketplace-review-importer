<?php
/**
 * Frontend Marketplace Reviews Presentation and Layout Engine.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Frontend
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Frontend;

use WPCalibrate\MarketplaceReviewImporter\Admin\Settings\SettingsRepository;
use WP_Comment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReviewDisplay
 */
final class ReviewDisplay {

	/**
	 * Initialize frontend review hooks and filters.
	 *
	 * @return void
	 */
	public static function init(): void {
		$enabled = 'yes' === SettingsRepository::get( 'wpcmri_enable_frontend_styling', 'yes' );
		if ( ! $enabled ) {
			return;
		}

		// 1. Enqueue frontend styles on singular product screens.
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );

		// 2. Render Review Summary Breakdown at top of review list.
		if ( 'yes' === SettingsRepository::get( 'wpcmri_show_review_summary', 'yes' ) ) {
			add_action( 'woocommerce_product_review_list_before', array( self::class, 'render_review_summary' ), 5 );
		}

		// 3. Enhance review content (Review Title, Photo Gallery, Helpful Votes).
		add_filter( 'comment_text', array( self::class, 'enhance_review_comment_text' ), 10, 2 );

		// 4. Inject Verified Marketplace badge into WooCommerce review meta.
		if ( 'yes' === SettingsRepository::get( 'wpcmri_show_marketplace_badge', 'yes' ) ) {
			add_action( 'woocommerce_review_meta', array( self::class, 'render_verified_marketplace_badge' ), 15, 1 );
		}

		// 5. Fallback letter-initials avatar for imported reviews without Gravatar.
		add_filter( 'get_avatar', array( self::class, 'filter_review_avatar' ), 10, 5 );
	}

	/**
	 * Enqueue frontend CSS on single product pages.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'wpcmri-frontend-css',
			WPCMRI_URL . 'assets/css/frontend.css',
			array(),
			WPCMRI_VERSION
		);

		if ( is_rtl() ) {
			wp_enqueue_style(
				'wpcmri-frontend-rtl-css',
				WPCMRI_URL . 'assets/css/frontend-rtl.css',
				array( 'wpcmri-frontend-css' ),
				WPCMRI_VERSION
			);
		}
	}

	/**
	 * Render Customer Reviews Breakdown Card (Average score, star distribution bars).
	 *
	 * @return void
	 */
	public static function render_review_summary(): void {
		global $product;
		if ( ! $product || ! method_exists( $product, 'get_id' ) ) {
			$product_id = get_the_ID();
			$product    = function_exists( 'wc_get_product' ) && $product_id ? wc_get_product( $product_id ) : null;
		}

		if ( ! $product ) {
			return;
		}

		$review_count = (int) $product->get_review_count();
		if ( $review_count <= 0 ) {
			return;
		}

		$avg_rating    = (float) $product->get_average_rating();
		$rating_counts = method_exists( $product, 'get_rating_counts' ) ? (array) $product->get_rating_counts() : array();

		?>
		<div class="wpcmri-reviews-summary-card">
			<div class="wpcmri-summary-header">
				<h3 class="wpcmri-summary-title">
					<?php esc_html_e( 'Customer Reviews & Ratings', 'wpcalibrate-marketplace-review-importer' ); ?>
				</h3>
				<span class="wpcmri-verified-source-pill">
					<span class="wpcmri-pill-check">&#10003;</span>
					<?php esc_html_e( 'Includes Marketplace Verified Reviews', 'wpcalibrate-marketplace-review-importer' ); ?>
				</span>
			</div>

			<div class="wpcmri-summary-grid">
				<!-- Overall Score Column -->
				<div class="wpcmri-score-col">
					<div class="wpcmri-score-number"><?php echo esc_html( number_format( $avg_rating, 1 ) ); ?></div>
					<div class="wpcmri-stars-display" aria-label="<?php echo esc_attr( sprintf( __( 'Rated %s out of 5', 'wpcalibrate-marketplace-review-importer' ), number_format( $avg_rating, 1 ) ) ); ?>">
						<?php self::render_star_rating_html( $avg_rating ); ?>
					</div>
					<div class="wpcmri-score-count">
						<?php
						printf(
							/* translators: %s: number of reviews */
							esc_html( _n( 'Based on %s customer review', 'Based on %s customer reviews', $review_count, 'wpcalibrate-marketplace-review-importer' ) ),
							'<strong>' . esc_html( number_format_i18n( $review_count ) ) . '</strong>'
						);
						?>
					</div>
					<div class="wpcmri-score-footnote">
						<?php esc_html_e( 'Combined authentic customer feedback from Amazon and verified purchasers.', 'wpcalibrate-marketplace-review-importer' ); ?>
					</div>
				</div>

				<!-- Rating Distribution Bars Column -->
				<div class="wpcmri-bars-col">
					<?php
					for ( $star = 5; $star >= 1; $star-- ) :
						$count   = isset( $rating_counts[ $star ] ) ? (int) $rating_counts[ $star ] : 0;
						$percent = $review_count > 0 ? round( ( $count / $review_count ) * 100 ) : 0;
						?>
						<div class="wpcmri-bar-row">
							<span class="wpcmri-bar-stars">
								<?php echo esc_html( (string) $star ); ?> <span class="wpcmri-star-symbol">&#9733;</span>
							</span>
							<div class="wpcmri-bar-track">
								<div class="wpcmri-bar-fill" style="width: <?php echo esc_attr( (string) $percent ); ?>%;"></div>
							</div>
							<span class="wpcmri-bar-count">
								<?php echo esc_html( (string) $percent ); ?>% (<?php echo esc_html( (string) $count ); ?>)
							</span>
						</div>
					<?php endfor; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enhance review comment text with review title, media gallery, and helpful votes.
	 *
	 * @param string          $comment_text Original comment text.
	 * @param WP_Comment|null $comment Comment object.
	 * @return string Enhanced HTML output.
	 */
	public static function enhance_review_comment_text( string $comment_text, ?WP_Comment $comment = null ): string {
		if ( ! $comment || 'review' !== $comment->comment_type ) {
			return $comment_text;
		}

		$output = '';

		// 1. Review Title (Prepend).
		$show_titles  = 'yes' === SettingsRepository::get( 'wpcmri_store_review_titles', 'yes' );
		$review_title = get_comment_meta( (int) $comment->comment_ID, '_wpcmri_review_title', true );
		if ( $show_titles && ! empty( $review_title ) ) {
			$output .= sprintf(
				'<h4 class="wpcmri-review-item-title">%s</h4>',
				esc_html( (string) $review_title )
			);
		}

		// 2. Original Comment Body.
		$output .= '<div class="wpcmri-review-item-content">' . $comment_text . '</div>';

		// 3. Review Image Media Gallery (Append).
		$show_media = 'yes' === SettingsRepository::get( 'wpcmri_show_review_media', 'yes' );
		$media_urls = get_comment_meta( (int) $comment->comment_ID, '_wpcmri_media_urls', true );
		if ( $show_media && ! empty( $media_urls ) && is_array( $media_urls ) ) {
			$output .= '<div class="wpcmri-review-gallery">';
			foreach ( $media_urls as $img_url ) {
				$clean_url = esc_url( (string) $img_url );
				if ( ! empty( $clean_url ) ) {
					$output .= sprintf(
						'<a href="%1$s" target="_blank" rel="noopener noreferrer" class="wpcmri-gallery-thumb-link"><img src="%1$s" alt="%2$s" class="wpcmri-gallery-thumb" loading="lazy" /></a>',
						$clean_url,
						esc_attr__( 'Customer Review Photo', 'wpcalibrate-marketplace-review-importer' )
					);
				}
			}
			$output .= '</div>';
		}

		// 4. Helpful Votes & Provenance Footer (Append).
		$show_helpful  = 'yes' === SettingsRepository::get( 'wpcmri_show_helpful_votes', 'yes' );
		$helpful_votes = (int) get_comment_meta( (int) $comment->comment_ID, '_wpcmri_helpful_votes', true );
		$marketplace   = get_comment_meta( (int) $comment->comment_ID, '_wpcmri_marketplace', true );
		$source_url    = get_comment_meta( (int) $comment->comment_ID, '_wpcmri_source_url', true );

		if ( $show_helpful && ( $helpful_votes > 0 || ! empty( $marketplace ) ) ) {
			$output .= '<div class="wpcmri-review-item-footer">';
			if ( $helpful_votes > 0 ) {
				$output .= sprintf(
					'<span class="wpcmri-helpful-tag"><span class="wpcmri-thumbs-up">&#128077;</span> %s</span>',
					sprintf(
						/* translators: %d: number of helpful votes */
						esc_html( _n( '%d person found this helpful', '%d people found this helpful', $helpful_votes, 'wpcalibrate-marketplace-review-importer' ) ),
						$helpful_votes
					)
				);
			}

			if ( ! empty( $marketplace ) ) {
				if ( ! empty( $source_url ) ) {
					$output .= sprintf(
						'<a href="%s" target="_blank" rel="nofollow noopener noreferrer" class="wpcmri-source-link">%s</a>',
						esc_url( (string) $source_url ),
						sprintf(
							/* translators: %s: marketplace domain */
							esc_html__( 'Review on %s &rarr;', 'wpcalibrate-marketplace-review-importer' ),
							esc_html( ucfirst( (string) $marketplace ) )
						)
					);
				} else {
					$output .= sprintf(
						'<span class="wpcmri-source-note">%s</span>',
						sprintf(
							/* translators: %s: marketplace domain */
							esc_html__( 'Source: %s', 'wpcalibrate-marketplace-review-importer' ),
							esc_html( ucfirst( (string) $marketplace ) )
						)
					);
				}
			}
			$output .= '</div>';
		}

		return $output;
	}

	/**
	 * Render Verified Marketplace badge in WooCommerce review metadata header.
	 *
	 * @param WP_Comment $comment WordPress comment object.
	 * @return void
	 */
	public static function render_verified_marketplace_badge( WP_Comment $comment ): void {
		if ( 'review' !== $comment->comment_type ) {
			return;
		}

		$is_verified = '1' === (string) get_comment_meta( (int) $comment->comment_ID, '_wpcmri_source_verified_purchase', true );
		$marketplace = (string) get_comment_meta( (int) $comment->comment_ID, '_wpcmri_marketplace', true );
		$is_imported = 'marketplace' === (string) get_comment_meta( (int) $comment->comment_ID, '_wpcmri_source', true );

		if ( ! $is_imported ) {
			return;
		}

		if ( $is_verified ) {
			$badge_label = ! empty( $marketplace ) && str_contains( strtolower( $marketplace ), 'amazon' )
				? __( 'Verified Amazon Purchase', 'wpcalibrate-marketplace-review-importer' )
				: __( 'Verified Marketplace Purchase', 'wpcalibrate-marketplace-review-importer' );

			echo '<span class="wpcmri-badge-verified-buyer" title="' . esc_attr__( 'Verified customer purchase from original marketplace.', 'wpcalibrate-marketplace-review-importer' ) . '">';
			echo '<span class="wpcmri-badge-icon">&#10003;</span> ' . esc_html( $badge_label );
			echo '</span>';
		} else {
			echo '<span class="wpcmri-badge-marketplace-buyer">';
			echo esc_html__( 'Marketplace Review', 'wpcalibrate-marketplace-review-importer' );
			echo '</span>';
		}
	}

	/**
	 * Filter avatar to render a sleek letter-initials badge for imported reviews if Gravatar is missing.
	 *
	 * @param string          $avatar Original avatar HTML.
	 * @param mixed           $id_or_email Identifier or comment object.
	 * @param int             $size Avatar dimensions.
	 * @param string          $default Default image URL.
	 * @param string          $alt Alternative text.
	 * @return string Filtered avatar HTML.
	 */
	public static function filter_review_avatar( string $avatar, mixed $id_or_email, int $size, string $default, string $alt ): string {
		if ( ! ( $id_or_email instanceof WP_Comment ) || 'review' !== $id_or_email->comment_type ) {
			return $avatar;
		}

		$is_imported = 'marketplace' === (string) get_comment_meta( (int) $id_or_email->comment_ID, '_wpcmri_source', true );
		if ( ! $is_imported ) {
			return $avatar;
		}

		// Check if avatar output contains default mystery man placeholder.
		if ( str_contains( $avatar, 'mystery' ) || str_contains( $avatar, 'mysteryman' ) || str_contains( $avatar, 'mm' ) || empty( $id_or_email->comment_author_email ) ) {
			$author_name = trim( (string) $id_or_email->comment_author );
			$initials    = self::get_initials( $author_name );
			$bg_color    = self::get_author_avatar_color( $author_name );

			return sprintf(
				'<span class="wpcmri-initials-avatar" style="width:%1$dpx;height:%1$dpx;line-height:%1$dpx;background-color:%2$s;" aria-hidden="true">%3$s</span>',
				max( 36, min( 60, $size ) ),
				esc_attr( $bg_color ),
				esc_html( $initials )
			);
		}

		return $avatar;
	}

	/**
	 * Compute 1-2 letter initials from author name.
	 *
	 * @param string $name Author name.
	 * @return string Initials.
	 */
	private static function get_initials( string $name ): string {
		$clean = trim( preg_replace( '/[^\p{L}\p{N}\s]/u', '', $name ) );
		if ( empty( $clean ) ) {
			return 'V';
		}

		$words = preg_split( '/\s+/', $clean );
		if ( false === $words || empty( $words ) ) {
			return strtoupper( substr( $clean, 0, 1 ) );
		}

		if ( count( $words ) >= 2 ) {
			return strtoupper( mb_substr( $words[0], 0, 1 ) . mb_substr( $words[1], 0, 1 ) );
		}

		return strtoupper( mb_substr( $words[0], 0, 1 ) );
	}

	/**
	 * Generate pleasant consistent HSL/hex background color for initials badge.
	 *
	 * @param string $name Author name.
	 * @return string Hex color string.
	 */
	private static function get_author_avatar_color( string $name ): string {
		$palette = array(
			'#2563eb', // Blue
			'#059669', // Emerald
			'#d97706', // Amber
			'#7c3aed', // Violet
			'#db2777', // Pink
			'#0891b2', // Cyan
			'#4f46e5', // Indigo
			'#ea580c', // Orange
		);

		$hash = crc32( $name );
		$idx  = abs( $hash ) % count( $palette );

		return $palette[ $idx ];
	}

	/**
	 * Render 5 golden star icons according to rating.
	 *
	 * @param float $rating Rating out of 5.
	 * @return void
	 */
	private static function render_star_rating_html( float $rating ): void {
		$clamped = max( 0.0, min( 5.0, $rating ) );
		for ( $i = 1; $i <= 5; $i++ ) {
			if ( $clamped >= $i ) {
				echo '<span class="wpcmri-star wpcmri-star-filled">&#9733;</span>';
			} elseif ( $clamped >= ( $i - 0.5 ) ) {
				echo '<span class="wpcmri-star wpcmri-star-half">&#9733;</span>';
			} else {
				echo '<span class="wpcmri-star wpcmri-star-empty">&#9734;</span>';
			}
		}
	}
}
