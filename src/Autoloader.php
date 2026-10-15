<?php
/**
 * PSR-4 Autoloader for WPCalibrate Marketplace Review Importer.
 *
 * @package WPCalibrate\MarketplaceReviewImporter
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 */
final class Autoloader {

	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	private const PREFIX = 'WPCalibrate\\MarketplaceReviewImporter\\';

	/**
	 * Base directory for the namespace prefix.
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $base_dir Base directory path ending with directory separator.
	 */
	public function __construct( string $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Register autoloader with SPL.
	 *
	 * @param string $base_dir Path to the `src` directory.
	 * @return void
	 */
	public static function register( string $base_dir ): void {
		$loader = new self( $base_dir );
		spl_autoload_register( array( $loader, 'autoload' ) );
	}

	/**
	 * Loads the class file for a given class name.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public function autoload( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( self::PREFIX ) );
		$file           = $this->base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
