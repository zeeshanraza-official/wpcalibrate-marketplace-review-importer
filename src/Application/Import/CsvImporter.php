<?php
/**
 * CSV Import Parser, Mapping, and Preview Engine.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Application\Import
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Application\Import;

use WPCalibrate\MarketplaceReviewImporter\Domain\Review\DuplicateChecker;
use WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewDTO;
use WPCalibrate\MarketplaceReviewImporter\Domain\Review\ReviewValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CsvImporter
 */
final class CsvImporter {

	/**
	 * Canonical supported fields for mapping.
	 */
	public const SUPPORTED_FIELDS = array(
		'rating'                   => 'Rating (1-5, Required)',
		'review_text'              => 'Review Text (Required)',
		'review_title'             => 'Review Title',
		'reviewer_name'            => 'Reviewer Name',
		'review_date'              => 'Review Date',
		'source_review_id'         => 'Source Review ID',
		'source_product_id'        => 'Source Product ID / ASIN',
		'asin'                     => 'ASIN',
		'marketplace'              => 'Marketplace (e.g. amazon.com)',
		'source_url'               => 'Source URL',
		'verified_purchase_source' => 'Verified Purchase (yes/no/true/false)',
		'helpful_votes'            => 'Helpful Votes Count',
		'image_urls'               => 'Image URLs (comma-separated)',
	);

	/**
	 * Read CSV headers and return first row as columns.
	 *
	 * @param string $file_path Absolute path to CSV file.
	 * @return array<string>
	 */
	public static function get_headers( string $file_path ): array {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array();
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return array();
		}

		// Read first line and strip UTF-8 BOM if present.
		$first_row = fgetcsv( $handle );
		fclose( $handle );

		if ( ! is_array( $first_row ) ) {
			return array();
		}

		$headers = array();
		foreach ( $first_row as $index => $col ) {
			// Remove UTF-8 BOM.
			$col = preg_replace( '/^\xEF\xBB\xBF/', '', trim( (string) $col ) );
			$headers[ $index ] = $col;
		}

		return $headers;
	}

	/**
	 * Suggest auto-mapping between CSV headers and canonical fields.
	 *
	 * @param array<string> $csv_headers Column names from CSV file.
	 * @return array<string, int> Mapping from canonical field => column index.
	 */
	public static function guess_mapping( array $csv_headers ): array {
		$mapping = array();
		$lookup  = array();

		foreach ( $csv_headers as $index => $name ) {
			$normalized = strtolower( (string) preg_replace( '/[^a-z0-9]/i', '', (string) $name ) );
			$lookup[ $normalized ] = (int) $index;
		}

		$synonyms = array(
			'rating'                   => array( 'rating', 'stars', 'score', 'starrating' ),
			'review_text'              => array( 'reviewtext', 'reviewbody', 'text', 'body', 'content', 'reviewcontent', 'comment' ),
			'review_title'             => array( 'reviewtitle', 'title', 'headline', 'subject' ),
			'reviewer_name'            => array( 'reviewername', 'author', 'reviewer', 'name', 'authorname', 'customer' ),
			'review_date'              => array( 'reviewdate', 'date', 'createdat', 'time', 'timestamp' ),
			'source_review_id'         => array( 'sourcereviewid', 'reviewid', 'id', 'externalid' ),
			'source_product_id'        => array( 'sourceproductid', 'productid', 'asin', 'externalproductid' ),
			'asin'                     => array( 'asin', 'amazonproductid' ),
			'marketplace'              => array( 'marketplace', 'domain', 'country', 'store' ),
			'source_url'               => array( 'sourceurl', 'url', 'link', 'reviewurl' ),
			'verified_purchase_source' => array( 'verifiedpurchasesource', 'verified', 'verifiedpurchase', 'isverified' ),
			'helpful_votes'            => array( 'helpfulvotes', 'helpful', 'upvotes', 'votes' ),
			'image_urls'               => array( 'imageurls', 'images', 'photos', 'media' ),
		);

		foreach ( $synonyms as $canonical => $synonym_list ) {
			foreach ( $synonym_list as $syn ) {
				if ( isset( $lookup[ $syn ] ) ) {
					$mapping[ $canonical ] = $lookup[ $syn ];
					break;
				}
			}
		}

		return $mapping;
	}

	/**
	 * Parse CSV file into normalized ReviewDTO records and preview statistics.
	 *
	 * @param string             $file_path CSV file path.
	 * @param array<string, int> $column_mapping Map of canonical field => CSV column index.
	 * @param int                $target_product_id Target WooCommerce Product ID.
	 * @param array<string, mixed> $filters Optional filter criteria.
	 * @param int                $preview_sample_limit Limit of sample records returned.
	 * @return array<string, mixed>
	 */
	public static function parse_and_preview(
		string $file_path,
		array $column_mapping,
		int $target_product_id,
		array $filters = array(),
		int $preview_sample_limit = 5
	): array {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Cannot read CSV file.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return array(
				'success' => false,
				'error'   => __( 'Unable to open CSV file.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		// Skip header row.
		fgetcsv( $handle );

		$total_rows       = 0;
		$valid_reviews    = array();
		$invalid_count    = 0;
		$duplicate_count  = 0;
		$rating_breakdown = array(
			1 => 0,
			2 => 0,
			3 => 0,
			4 => 0,
			5 => 0,
		);
		$dates            = array();
		$samples          = array();

		$max_rows = (int) apply_filters( 'wpcmri_max_csv_rows', 5000 );

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			++$total_rows;
			if ( $total_rows > $max_rows ) {
				break;
			}

			// Map row according to column_mapping.
			$raw_item = array();
			foreach ( $column_mapping as $canonical_field => $col_index ) {
				$raw_item[ $canonical_field ] = isset( $row[ $col_index ] ) ? trim( (string) $row[ $col_index ] ) : '';
			}

			$dto = ReviewValidator::validate_and_normalize( $raw_item, 'csv' );
			if ( ! $dto ) {
				++$invalid_count;
				continue;
			}

			// Check filter pass.
			if ( ! ReviewValidator::passes_filters( $dto, $filters ) ) {
				continue;
			}

			$dto = $dto->with_content_hash( $target_product_id );

			// Check duplicates.
			if ( $target_product_id > 0 ) {
				$dup_id = DuplicateChecker::find_duplicate_comment_id( $target_product_id, $dto );
				if ( $dup_id > 0 ) {
					++$duplicate_count;
				}
			}

			$valid_reviews[] = $dto;
			if ( isset( $rating_breakdown[ $dto->rating ] ) ) {
				++$rating_breakdown[ $dto->rating ];
			}

			if ( ! empty( $dto->original_review_date ) ) {
				$dates[] = $dto->original_review_date;
			}

			if ( count( $samples ) < $preview_sample_limit ) {
				$samples[] = $dto->to_array();
			}
		}

		fclose( $handle );

		$min_date = ! empty( $dates ) ? min( $dates ) : '';
		$max_date = ! empty( $dates ) ? max( $dates ) : '';

		return array(
			'success'          => true,
			'total_found'      => $total_rows,
			'valid_count'      => count( $valid_reviews ),
			'invalid_count'    => $invalid_count,
			'duplicate_count'  => $duplicate_count,
			'rating_breakdown' => $rating_breakdown,
			'date_range'       => array(
				'from' => $min_date,
				'to'   => $max_date,
			),
			'samples'          => $samples,
			'reviews'          => $valid_reviews,
		);
	}

	/**
	 * Output a downloadable CSV template.
	 *
	 * @return void
	 */
	public static function output_template(): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wpcalibrate-reviews-template.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		// UTF-8 BOM.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Header row.
		fputcsv(
			$output,
			array(
				'rating',
				'review_text',
				'review_title',
				'reviewer_name',
				'review_date',
				'source_review_id',
				'asin',
				'marketplace',
				'source_url',
				'verified_purchase_source',
				'helpful_votes',
				'image_urls',
			)
		);

		// Sample row.
		fputcsv(
			$output,
			array(
				'5',
				'Exceptional quality and build! Exceeded all my expectations.',
				'Highly recommended product',
				'Alex Smith',
				gmdate( 'Y-m-d' ),
				'R1A2B3C4D5',
				'B08N5WRWNW',
				'amazon.com',
				'https://www.amazon.com/gp/customer-reviews/R1A2B3C4D5',
				'true',
				'12',
				'https://example.com/sample-review-image.jpg',
			)
		);

		fclose( $output );
		exit;
	}
}
