<?php
/**
 * Application singleton — framework bootstrap.
 *
 * @package WPFlint
 */

declare(strict_types=1);

namespace WPFlint;

use WPFlint\Container\Container;
use WPFlint\Providers\ServiceProvider;

/**
 * Main application class. Extends Container, manages service providers and lifecycle.
 */
class Application extends Container {

	/**
	 * The singleton application instance.
	 *
	 * @var Application|null
	 */
	protected static ?Application $instance = null;

	/**
	 * The base path of the plugin.
	 *
	 * @var string
	 */
	protected string $base_path = '';

	/**
	 * All registered service providers.
	 *
	 * @var array<int, ServiceProvider>
	 */
	protected array $providers = array();

	/**
	 * Deferred provider map: abstract => provider class.
	 *
	 * @var array<string, string>
	 */
	protected array $deferred_providers = array();

	/**
	 * Whether the application has been booted.
	 *
	 * @var bool
	 */
	protected bool $booted = false;

	/**
	 * Create a new application instance.
	 *
	 * @param string $base_path The base path of the plugin.
	 */
	public function __construct( string $base_path = '' ) {
		$this->base_path = $base_path;

		$this->register_base_bindings();
	}

	/**
	 * Get or create the singleton application instance.
	 *
	 * @param string $base_path The base path of the plugin.
	 *
	 * @return static
	 */
	public static function get_instance( string $base_path = '' ): self {
		if ( null === static::$instance ) {
			static::$instance = new static( $base_path );
		}

		return static::$instance;
	}

	/**
	 * Clear the singleton instance (for testing).
	 *
	 * @return void
	 */
	public static function clear_instance(): void {
		static::$instance = null;
	}

	/**
	 * Register the base container bindings.
	 *
	 * @return void
	 */
	protected function register_base_bindings(): void {
		$this->instance( 'app', $this );
		$this->instance( self::class, $this );
	}

	/**
	 * Bootstrap the application by hooking into WordPress.
	 *
	 * @return void
	 */
	public function bootstrap(): void {
		add_action(
			'plugins_loaded',
			function () {
				$this->register_all_providers();
			},
			1
		);

		add_action(
			'init',
			function () {
				$this->boot_providers();
			},
			5
		);

		add_action(
			'wp_loaded',
			function () {
				$this->boot_deferred_providers();
			}
		);
	}

	/**
	 * Register a service provider.
	 *
	 * @param string|ServiceProvider $provider Provider class name or instance.
	 *
	 * @return ServiceProvider The registered provider.
	 */
	public function register( $provider ): ServiceProvider {
		if ( is_string( $provider ) ) {
			$provider = new $provider( $this );
		}

		// If deferred, store the mapping and defer registration.
		if ( $provider->defer ) {
			foreach ( $provider->provides() as $abstract ) {
				$this->deferred_providers[ $abstract ] = get_class( $provider );
			}

			return $provider;
		}

		$provider->register();
		$provider->mark_registered();

		$this->providers[] = $provider;

		// If already booted, boot this provider immediately.
		if ( $this->booted ) {
			$this->boot_provider( $provider );
		}

		return $provider;
	}

	/**
	 * Register all queued providers. Called on plugins_loaded.
	 *
	 * @return void
	 */
	public function register_all_providers(): void {
		// Providers are registered via register() calls before bootstrap.
		// This hook exists so consumers can rely on plugins_loaded timing.
	}

	/**
	 * Boot all registered (non-deferred) providers. Called on init.
	 *
	 * @return void
	 */
	public function boot_providers(): void {
		if ( $this->booted ) {
			return;
		}

		foreach ( $this->providers as $provider ) {
			$this->boot_provider( $provider );
		}

		$this->booted = true;
	}

	/**
	 * Boot a single provider.
	 *
	 * @param ServiceProvider $provider The provider to boot.
	 *
	 * @return void
	 */
	protected function boot_provider( ServiceProvider $provider ): void {
		if ( $provider->is_booted() ) {
			return;
		}

		$provider->boot();
		$provider->mark_booted();
	}

	/**
	 * Boot all deferred providers. Called on wp_loaded.
	 *
	 * @return void
	 */
	public function boot_deferred_providers(): void {
		foreach ( $this->deferred_providers as $abstract => $provider_class ) {
			$this->register_deferred_provider( $provider_class );
		}

		$this->deferred_providers = array();
	}

	/**
	 * Register and boot a deferred provider by class name.
	 *
	 * @param string $provider_class The provider class name.
	 *
	 * @return void
	 */
	protected function register_deferred_provider( string $provider_class ): void {
		// Avoid duplicate registration.
		foreach ( $this->providers as $existing ) {
			if ( $existing instanceof $provider_class ) {
				return;
			}
		}

		$provider = new $provider_class( $this );

		$provider->register();
		$provider->mark_registered();

		$this->providers[] = $provider;

		if ( $this->booted ) {
			$this->boot_provider( $provider );
		}
	}

	/**
	 * Override make() to resolve deferred providers on first call.
	 *
	 * @param string $abstract The abstract type or alias.
	 *
	 * @return mixed
	 */
	public function make( string $abstract ) {
		if ( isset( $this->deferred_providers[ $abstract ] ) ) {
			$this->register_deferred_provider( $this->deferred_providers[ $abstract ] );
			unset( $this->deferred_providers[ $abstract ] );
		}

		return parent::make( $abstract );
	}

	/**
	 * Get the base path of the plugin.
	 *
	 * @param string $path Optional path to append.
	 *
	 * @return string
	 */
	public function base_path( string $path = '' ): string {
		if ( '' === $path ) {
			return $this->base_path;
		}

		return $this->base_path . DIRECTORY_SEPARATOR . ltrim( $path, DIRECTORY_SEPARATOR );
	}

	/**
	 * Check if the application has been booted.
	 *
	 * @return bool
	 */
	public function is_booted(): bool {
		return $this->booted;
	}

	/**
	 * Get all registered providers.
	 *
	 * @return array<int, ServiceProvider>
	 */
	public function get_providers(): array {
		return $this->providers;
	}

	/**
	 * Get the deferred provider map.
	 *
	 * @return array<string, string>
	 */
	public function get_deferred_providers(): array {
		return $this->deferred_providers;
	}
}
