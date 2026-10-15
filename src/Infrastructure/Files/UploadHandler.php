<?php
/**
 * Safe File Upload and Retention Handler.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Infrastructure\Files
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Infrastructure\Files;

use WPCalibrate\MarketplaceReviewImporter\Admin\Settings\SettingsRepository;
use WPCalibrate\MarketplaceReviewImporter\Infrastructure\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UploadHandler
 */
final class UploadHandler {

	/**
	 * Subfolder inside uploads directory.
	 */
	public const UPLOAD_SUBDIR = 'wpcalibrate-reviews';

	/**
	 * Get the absolute upload directory path for temporary review files.
	 *
	 * @return string Directory path with trailing slash.
	 */
	public static function get_upload_dir(): string {
		$wp_upload_dir = wp_upload_dir();
		$target_dir    = $wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR . self::UPLOAD_SUBDIR . DIRECTORY_SEPARATOR;

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );

			// Add .htaccess and index.php to prevent direct execution or browsing.
			file_put_contents( $target_dir . '.htaccess', "Deny from all\n<FilesMatch \"\.(?i:csv|json)$\">\nOrder Allow,Deny\nDeny from all\n</FilesMatch>\n" );
			file_put_contents( $target_dir . 'index.php', "<?php\n// Silence is golden.\n" );
		}

		return $target_dir;
	}

	/**
	 * Process and securely store an uploaded file.
	 *
	 * @param array<string, mixed> $file_post $_FILES['key'] array.
	 * @param array<string>        $allowed_extensions Allowed file extensions ('csv', 'json').
	 * @return array{success: bool, path: string, error: string}
	 */
	public static function handle_upload( array $file_post, array $allowed_extensions ): array {
		if ( empty( $file_post['tmp_name'] ) || ! is_uploaded_file( $file_post['tmp_name'] ) ) {
			return array(
				'success' => false,
				'path'    => '',
				'error'   => __( 'No valid uploaded file found.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		$filename = sanitize_file_name( (string) ( $file_post['name'] ?? '' ) );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed_extensions, true ) ) {
			return array(
				'success' => false,
				'path'    => '',
				'error'   => sprintf(
					/* translators: %s: allowed extensions */
					__( 'Invalid file extension. Allowed extensions: %s', 'wpcalibrate-marketplace-review-importer' ),
					implode( ', ', $allowed_extensions )
				),
			);
		}

		// Maximum allowed size filter (default 10MB).
		$max_size = (int) apply_filters( 'wpcmri_max_upload_size', 10 * 1024 * 1024 );
		if ( $file_post['size'] > $max_size ) {
			return array(
				'success' => false,
				'path'    => '',
				'error'   => __( 'File size exceeds maximum allowed upload limit.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		$target_dir  = self::get_upload_dir();
		$unique_name = wp_unique_filename( $target_dir, 'import_' . wp_generate_password( 12, false ) . '.' . $ext );
		$destination = $target_dir . $unique_name;

		if ( ! move_uploaded_file( $file_post['tmp_name'], $destination ) ) {
			return array(
				'success' => false,
				'path'    => '',
				'error'   => __( 'Failed to save uploaded file to destination directory.', 'wpcalibrate-marketplace-review-importer' ),
			);
		}

		return array(
			'success' => true,
			'path'    => $destination,
			'error'   => '',
		);
	}

	/**
	 * Clean up aged temporary upload files based on configured retention hours.
	 *
	 * @return int Number of deleted files.
	 */
	public static function cleanup_aged_files(): int {
		$retention_hours = (int) SettingsRepository::get( 'wpcmri_temp_retention_hours', 24 );
		$cutoff_time     = time() - ( $retention_hours * 3600 );
		$dir             = self::get_upload_dir();
		$deleted_count   = 0;

		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$files = glob( $dir . 'import_*.*' );
		if ( ! $files ) {
			return 0;
		}

		foreach ( $files as $filepath ) {
			if ( is_file( $filepath ) && filemtime( $filepath ) < $cutoff_time ) {
				// phpcs:ignore WordPress.WP.AlternativeFileSystem.unlink_unlink
				if ( unlink( $filepath ) ) {
					++$deleted_count;
				}
			}
		}

		return $deleted_count;
	}
}
