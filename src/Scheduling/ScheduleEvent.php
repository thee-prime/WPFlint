<?php
/**
 * A single scheduled recurring task.
 *
 * @package WPFlint\Scheduling
 */

declare(strict_types=1);

namespace WPFlint\Scheduling;

/**
 * Represents one entry in the application schedule.
 *
 * Created by Scheduler::call() or Scheduler::job(). Fluent timing methods
 * configure the WP-Cron recurrence interval. Call register() to push the
 * event into WP-Cron (idempotent — safe to call on every request).
 *
 * Usage:
 *
 *     $scheduler->call( function() { clean_expired_transients(); } )
 *               ->name( 'my_plugin_cleanup' )
 *               ->hourly();
 *
 *     $scheduler->job( GenerateReportJob::class )
 *               ->name( 'my_plugin_daily_report' )
 *               ->dailyAt( '03:00' );
 */
class ScheduleEvent {

	/**
	 * Unique WP hook name for this event.
	 *
	 * @var string
	 */
	protected string $hook = '';

	/**
	 * Callable to invoke when the event fires.
	 *
	 * @var callable
	 */
	protected $callback;

	/**
	 * WP-Cron recurrence key (e.g. 'hourly', 'wpflint_every_minute').
	 *
	 * @var string
	 */
	protected string $interval = '';

	/**
	 * Whether this event is active.
	 *
	 * @var bool
	 */
	protected bool $enabled = true;

	/**
	 * Optional description shown in debug output.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Create a ScheduleEvent.
	 *
	 * @param callable $callback The callback or closure to execute.
	 */
	public function __construct( callable $callback ) {
		$this->callback = $callback;
	}

	// ---------------------------------------------------------------
	// Identity
	// ---------------------------------------------------------------

	/**
	 * Set the unique hook name for this event.
	 *
	 * If not set, Scheduler auto-generates one from a hash of the interval.
	 *
	 * @param string $hook Unique WP action hook name.
	 * @return $this
	 */
	public function name( string $hook ): self {
		$this->hook = $hook;
		return $this;
	}

	/**
	 * Set a human-readable description.
	 *
	 * @param string $description Description text.
	 * @return $this
	 */
	public function description( string $description ): self {
		$this->description = $description;
		return $this;
	}

	/**
	 * Disable this event (it will not be registered with WP-Cron).
	 *
	 * @return $this
	 */
	public function skip(): self {
		$this->enabled = false;
		return $this;
	}

	// ---------------------------------------------------------------
	// Timing — standard intervals
	// ---------------------------------------------------------------

	/**
	 * Run every minute.
	 *
	 * @return $this
	 */
	public function every_minute(): self {
		return $this->set_interval( Scheduler::EVERY_MINUTE );
	}

	/**
	 * Run every 5 minutes.
	 *
	 * @return $this
	 */
	public function every_five_minutes(): self {
		return $this->set_interval( Scheduler::EVERY_5_MINUTES );
	}

	/**
	 * Run every 10 minutes.
	 *
	 * @return $this
	 */
	public function every_ten_minutes(): self {
		return $this->set_interval( Scheduler::EVERY_10_MINUTES );
	}

	/**
	 * Run every 15 minutes.
	 *
	 * @return $this
	 */
	public function every_fifteen_minutes(): self {
		return $this->set_interval( Scheduler::EVERY_15_MINUTES );
	}

	/**
	 * Run every 30 minutes.
	 *
	 * @return $this
	 */
	public function every_thirty_minutes(): self {
		return $this->set_interval( Scheduler::EVERY_30_MINUTES );
	}

	/**
	 * Run once per hour.
	 *
	 * @return $this
	 */
	public function hourly(): self {
		return $this->set_interval( 'hourly' );
	}

	/**
	 * Run every N hours.
	 *
	 * @param int $hours Number of hours between runs.
	 * @return $this
	 */
	public function every_hours( int $hours ): self {
		if ( 1 === $hours ) {
			return $this->hourly();
		}

		return $this->set_interval( 'wpflint_every_' . $hours . '_hours' );
	}

	/**
	 * Run twice per day (every 12 hours).
	 *
	 * @return $this
	 */
	public function twice_daily(): self {
		return $this->set_interval( 'twicedaily' );
	}

	/**
	 * Run once per day.
	 *
	 * @return $this
	 */
	public function daily(): self {
		return $this->set_interval( 'daily' );
	}

	/**
	 * Run once per week.
	 *
	 * @return $this
	 */
	public function weekly(): self {
		return $this->set_interval( Scheduler::WEEKLY );
	}

	/**
	 * Run once per month (~30 days).
	 *
	 * @return $this
	 */
	public function monthly(): self {
		return $this->set_interval( Scheduler::MONTHLY );
	}

	/**
	 * Run on a custom interval (registered separately in cron_schedules).
	 *
	 * @param string $interval WP-Cron recurrence key.
	 * @return $this
	 */
	public function cron( string $interval ): self {
		return $this->set_interval( $interval );
	}

	// ---------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------

	/**
	 * Register this event with WP-Cron (idempotent).
	 *
	 * Only schedules the event if it is not already scheduled. Safe to call
	 * on every page load (typically from an 'init' action hook).
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->enabled || '' === $this->interval ) {
			return;
		}

		$hook = $this->get_hook();

		// Register the action handler.
		add_action( $hook, $this->callback );

		// Schedule if not already scheduled.
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), $this->interval, $hook );
		}
	}

	/**
	 * Unschedule this event from WP-Cron.
	 *
	 * @return void
	 */
	public function unschedule(): void {
		$hook      = $this->get_hook();
		$timestamp = wp_next_scheduled( $hook );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}

	/**
	 * Execute the callback directly (bypasses scheduling — useful for testing).
	 *
	 * @return void
	 */
	public function run(): void {
		( $this->callback )();
	}

	// ---------------------------------------------------------------
	// Accessors
	// ---------------------------------------------------------------

	/**
	 * Get the resolved hook name.
	 *
	 * @return string
	 */
	public function get_hook(): string {
		if ( '' !== $this->hook ) {
			return $this->hook;
		}

		// Auto-generate a stable hook name from the interval.
		return 'wpflint_schedule_' . $this->interval;
	}

	/**
	 * Get the recurrence interval key.
	 *
	 * @return string
	 */
	public function get_interval(): string {
		return $this->interval;
	}

	/**
	 * Whether this event is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Get the description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->description;
	}

	// ---------------------------------------------------------------
	// Internals
	// ---------------------------------------------------------------

	/**
	 * Set the interval and return $this.
	 *
	 * @param string $interval WP-Cron recurrence key.
	 * @return $this
	 */
	protected function set_interval( string $interval ): self {
		$this->interval = $interval;
		return $this;
	}
}
