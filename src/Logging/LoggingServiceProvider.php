<?php
/**
 * Registers the Logger in the container.
 *
 * @package WPFlint\Logging
 */

declare(strict_types=1);

namespace WPFlint\Logging;

use WPFlint\Providers\ServiceProvider;

/**
 * Binds the Logger and LoggerInterface to the container under the 'logger' alias.
 *
 * Usage in a plugin bootstrap:
 *
 *     $app->register( LoggingServiceProvider::class );
 *
 * Resolve:
 *
 *     $app->make( 'logger' )->info( 'Plugin booted.' );
 *     $app->make( LoggerInterface::class )->error( 'Something failed.' );
 */
class LoggingServiceProvider extends ServiceProvider {

	/**
	 * Deferred — only boots when 'logger' / LoggerInterface is first resolved.
	 *
	 * @var bool
	 */
	public bool $defer = true;

	/**
	 * Register the logger singleton.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			'logger',
			function () {
				$channel   = defined( 'WPFLINT_LOG_CHANNEL' ) ? WPFLINT_LOG_CHANNEL : 'wpflint';
				$min_level = defined( 'WPFLINT_LOG_LEVEL' ) ? WPFLINT_LOG_LEVEL : LogLevel::DEBUG;
				return new Logger( $channel, $min_level );
			}
		);

		$this->app->singleton( LoggerInterface::class, fn() => $this->app->make( 'logger' ) );
		$this->app->singleton( Logger::class, fn() => $this->app->make( 'logger' ) );
	}

	/**
	 * Boot is a no-op — logger needs no hooks.
	 *
	 * @return void
	 */
	public function boot(): void {}

	/**
	 * Deferred abstracts provided by this provider.
	 *
	 * @return array<int, string>
	 */
	public function provides(): array {
		return array( 'logger', LoggerInterface::class, Logger::class );
	}
}
