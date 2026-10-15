<?php
/**
 * Logger abstraction.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Infrastructure\Logging
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Infrastructure\Logging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger
 */
final class Logger {

	/**
	 * Log source context identifier.
	 *
	 * @var string
	 */
	public const SOURCE = 'wpcalibrate-marketplace-review-importer';

	/**
	 * Log an informational message.
	 *
	 * @param string               $message Message to log.
	 * @param array<string, mixed> $context Additional structured context.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string               $message Message to log.
	 * @param array<string, mixed> $context Additional structured context.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string               $message Message to log.
	 * @param array<string, mixed> $context Additional structured context.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Core logging implementation.
	 *
	 * @param string               $level Log level (info, warning, error).
	 * @param string               $message Message string.
	 * @param array<string, mixed> $context Context array.
	 * @return void
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		// Check if logging is enabled in options.
		$logging_enabled = (bool) get_option( 'wpcmri_enable_logging', true );
		if ( ! $logging_enabled && 'error' !== $level ) {
			return;
		}

		$sanitized_context = self::sanitize_context( $context );

		if ( function_exists( 'wc_get_logger' ) ) {
			$wc_logger = wc_get_logger();
			if ( $wc_logger ) {
				$formatted_message = $message;
				if ( ! empty( $sanitized_context ) ) {
					$formatted_message .= ' | Context: ' . wp_json_encode( $sanitized_context );
				}
				$wc_logger->log( $level, $formatted_message, array( 'source' => self::SOURCE ) );
				return;
			}
		}

		// Fallback to error_log if WP_DEBUG is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$entry = sprintf(
				'[%s] [WPCMRI] %s: %s %s',
				gmdate( 'Y-m-d H:i:s' ),
				strtoupper( $level ),
				$message,
				! empty( $sanitized_context ) ? wp_json_encode( $sanitized_context ) : ''
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $entry );
		}
	}

	/**
	 * Sanitize context to ensure sensitive keys and tokens are never written to logs.
	 *
	 * @param array<string, mixed> $context Context array.
	 * @return array<string, mixed>
	 */
	public static function sanitize_context( array $context ): array {
		$sensitive_keys = array(
			'api_key',
			'api_secret',
			'token',
			'license_key',
			'password',
			'authorization',
			'reviewer_email',
			'author_email',
		);

		$cleaned = array();
		foreach ( $context as $key => $value ) {
			$lower_key = strtolower( (string) $key );
			$is_secret = false;
			foreach ( $sensitive_keys as $secret_key ) {
				if ( str_contains( $lower_key, $secret_key ) ) {
					$is_secret = true;
					break;
				}
			}

			if ( $is_secret ) {
				$cleaned[ $key ] = '[REDACTED]';
			} elseif ( is_array( $value ) ) {
				$cleaned[ $key ] = self::sanitize_context( $value );
			} elseif ( is_scalar( $value ) || is_null( $value ) ) {
				$cleaned[ $key ] = $value;
			} else {
				$cleaned[ $key ] = '[COMPLEX_DATA]';
			}
		}

		return $cleaned;
	}
}
