<?php
/**
 * Registers the Scheduler in the container.
 *
 * @package WPFlint\Scheduling
 */

declare(strict_types=1);

namespace WPFlint\Scheduling;

use WPFlint\Providers\ServiceProvider;
use WPFlint\Queue\QueueManager;

/**
 * Binds the Scheduler singleton and wires WP-Cron hooks.
 *
 * Usage:
 *
 *     $app->register( SchedulerServiceProvider::class );
 *
 *     // Then define events in your own provider's boot():
 *     $scheduler = $app->make( 'scheduler' );
 *     $scheduler->call( fn() => my_cleanup() )->name('my_cleanup')->daily();
 */
class SchedulerServiceProvider extends ServiceProvider {

	/**
	 * Register the Scheduler singleton.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			'scheduler',
			function () {
				$queue = null;
				try {
					$queue = $this->app->make( QueueManager::class );
				} catch ( \Exception $e ) {
					// Queue not bound — scheduler still works with plain callables; $queue stays null.
					$queue = null;
				}
				return new Scheduler( $queue );
			}
		);

		$this->app->singleton( Scheduler::class, fn() => $this->app->make( 'scheduler' ) );
	}

	/**
	 * Boot: register custom cron intervals + schedule events on 'init'.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Register custom WP-Cron intervals — interval values are defined in Scheduler::register_intervals().
		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- intervals defined in Scheduler::register_intervals(), all ≥ 60s.
		add_filter( 'cron_schedules', array( Scheduler::class, 'register_intervals' ) );

		// Register all scheduled events on init (wp_schedule_event requires init+).
		add_action(
			'init',
			function () {
				$this->app->make( 'scheduler' )->register();
			}
		);
	}
}
