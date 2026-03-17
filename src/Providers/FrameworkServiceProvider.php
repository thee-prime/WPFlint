<?php
/**
 * Core framework service provider.
 *
 * @package WPFlint\Providers
 */

declare(strict_types=1);

namespace WPFlint\Providers;

use WPFlint\Application;
use WPFlint\Container\Container;
use WPFlint\Container\ContainerInterface;

/**
 * Registers core framework bindings in the container.
 */
class FrameworkServiceProvider extends ServiceProvider {

	/**
	 * Register core framework bindings.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			ContainerInterface::class,
			fn( Application $app ) => $app
		);

		$this->app->singleton(
			Container::class,
			fn( Application $app ) => $app
		);
	}

	/**
	 * Boot the framework provider.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Core framework boot logic — reserved for future hooks.
	}
}
