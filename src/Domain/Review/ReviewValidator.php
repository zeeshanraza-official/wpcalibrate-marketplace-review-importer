<?php
/**
 * Review Validator and Sanitizer.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Domain\Review
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Domain\Review;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReviewValidator
 */
final class ReviewValidator {

	/**
	 * Validate and sanitize raw review data into a clean ReviewDTO.
	 *
	 * @param array<string, mixed> $raw Raw input data.
	 * @param string               $default_provider Default provider name ('csv', 'json', etc.).
	 * @return ReviewDTO|null Returns null if required data is missing/invalid.
	 */
	public static function validate_and_normalize( array $raw, string $default_provider = 'csv' ): ?ReviewDTO {
		// 1. Rating validation (required, 1-5).
		$raw_rating = $raw['rating'] ?? null;
		if ( ! is_numeric( $raw_rating ) ) {
			return null;
		}

		$rating = (int) round( (float) $raw_rating );
		if ( $rating < 1 || $rating > 5 ) {
			return null;
		}

		// 2. Review text validation (required).
		$body = isset( $raw['review_text'] ) ? (string) $raw['review_text'] : ( $raw['review_body'] ?? '' );
		$body = trim( (string) $body );
		if ( '' === $body ) {
			return null;
		}

		// 3. Reviewer display name.
		$name = isset( $raw['reviewer_name'] ) ? (string) $raw['reviewer_name'] : ( $raw['reviewer_display_name'] ?? '' );
		$name = trim( sanitize_text_field( (string) $name ) );
		if ( '' === $name ) {
			$name = __( 'Verified Customer', 'wpcalibrate-marketplace-review-importer' );
		}

		// 4. Review title.
		$title = isset( $raw['review_title'] ) ? (string) $raw['review_title'] : ( $raw['title'] ?? '' );
		$title = trim( sanitize_text_field( (string) $title ) );

		// 5. Date normalization.
		$raw_date = isset( $raw['review_date'] ) ? (string) $raw['review_date'] : ( $raw['original_review_date'] ?? '' );
		$date     = self::normalize_date( (string) $raw_date );

		// 6. Marketplace & ASIN / Source product ID.
		$marketplace = trim( sanitize_text_field( (string) ( $raw['marketplace'] ?? '' ) ) );
		$asin        = trim( sanitize_text_field( (string) ( $raw['asin'] ?? ( $raw['source_product_id'] ?? '' ) ) ) );
		$review_id   = trim( sanitize_text_field( (string) ( $raw['source_review_id'] ?? '' ) ) );

		// 7. Source URL.
		$source_url = trim( (string) ( $raw['source_url'] ?? '' ) );
		if ( ! empty( $source_url ) ) {
			$source_url = esc_url_raw( $source_url );
		}

		// 8. Verified purchase status at source.
		$verified_raw = $raw['verified_purchase_source'] ?? ( $raw['source_verified_purchase'] ?? false );
		$verified     = filter_var( $verified_raw, FILTER_VALIDATE_BOOLEAN );

		// 9. Helpful votes.
		$helpful_votes = isset( $raw['helpful_votes'] ) ? (int) $raw['helpful_votes'] : 0;
		if ( $helpful_votes < 0 ) {
			$helpful_votes = 0;
		}

		// 10. Media URLs.
		$media_urls = array();
		if ( isset( $raw['image_urls'] ) ) {
			if ( is_array( $raw['image_urls'] ) ) {
				foreach ( $raw['image_urls'] as $url ) {
					$url = esc_url_raw( trim( (string) $url ) );
					if ( ! empty( $url ) ) {
						$media_urls[] = $url;
					}
				}
			} elseif ( is_string( $raw['image_urls'] ) && '' !== trim( $raw['image_urls'] ) ) {
				$parts = explode( ',', $raw['image_urls'] );
				foreach ( $parts as $p ) {
					$url = esc_url_raw( trim( $p ) );
					if ( ! empty( $url ) ) {
						$media_urls[] = $url;
					}
				}
			}
		}

		$provider        = ! empty( $raw['provider'] ) ? sanitize_key( (string) $raw['provider'] ) : $default_provider;
		$source_language = sanitize_text_field( (string) ( $raw['source_language'] ?? '' ) );

		return new ReviewDTO(
			provider: $provider,
			source_review_id: $review_id,
			source_product_id: $asin,
			marketplace: $marketplace,
			source_url: $source_url,
			reviewer_display_name: $name,
			review_title: $title,
			review_body: $body,
			rating: $rating,
			original_review_date: $date,
			imported_at: gmdate( 'Y-m-d H:i:s' ),
			source_verified_purchase: $verified,
			helpful_votes: $helpful_votes,
			media_urls: $media_urls,
			source_language: $source_language
		);
	}

	/**
	 * Normalize various date formats to standard MySQL UTC datetime string.
	 *
	 * @param string $raw_date Input date.
	 * @return string Normalized Y-m-d H:i:s string.
	 */
	public static function normalize_date( string $raw_date ): string {
		$raw_date = trim( $raw_date );
		if ( empty( $raw_date ) ) {
			return gmdate( 'Y-m-d H:i:s' );
		}

		// Strip common Amazon date prefixes like "Reviewed in the United States on ".
		$cleaned_date = preg_replace( '/^Reviewed in .* on /i', '', $raw_date );
		$timestamp    = strtotime( (string) $cleaned_date );

		if ( false === $timestamp || $timestamp <= 0 ) {
			$timestamp = strtotime( $raw_date );
		}

		if ( false === $timestamp || $timestamp <= 0 ) {
			return gmdate( 'Y-m-d H:i:s' );
		}

		// If future timestamp by more than 1 day, cap at current time.
		if ( $timestamp > time() + 86400 ) {
			return gmdate( 'Y-m-d H:i:s' );
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Check if a ReviewDTO matches administrator configured import filters.
	 *
	 * @param ReviewDTO            $review Review object.
	 * @param array<string, mixed> $filters Filter rules.
	 * @return bool True if review passes all active filters.
	 */
	public static function passes_filters( ReviewDTO $review, array $filters ): bool {
		// Min star rating filter.
		if ( isset( $filters['min_rating'] ) && is_numeric( $filters['min_rating'] ) ) {
			if ( $review->rating < (int) $filters['min_rating'] ) {
				return false;
			}
		}

		// Max star rating filter.
		if ( isset( $filters['max_rating'] ) && is_numeric( $filters['max_rating'] ) ) {
			if ( $review->rating > (int) $filters['max_rating'] ) {
				return false;
			}
		}

		// Minimum review length filter.
		if ( ! empty( $filters['min_length'] ) && is_numeric( $filters['min_length'] ) ) {
			if ( mb_strlen( $review->review_body ) < (int) $filters['min_length'] ) {
				return false;
			}
		}

		// Verified purchase filter.
		if ( ! empty( $filters['verified_only'] ) ) {
			if ( ! $review->source_verified_purchase ) {
				return false;
			}
		}

		// Reviews with media only filter.
		if ( ! empty( $filters['media_only'] ) ) {
			if ( empty( $review->media_urls ) ) {
				return false;
			}
		}

		// Date range filters.
		if ( ! empty( $filters['date_from'] ) ) {
			$from_time = strtotime( (string) $filters['date_from'] );
			if ( false !== $from_time && strtotime( $review->original_review_date ) < $from_time ) {
				return false;
			}
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$to_time = strtotime( (string) $filters['date_to'] . ' 23:59:59' );
			if ( false !== $to_time && strtotime( $review->original_review_date ) > $to_time ) {
				return false;
			}
		}

		return true;
	}
}
