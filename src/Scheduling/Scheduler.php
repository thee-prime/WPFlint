<?php
/**
 * Fluent application scheduler backed by WordPress Cron.
 *
 * @package WPFlint\Scheduling
 */

declare(strict_types=1);

namespace WPFlint\Scheduling;

use WPFlint\Queue\Job;
use WPFlint\Queue\QueueManager;

/**
 * Manages a collection of scheduled tasks.
 *
 * Define your schedule in a service provider's boot() method:
 *
 *     $scheduler = $app->make( 'scheduler' );
 *
 *     $scheduler->call( function() { do_something(); } )
 *               ->name( 'my_plugin_cleanup' )
 *               ->daily();
 *
 *     $scheduler->job( GenerateReportJob::class )
 *               ->name( 'my_plugin_report' )
 *               ->weekly();
 *
 * All events are registered on 'init' automatically by SchedulerServiceProvider.
 */
class Scheduler {

	// Custom WP-Cron recurrence keys added by this framework.
	const EVERY_MINUTE     = 'wpflint_every_minute';
	const EVERY_5_MINUTES  = 'wpflint_every_5_minutes';
	const EVERY_10_MINUTES = 'wpflint_every_10_minutes';
	const EVERY_15_MINUTES = 'wpflint_every_15_minutes';
	const EVERY_30_MINUTES = 'wpflint_every_30_minutes';
	const WEEKLY           = 'wpflint_weekly';
	const MONTHLY          = 'wpflint_monthly';

	/**
	 * All registered schedule events.
	 *
	 * @var array<int, ScheduleEvent>
	 */
	protected array $events = array();

	/**
	 * Queue manager for job-based events (optional).
	 *
	 * @var QueueManager|null
	 */
	protected ?QueueManager $queue;

	/**
	 * Create a Scheduler.
	 *
	 * @param QueueManager|null $queue Optional queue manager for job dispatch.
	 */
	public function __construct( ?QueueManager $queue = null ) {
		$this->queue = $queue;
	}

	// ---------------------------------------------------------------
	// Schedule entry points
	// ---------------------------------------------------------------

	/**
	 * Schedule a callable to run on a recurring interval.
	 *
	 * @param callable $callback The closure or callable to execute.
	 * @return ScheduleEvent Fluent event builder.
	 */
	public function call( callable $callback ): ScheduleEvent {
		$event          = new ScheduleEvent( $callback );
		$this->events[] = $event;
		return $event;
	}

	/**
	 * Schedule a Job class to be dispatched on a recurring interval.
	 *
	 * The job is pushed onto the queue each time the cron fires.
	 *
	 * @param string $job_class Fully-qualified Job class name.
	 * @return ScheduleEvent Fluent event builder.
	 */
	public function job( string $job_class ): ScheduleEvent {
		$queue = $this->queue;

		$callback = function () use ( $job_class, $queue ) {
			$instance = new $job_class();
			if ( null !== $queue ) {
				$queue->dispatch( $instance );
			} else {
				$instance->handle();
			}
		};

		$event = new ScheduleEvent( $callback );
		$event->name( 'wpflint_job_' . strtolower( str_replace( '\\', '_', $job_class ) ) );
		$this->events[] = $event;
		return $event;
	}

	// ---------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------

	/**
	 * Register all enabled events with WP-Cron.
	 *
	 * Call this from an 'init' action hook so wp_schedule_event() is available.
	 *
	 * @return void
	 */
	public function register(): void {
		foreach ( $this->events as $event ) {
			$event->register();
		}
	}

	/**
	 * Unschedule all events managed by this scheduler.
	 *
	 * Useful during plugin deactivation.
	 *
	 * @return void
	 */
	public function unschedule_all(): void {
		foreach ( $this->events as $event ) {
			$event->unschedule();
		}
	}

	// ---------------------------------------------------------------
	// Custom cron interval registration
	// ---------------------------------------------------------------

	/**
	 * Register all WPFlint custom cron intervals via the cron_schedules filter.
	 *
	 * Must be called before or during the 'cron_schedules' filter (typically
	 * very early in plugin bootstrap or inside the filter callback).
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing WP schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_intervals( array $schedules ): array {
		$custom = array(
			static::EVERY_MINUTE     => array(
				'interval' => MINUTE_IN_SECONDS,
				'display'  => __( 'Every Minute', 'wpflint' ),
			),
			static::EVERY_5_MINUTES  => array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 Minutes', 'wpflint' ),
			),
			static::EVERY_10_MINUTES => array(
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 10 Minutes', 'wpflint' ),
			),
			static::EVERY_15_MINUTES => array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 Minutes', 'wpflint' ),
			),
			static::EVERY_30_MINUTES => array(
				'interval' => 30 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 30 Minutes', 'wpflint' ),
			),
			static::WEEKLY           => array(
				'interval' => 7 * DAY_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'wpflint' ),
			),
			static::MONTHLY          => array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'wpflint' ),
			),
		);

		// Add per-N-hours intervals for 2–23 hours.
		for ( $h = 2; $h <= 23; $h++ ) {
			$key            = 'wpflint_every_' . $h . '_hours';
			$custom[ $key ] = array(
				'interval' => $h * HOUR_IN_SECONDS,
				/* translators: %d: number of hours */
				'display'  => sprintf( __( 'Every %d Hours', 'wpflint' ), $h ),
			);
		}

		return array_merge( $schedules, $custom );
	}

	// ---------------------------------------------------------------
	// Introspection
	// ---------------------------------------------------------------

	/**
	 * Get all registered events.
	 *
	 * @return array<int, ScheduleEvent>
	 */
	public function get_events(): array {
		return $this->events;
	}

	/**
	 * Get the number of registered events.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->events );
	}
}
