<?php
/**
 * HTTP service provider.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

use WPFlint\Providers\ServiceProvider;

/**
 * Registers HTTP layer bindings and boots the router.
 */
class HttpServiceProvider extends ServiceProvider {

	/**
	 * Register HTTP bindings.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			Router::class,
			function () {
				return new Router( $this->app );
			}
		);
	}

	/**
	 * Boot the HTTP layer — registers routes with WordPress hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		$router = $this->app->make( Router::class );
		$router->boot();
	}

	/**
	 * Services provided by this provider.
	 *
	 * @return array<int, string>
	 */
	public function provides(): array {
		return array( Router::class );
	}
}
