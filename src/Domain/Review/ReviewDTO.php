<?php
/**
 * Review Data Transfer Object (DTO).
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Domain\Review
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Domain\Review;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReviewDTO
 */
final class ReviewDTO {

	/**
	 * Constructor.
	 *
	 * @param string        $provider Source provider identifier (e.g. 'csv', 'json', 'serpapi').
	 * @param string        $source_review_id Identifier from external marketplace.
	 * @param string        $source_product_id ASIN or external product ID.
	 * @param string        $marketplace Marketplace host or code (e.g. 'amazon.com').
	 * @param string        $source_url Direct URL to original review or product page.
	 * @param string        $reviewer_display_name Name of reviewer.
	 * @param string        $review_title Title or summary of review.
	 * @param string        $review_body Body content of review.
	 * @param int           $rating Integer rating from 1 to 5.
	 * @param string        $original_review_date Date of review at source (Y-m-d H:i:s or Y-m-d).
	 * @param string        $imported_at Timestamp when imported (Y-m-d H:i:s).
	 * @param bool          $source_verified_purchase Verified purchase flag from source.
	 * @param int           $helpful_votes Number of helpful votes.
	 * @param array<string> $media_urls Associated media image URLs if available.
	 * @param string        $source_language Source language code if provided.
	 * @param string        $content_hash Normalized fingerprint hash.
	 */
	public function __construct(
		public readonly string $provider,
		public readonly string $source_review_id,
		public readonly string $source_product_id,
		public readonly string $marketplace,
		public readonly string $source_url,
		public readonly string $reviewer_display_name,
		public readonly string $review_title,
		public readonly string $review_body,
		public readonly int $rating,
		public readonly string $original_review_date,
		public readonly string $imported_at,
		public readonly bool $source_verified_purchase,
		public readonly int $helpful_votes,
		public readonly array $media_urls = array(),
		public readonly string $source_language = '',
		public readonly string $content_hash = '',
	) {}

	/**
	 * Compute normalized content hash for fallback duplicate detection.
	 *
	 * @param int    $target_product_id Destination WooCommerce product ID.
	 * @param string $reviewer_name Display name.
	 * @param string $review_date Review date.
	 * @param int    $rating Star rating.
	 * @param string $review_body Review text.
	 * @return string
	 */
	public static function generate_content_hash(
		int $target_product_id,
		string $reviewer_name,
		string $review_date,
		int $rating,
		string $review_body
	): string {
		$normalized_name = strtolower( trim( $reviewer_name ) );
		$normalized_date = trim( $review_date );
		$normalized_body = preg_replace( '/\s+/', ' ', strtolower( trim( $review_body ) ) );

		$raw = sprintf(
			'%d|%s|%s|%d|%s',
			$target_product_id,
			$normalized_name,
			$normalized_date,
			$rating,
			$normalized_body
		);

		return hash( 'sha256', $raw );
	}

	/**
	 * Convert DTO to array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'provider'                 => $this->provider,
			'source_review_id'         => $this->source_review_id,
			'source_product_id'        => $this->source_product_id,
			'marketplace'              => $this->marketplace,
			'source_url'               => $this->source_url,
			'reviewer_display_name'    => $this->reviewer_display_name,
			'review_title'             => $this->review_title,
			'review_body'              => $this->review_body,
			'rating'                   => $this->rating,
			'original_review_date'     => $this->original_review_date,
			'imported_at'              => $this->imported_at,
			'source_verified_purchase' => $this->source_verified_purchase,
			'helpful_votes'            => $this->helpful_votes,
			'media_urls'               => $this->media_urls,
			'source_language'          => $this->source_language,
			'content_hash'             => $this->content_hash,
		);
	}

	/**
	 * Recreate DTO with a computed content hash if not already set.
	 *
	 * @param int $target_product_id WooCommerce product ID.
	 * @return self
	 */
	public function with_content_hash( int $target_product_id ): self {
		if ( ! empty( $this->content_hash ) ) {
			return $this;
		}

		$hash = self::generate_content_hash(
			$target_product_id,
			$this->reviewer_display_name,
			$this->original_review_date,
			$this->rating,
			$this->review_body
		);

		return new self(
			provider: $this->provider,
			source_review_id: $this->source_review_id,
			source_product_id: $this->source_product_id,
			marketplace: $this->marketplace,
			source_url: $this->source_url,
			reviewer_display_name: $this->reviewer_display_name,
			review_title: $this->review_title,
			review_body: $this->review_body,
			rating: $this->rating,
			original_review_date: $this->original_review_date,
			imported_at: $this->imported_at,
			source_verified_purchase: $this->source_verified_purchase,
			helpful_votes: $this->helpful_votes,
			media_urls: $this->media_urls,
			source_language: $this->source_language,
			content_hash: $hash
		);
	}
}
