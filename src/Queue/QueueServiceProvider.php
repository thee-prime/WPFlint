<?php
/**
 * Registers the queue system in the container.
 *
 * @package WPFlint\Queue
 */

declare(strict_types=1);

namespace WPFlint\Queue;

use WPFlint\Logging\LoggerInterface;
use WPFlint\Logging\NullLogger;
use WPFlint\Providers\ServiceProvider;

/**
 * Binds QueueManager and QueueWorker and registers the async cron hook.
 *
 * Usage in plugin bootstrap:
 *
 *     $app->register( QueueServiceProvider::class );
 *
 * Dispatch a job:
 *
 *     $app->make( 'queue' )->dispatch( new SendWelcomeEmail( $user_id ) );
 *     // or via helper:
 *     wpflint_dispatch( new SendWelcomeEmail( $user_id ) );
 */
class QueueServiceProvider extends ServiceProvider {

	/**
	 * Register queue bindings.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			'queue',
			function () {
				global $wpdb;
				return new QueueManager( $wpdb );
			}
		);

		$this->app->singleton( QueueManager::class, fn() => $this->app->make( 'queue' ) );

		$this->app->singleton(
			'queue.worker',
			function () {
				$manager = $this->app->make( 'queue' );
				$logger  = $this->resolve_logger();
				return new QueueWorker( $manager, $logger );
			}
		);

		$this->app->singleton( QueueWorker::class, fn() => $this->app->make( 'queue.worker' ) );
	}

	/**
	 * Boot: register the WP-Cron hook that processes the queue asynchronously.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action(
			QueueManager::PROCESS_HOOK,
			function ( string $queue = 'default' ) {
				$this->app->make( 'queue.worker' )->process_all( $queue );
			}
		);
	}

	/**
	 * Resolve the logger from the container, falling back to NullLogger.
	 *
	 * @return LoggerInterface
	 */
	private function resolve_logger(): LoggerInterface {
		try {
			return $this->app->make( LoggerInterface::class );
		} catch ( \Exception $e ) {
			return new NullLogger();
		}
	}
}
