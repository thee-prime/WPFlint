<?php
/**
 * Event service provider.
 *
 * @package WPFlint\Events
 */

declare(strict_types=1);

namespace WPFlint\Events;

use WPFlint\Providers\ServiceProvider;

/**
 * Registers the Dispatcher as a singleton.
 */
class EventServiceProvider extends ServiceProvider {

	/**
	 * Whether the provider is deferred.
	 *
	 * @var bool
	 */
	public bool $defer = true;

	/**
	 * Register event bindings.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			'events',
			function () {
				return new Dispatcher( $this->app );
			}
		);

		$this->app->singleton(
			Dispatcher::class,
			function () {
				return $this->app->make( 'events' );
			}
		);
	}

	/**
	 * Services provided by this provider.
	 *
	 * @return array<int, string>
	 */
	public function provides(): array {
		return array( 'events', Dispatcher::class );
	}
}
