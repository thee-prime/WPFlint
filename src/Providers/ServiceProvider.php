<?php
/**
 * Abstract service provider base class.
 *
 * @package WPFlint\Providers
 */

declare(strict_types=1);

namespace WPFlint\Providers;

use WPFlint\Application;

/**
 * Base class for all service providers.
 */
abstract class ServiceProvider {

	/**
	 * The application instance.
	 *
	 * @var Application
	 */
	protected Application $app;

	/**
	 * Whether the provider is deferred.
	 *
	 * @var bool
	 */
	public bool $defer = false;

	/**
	 * Whether the provider has been booted.
	 *
	 * @var bool
	 */
	protected bool $booted = false;

	/**
	 * Whether the provider has been registered.
	 *
	 * @var bool
	 */
	protected bool $registered = false;

	/**
	 * Create a new service provider instance.
	 *
	 * @param Application $app The application instance.
	 */
	public function __construct( Application $app ) {
		$this->app = $app;
	}

	/**
	 * Register bindings in the container.
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Boot the service provider (hook registrations, etc.).
	 *
	 * @return void
	 */
	public function boot(): void {
		// Override in subclasses.
	}

	/**
	 * Get the services provided by this provider (for deferred providers).
	 *
	 * @return array<int, string>
	 */
	public function provides(): array {
		return array();
	}

	/**
	 * Mark the provider as registered.
	 *
	 * @return void
	 */
	public function mark_registered(): void {
		$this->registered = true;
	}

	/**
	 * Mark the provider as booted.
	 *
	 * @return void
	 */
	public function mark_booted(): void {
		$this->booted = true;
	}

	/**
	 * Check if the provider has been registered.
	 *
	 * @return bool
	 */
	public function is_registered(): bool {
		return $this->registered;
	}

	/**
	 * Check if the provider has been booted.
	 *
	 * @return bool
	 */
	public function is_booted(): bool {
		return $this->booted;
	}
}
