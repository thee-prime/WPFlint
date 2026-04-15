<?php
/**
 * Service provider for the View module.
 *
 * @package WPFlint\View
 */

declare(strict_types=1);

namespace WPFlint\View;

use WPFlint\Providers\ServiceProvider;

/**
 * Configures View's default base path from the application base path.
 *
 * Usage:
 *
 *     $app->register( ViewServiceProvider::class );
 *
 * By default the views directory is resolved as:
 *     {app_base_path}/resources/views
 *
 * Override in config:
 *     // config/view.php
 *     return [ 'path' => plugin_dir_path( __FILE__ ) . 'templates' ];
 */
class ViewServiceProvider extends ServiceProvider {

	/**
	 * Register: nothing to bind (View uses static methods).
	 *
	 * @return void
	 */
	public function register(): void {}

	/**
	 * Boot: set the default view base path from app base path.
	 *
	 * @return void
	 */
	public function boot(): void {
		$views_path = $this->app->base_path( 'resources' . DIRECTORY_SEPARATOR . 'views' );
		View::set_base_path( $views_path );
	}
}
