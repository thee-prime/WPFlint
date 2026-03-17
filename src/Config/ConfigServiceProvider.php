<?php
/**
 * Service provider that registers the config repository.
 *
 * @package WPFlint\Config
 */

declare(strict_types=1);

namespace WPFlint\Config;

use WPFlint\Providers\ServiceProvider;

/**
 * Registers the config singleton in the container.
 */
class ConfigServiceProvider extends ServiceProvider {

	/**
	 * Register the config binding.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			'config',
			function () {
				return new Repository( $this->load_config() );
			}
		);
	}

	/**
	 * Boot the service provider.
	 *
	 * @return void
	 */
	public function boot(): void {
		// No boot actions needed.
	}

	/**
	 * Load configuration files from the config directory.
	 *
	 * Each PHP file in config/ should return an array. The filename (without
	 * extension) becomes the top-level key.
	 *
	 * @return array<string, mixed>
	 */
	protected function load_config(): array {
		$config_path = $this->app->base_path( 'config' );
		$items       = array();

		if ( ! is_dir( $config_path ) ) {
			return $items;
		}

		$files = glob( $config_path . '/*.php' );

		if ( false === $files ) {
			return $items;
		}

		foreach ( $files as $file ) {
			$key = basename( $file, '.php' );

			$items[ $key ] = require $file;
		}

		return $items;
	}
}
