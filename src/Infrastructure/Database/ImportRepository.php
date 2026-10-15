<?php
/**
 * Import History and Jobs Database Repository.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Infrastructure\Database
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Infrastructure\Database;

use WPCalibrate\MarketplaceReviewImporter\Admin\Settings\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ImportRepository
 */
final class ImportRepository {

	/**
	 * Insert a new import job record.
	 *
	 * @param array<string, mixed> $data Job attributes.
	 * @return int Inserted ID or 0 on failure.
	 */
	public static function create( array $data ): int {
		global $wpdb;

		$table = Schema::get_imports_table();
		$now   = current_time( 'mysql', true );

		$record = array(
			'import_id'        => sanitize_key( (string) ( $data['import_id'] ?? 'imp_' . wp_generate_password( 16, false ) ) ),
			'created_at'       => $data['created_at'] ?? $now,
			'updated_at'       => $now,
			'user_id'          => (int) ( $data['user_id'] ?? get_current_user_id() ),
			'source_type'      => sanitize_key( (string) ( $data['source_type'] ?? 'csv' ) ),
			'provider_id'      => sanitize_text_field( (string) ( $data['provider_id'] ?? '' ) ),
			'product_id'       => (int) ( $data['product_id'] ?? 0 ),
			'marketplace'      => sanitize_text_field( (string) ( $data['marketplace'] ?? '' ) ),
			'asin'             => sanitize_text_field( (string) ( $data['asin'] ?? '' ) ),
			'total_discovered' => (int) ( $data['total_discovered'] ?? 0 ),
			'imported_count'   => (int) ( $data['imported_count'] ?? 0 ),
			'duplicate_count'  => (int) ( $data['duplicate_count'] ?? 0 ),
			'skipped_count'    => (int) ( $data['skipped_count'] ?? 0 ),
			'failed_count'     => (int) ( $data['failed_count'] ?? 0 ),
			'status'           => sanitize_key( (string) ( $data['status'] ?? 'pending' ) ),
			'filter_criteria'  => isset( $data['filter_criteria'] ) ? wp_json_encode( $data['filter_criteria'] ) : null,
			'error_summary'    => ! empty( $data['error_summary'] ) ? sanitize_textarea_field( (string) $data['error_summary'] ) : null,
			'job_token'        => sanitize_key( (string) ( $data['job_token'] ?? wp_generate_password( 24, false ) ) ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $record );
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Find an import job by import_id string.
	 *
	 * @param string $import_id Unique import ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_by_import_id( string $import_id ): ?array {
		global $wpdb;

		$table = Schema::get_imports_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE import_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$import_id
			),
			\ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Update an import job record.
	 *
	 * @param string               $import_id Unique import ID.
	 * @param array<string, mixed> $data Attributes to update.
	 * @return bool
	 */
	public static function update_by_import_id( string $import_id, array $data ): bool {
		global $wpdb;

		$table              = Schema::get_imports_table();
		$data['updated_at'] = current_time( 'mysql', true );

		if ( isset( $data['filter_criteria'] ) && is_array( $data['filter_criteria'] ) ) {
			$data['filter_criteria'] = wp_json_encode( $data['filter_criteria'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			$data,
			array( 'import_id' => $import_id )
		);

		return false !== $result;
	}

	/**
	 * Increment counts for a running import job.
	 *
	 * @param string $import_id Unique import ID.
	 * @param int    $imported Delta for imported.
	 * @param int    $duplicate Delta for duplicate.
	 * @param int    $skipped Delta for skipped.
	 * @param int    $failed Delta for failed.
	 * @return void
	 */
	public static function increment_counts( string $import_id, int $imported = 0, int $duplicate = 0, int $skipped = 0, int $failed = 0 ): void {
		global $wpdb;

		$table = Schema::get_imports_table();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} 
				SET imported_count = imported_count + %d,
				    duplicate_count = duplicate_count + %d,
				    skipped_count = skipped_count + %d,
				    failed_count = failed_count + %d,
				    updated_at = %s
				WHERE import_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$imported,
				$duplicate,
				$skipped,
				$failed,
				$now,
				$import_id
			)
		);
	}

	/**
	 * Get paginated list of imports.
	 *
	 * @param int    $limit Per page limit.
	 * @param int    $offset Pagination offset.
	 * @param string $orderby Column to sort by.
	 * @param string $order 'ASC' or 'DESC'.
	 * @return array{items: array<array<string, mixed>>, total: int}
	 */
	public static function get_paginated( int $limit = 20, int $offset = 0, string $orderby = 'created_at', string $order = 'DESC' ): array {
		global $wpdb;

		$table = Schema::get_imports_table();

		// Whitelist sorting parameters.
		$allowed_columns = array( 'id', 'created_at', 'status', 'product_id', 'total_discovered', 'imported_count' );
		if ( ! in_array( $orderby, $allowed_columns, true ) ) {
			$orderby = 'created_at';
		}
		$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit,
				$offset
			),
			\ARRAY_A
		);

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Delete import records older than retention threshold.
	 *
	 * @return int Number of deleted rows.
	 */
	public static function cleanup_retention(): int {
		global $wpdb;

		$retention_days = (int) SettingsRepository::get( 'wpcmri_history_retention_days', 60 );
		if ( $retention_days <= 0 ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * 86400 ) );
		$table  = Schema::get_imports_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);

		return is_numeric( $deleted ) ? (int) $deleted : 0;
	}
}
