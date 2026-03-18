<?php
/**
 * Cache service provider.
 *
 * @package WPFlint\Cache
 */

declare(strict_types=1);

namespace WPFlint\Cache;

use WPFlint\Providers\ServiceProvider;

/**
 * Registers the CacheManager as a singleton.
 */
class CacheServiceProvider extends ServiceProvider {

	/**
	 * Whether the provider is deferred.
	 *
	 * @var bool
	 */
	public bool $defer = true;

	/**
	 * Register cache bindings.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			'cache',
			function () {
				$config = $this->app->has( 'config' )
					? $this->app->make( 'config' )
					: null;

				$driver = 'transient';
				$prefix = 'wpflint';

				if ( null !== $config ) {
					$driver = $config->get( 'cache.driver', 'transient' );
					$prefix = $config->get( 'cache.prefix', 'wpflint' );
				}

				return new CacheManager( $driver, $prefix );
			}
		);

		$this->app->singleton(
			CacheManager::class,
			function () {
				return $this->app->make( 'cache' );
			}
		);
	}

	/**
	 * Services provided by this provider.
	 *
	 * @return array<int, string>
	 */
	public function provides(): array {
		return array( 'cache', CacheManager::class );
	}
}
