<?php
/**
 * External Review Providers Registry.
 *
 * @package WPCalibrate\MarketplaceReviewImporter\Providers
 */

declare(strict_types=1);

namespace WPCalibrate\MarketplaceReviewImporter\Providers;

use WPCalibrate\MarketplaceReviewImporter\Providers\SerpApi\SerpApiProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProviderRegistry
 */
final class ProviderRegistry {

	/**
	 * Registered providers list.
	 *
	 * @var array<string, ProviderInterface>
	 */
	private array $providers = array();

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton registry instance.
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
	 * Constructor. Register default providers.
	 */
	private function __construct() {
		$this->register_provider( new SerpApiProvider() );
		$this->load_custom_providers();
	}

	/**
	 * Register a provider implementation.
	 *
	 * @param ProviderInterface $provider Provider instance.
	 * @return void
	 */
	public function register_provider( ProviderInterface $provider ): void {
		$this->providers[ $provider->get_id() ] = $provider;
	}

	/**
	 * Load any additional providers hooked through WordPress filters.
	 *
	 * @return void
	 */
	private function load_custom_providers(): void {
		/**
		 * Filter to allow adding custom external review providers.
		 *
		 * @param array<string, ProviderInterface> $providers Currently registered providers.
		 */
		$filtered_providers = apply_filters( 'wpcmri_register_providers', $this->providers );
		if ( is_array( $filtered_providers ) ) {
			foreach ( $filtered_providers as $provider ) {
				if ( $provider instanceof ProviderInterface ) {
					$this->providers[ $provider->get_id() ] = $provider;
				}
			}
		}
	}

	/**
	 * Get provider by ID.
	 *
	 * @param string $id Provider ID.
	 * @return ProviderInterface|null
	 */
	public function get_provider( string $id ): ?ProviderInterface {
		return $this->providers[ $id ] ?? null;
	}

	/**
	 * Get all registered providers.
	 *
	 * @return array<string, ProviderInterface>
	 */
	public function get_all(): array {
		return $this->providers;
	}
}
