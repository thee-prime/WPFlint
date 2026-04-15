<?php
/**
 * Service provider for the Assets module.
 *
 * @package WPFlint\Assets
 */

declare(strict_types=1);

namespace WPFlint\Assets;

use WPFlint\Providers\ServiceProvider;

/**
 * Binds AssetManager into the container as a singleton.
 *
 * Usage:
 *
 *     $app->register( AssetServiceProvider::class );
 *
 *     // Resolve from container:
 *     $assets = $app->make( AssetManager::class );
 *     $assets->script( 'my-plugin', plugin_dir_url( __FILE__ ) . 'assets/js/app.js' );
 *
 *     // Or use the Asset facade (if registered):
 *     Asset::script( 'my-plugin', '...' )->footer()->enqueue();
 */
class AssetServiceProvider extends ServiceProvider {

	/**
	 * Register AssetManager as a container singleton.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			'assets',
			static function () {
				return new AssetManager();
			}
		);

		$this->app->singleton(
			AssetManager::class,
			static function ( $app ) {
				return $app->make( 'assets' );
			}
		);
	}

	/**
	 * Boot: hook the asset manager's enqueue() into both frontend and admin.
	 *
	 * @return void
	 */
	public function boot(): void {
		$app = $this->app;

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $app ) {
				$app->make( 'assets' )->enqueue();
			}
		);

		add_action(
			'admin_enqueue_scripts',
			static function () use ( $app ) {
				$app->make( 'assets' )->enqueue();
			}
		);
	}
}
