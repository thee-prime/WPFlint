<?php

declare(strict_types=1);

namespace WPFlint\Tests\Scheduling;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Application;
use WPFlint\Queue\QueueManager;
use WPFlint\Queue\Job;
use WPFlint\Scheduling\ScheduleEvent;
use WPFlint\Scheduling\Scheduler;
use WPFlint\Scheduling\SchedulerServiceProvider;

/**
 * @covers \WPFlint\Scheduling\ScheduleEvent
 * @covers \WPFlint\Scheduling\Scheduler
 * @covers \WPFlint\Scheduling\SchedulerServiceProvider
 */
class SchedulerTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		Application::clear_instance();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// ScheduleEvent — identity
	// ---------------------------------------------------------------

	public function test_default_hook_is_generated_from_interval(): void {
		$event = new ScheduleEvent( function () {} );
		$event->hourly();

		$this->assertSame( 'wpflint_schedule_hourly', $event->get_hook() );
	}

	public function test_name_overrides_auto_hook(): void {
		$event = new ScheduleEvent( function () {} );
		$event->name( 'my_plugin_cleanup' )->hourly();

		$this->assertSame( 'my_plugin_cleanup', $event->get_hook() );
	}

	public function test_description_stored_and_retrieved(): void {
		$event = new ScheduleEvent( function () {} );
		$event->description( 'Cleans up old data.' );

		$this->assertSame( 'Cleans up old data.', $event->get_description() );
	}

	public function test_enabled_by_default(): void {
		$event = new ScheduleEvent( function () {} );
		$this->assertTrue( $event->is_enabled() );
	}

	public function test_skip_disables_event(): void {
		$event = new ScheduleEvent( function () {} );
		$event->skip();

		$this->assertFalse( $event->is_enabled() );
	}

	// ---------------------------------------------------------------
	// ScheduleEvent — interval methods
	// ---------------------------------------------------------------

	/** @dataProvider interval_provider */
	public function test_interval_method( string $method, string $expected_key ): void {
		$event = new ScheduleEvent( function () {} );
		$event->$method();

		$this->assertSame( $expected_key, $event->get_interval() );
	}

	/** @return array<string, array{string, string}> */
	public function interval_provider(): array {
		return array(
			'every_minute'          => array( 'every_minute', Scheduler::EVERY_MINUTE ),
			'every_five_minutes'    => array( 'every_five_minutes', Scheduler::EVERY_5_MINUTES ),
			'every_ten_minutes'     => array( 'every_ten_minutes', Scheduler::EVERY_10_MINUTES ),
			'every_fifteen_minutes' => array( 'every_fifteen_minutes', Scheduler::EVERY_15_MINUTES ),
			'every_thirty_minutes'  => array( 'every_thirty_minutes', Scheduler::EVERY_30_MINUTES ),
			'hourly'                => array( 'hourly', 'hourly' ),
			'twice_daily'           => array( 'twice_daily', 'twicedaily' ),
			'daily'                 => array( 'daily', 'daily' ),
			'weekly'                => array( 'weekly', Scheduler::WEEKLY ),
			'monthly'               => array( 'monthly', Scheduler::MONTHLY ),
		);
	}

	public function test_every_hours_single_maps_to_hourly(): void {
		$event = new ScheduleEvent( function () {} );
		$event->every_hours( 1 );

		$this->assertSame( 'hourly', $event->get_interval() );
	}

	public function test_every_hours_n_sets_custom_key(): void {
		$event = new ScheduleEvent( function () {} );
		$event->every_hours( 6 );

		$this->assertSame( 'wpflint_every_6_hours', $event->get_interval() );
	}

	public function test_cron_sets_custom_interval(): void {
		$event = new ScheduleEvent( function () {} );
		$event->cron( 'my_plugin_custom' );

		$this->assertSame( 'my_plugin_custom', $event->get_interval() );
	}

	// ---------------------------------------------------------------
	// ScheduleEvent — run()
	// ---------------------------------------------------------------

	public function test_run_invokes_callback(): void {
		$called = false;
		$event  = new ScheduleEvent( function () use ( &$called ) {
			$called = true;
		} );

		$event->run();

		$this->assertTrue( $called );
	}

	// ---------------------------------------------------------------
	// ScheduleEvent — register()
	// ---------------------------------------------------------------

	public function test_register_skips_disabled_event(): void {
		$called = false;
		WP_Mock::userFunction( 'add_action' )->andReturnUsing( function () use ( &$called ) {
			$called = true;
		} );

		$event = new ScheduleEvent( function () {} );
		$event->hourly()->skip()->register();

		$this->assertFalse( $called );
	}

	public function test_register_skips_event_without_interval(): void {
		$called = false;
		WP_Mock::userFunction( 'add_action' )->andReturnUsing( function () use ( &$called ) {
			$called = true;
		} );

		$event = new ScheduleEvent( function () {} );
		$event->name( 'my_hook' )->register(); // no interval set.

		$this->assertFalse( $called );
	}

	public function test_register_adds_action_and_schedules_when_not_already_scheduled(): void {
		$scheduled = array();

		WP_Mock::userFunction( 'add_action' );
		WP_Mock::userFunction( 'wp_next_scheduled' )->andReturn( false );
		WP_Mock::userFunction( 'wp_schedule_event' )->andReturnUsing(
			function ( $time, $interval, $hook ) use ( &$scheduled ) {
				$scheduled[] = $hook;
			}
		);

		$event = new ScheduleEvent( function () {} );
		$event->name( 'my_cron_hook' )->hourly()->register();

		// wp_schedule_event was called with our hook name.
		$this->assertContains( 'my_cron_hook', $scheduled );
	}

	public function test_register_skips_scheduling_if_already_scheduled(): void {
		$schedule_event_called = false;

		WP_Mock::userFunction( 'add_action' );
		WP_Mock::userFunction( 'wp_next_scheduled' )->andReturn( time() + 3600 );
		WP_Mock::userFunction( 'wp_schedule_event' )->andReturnUsing(
			function () use ( &$schedule_event_called ) {
				$schedule_event_called = true;
			}
		);

		$event = new ScheduleEvent( function () {} );
		$event->name( 'already_scheduled' )->daily()->register();

		$this->assertFalse( $schedule_event_called );
	}

	// ---------------------------------------------------------------
	// ScheduleEvent — unschedule()
	// ---------------------------------------------------------------

	public function test_unschedule_clears_event_when_scheduled(): void {
		$ts             = time() + 3600;
		$unscheduled_ts = null;

		WP_Mock::userFunction( 'wp_next_scheduled' )->andReturn( $ts );
		WP_Mock::userFunction( 'wp_unschedule_event' )->andReturnUsing(
			function ( int $time ) use ( &$unscheduled_ts ) {
				$unscheduled_ts = $time;
			}
		);

		$event = new ScheduleEvent( function () {} );
		$event->name( 'my_hook' )->hourly()->unschedule();

		$this->assertSame( $ts, $unscheduled_ts );
	}

	public function test_unschedule_noop_when_not_scheduled(): void {
		$unschedule_called = false;

		WP_Mock::userFunction( 'wp_next_scheduled' )->andReturn( false );
		WP_Mock::userFunction( 'wp_unschedule_event' )->andReturnUsing(
			function () use ( &$unschedule_called ) {
				$unschedule_called = true;
			}
		);

		$event = new ScheduleEvent( function () {} );
		$event->name( 'not_scheduled' )->hourly()->unschedule();

		$this->assertFalse( $unschedule_called );
	}

	// ---------------------------------------------------------------
	// Scheduler — call() and job()
	// ---------------------------------------------------------------

	public function test_call_adds_event_and_returns_schedule_event(): void {
		$scheduler = new Scheduler();
		$event     = $scheduler->call( function () {} );

		$this->assertInstanceOf( ScheduleEvent::class, $event );
		$this->assertSame( 1, $scheduler->count() );
	}

	public function test_multiple_calls_accumulate_events(): void {
		$scheduler = new Scheduler();
		$scheduler->call( function () {} )->hourly();
		$scheduler->call( function () {} )->daily();

		$this->assertSame( 2, $scheduler->count() );
		$this->assertCount( 2, $scheduler->get_events() );
	}

	public function test_job_dispatches_to_queue_on_run(): void {
		$dispatched = false;

		$queue = Mockery::mock( QueueManager::class );
		$queue->shouldReceive( 'dispatch' )->once()->andReturnUsing(
			function () use ( &$dispatched ) {
				$dispatched = true;
				return 1;
			}
		);

		$scheduler = new Scheduler( $queue );
		$event     = $scheduler->job( StubSchedulableJob::class )->daily();

		// Simulate WP-Cron firing the event directly.
		$event->run();

		$this->assertTrue( $dispatched );
	}

	public function test_job_runs_handle_directly_when_no_queue(): void {
		StubSchedulableJob::$handled = false;

		$scheduler = new Scheduler(); // no queue
		$event     = $scheduler->job( StubSchedulableJob::class )->daily();

		$event->run();

		$this->assertTrue( StubSchedulableJob::$handled );
	}

	public function test_job_sets_auto_hook_name(): void {
		$scheduler = new Scheduler();
		$event     = $scheduler->job( StubSchedulableJob::class );

		$hook = $event->get_hook();
		$this->assertStringStartsWith( 'wpflint_job_', $hook );
	}

	// ---------------------------------------------------------------
	// Scheduler — register() and unschedule_all()
	// ---------------------------------------------------------------

	public function test_register_calls_register_on_each_enabled_event(): void {
		$scheduled = array();

		WP_Mock::userFunction( 'add_action' );
		WP_Mock::userFunction( 'wp_next_scheduled' )->andReturn( false );
		WP_Mock::userFunction( 'wp_schedule_event' )->andReturnUsing(
			function ( $time, $interval, $hook ) use ( &$scheduled ) {
				$scheduled[] = $hook;
			}
		);

		$scheduler = new Scheduler();
		$scheduler->call( function () {} )->name( 'hook_a' )->hourly();
		$scheduler->call( function () {} )->name( 'hook_b' )->daily();
		$scheduler->register();

		$this->assertCount( 2, $scheduled );
	}

	public function test_register_skips_disabled_events(): void {
		$scheduled = array();

		WP_Mock::userFunction( 'add_action' );
		WP_Mock::userFunction( 'wp_next_scheduled' )->andReturn( false );
		WP_Mock::userFunction( 'wp_schedule_event' )->andReturnUsing(
			function ( $time, $interval, $hook ) use ( &$scheduled ) {
				$scheduled[] = $hook;
			}
		);

		$scheduler = new Scheduler();
		$scheduler->call( function () {} )->name( 'active' )->daily();
		$scheduler->call( function () {} )->name( 'inactive' )->daily()->skip();
		$scheduler->register();

		$this->assertCount( 1, $scheduled );
		$this->assertSame( 'active', $scheduled[0] );
	}

	public function test_unschedule_all_calls_unschedule_on_each_event(): void {
		$unscheduled = array();

		WP_Mock::userFunction( 'wp_next_scheduled' )->andReturn( time() + 100 );
		WP_Mock::userFunction( 'wp_unschedule_event' )->andReturnUsing(
			function ( $ts, $hook ) use ( &$unscheduled ) {
				$unscheduled[] = $hook;
			}
		);

		$scheduler = new Scheduler();
		$scheduler->call( function () {} )->name( 'hook_a' )->hourly();
		$scheduler->call( function () {} )->name( 'hook_b' )->daily();
		$scheduler->unschedule_all();

		$this->assertCount( 2, $unscheduled );
	}

	// ---------------------------------------------------------------
	// Scheduler — register_intervals()
	// ---------------------------------------------------------------

	public function test_register_intervals_adds_custom_keys(): void {
		$result = Scheduler::register_intervals( array() );

		$this->assertArrayHasKey( Scheduler::EVERY_MINUTE, $result );
		$this->assertArrayHasKey( Scheduler::EVERY_5_MINUTES, $result );
		$this->assertArrayHasKey( Scheduler::EVERY_15_MINUTES, $result );
		$this->assertArrayHasKey( Scheduler::WEEKLY, $result );
		$this->assertArrayHasKey( Scheduler::MONTHLY, $result );
		$this->assertArrayHasKey( 'wpflint_every_6_hours', $result );
	}

	public function test_register_intervals_preserves_existing_schedules(): void {
		$existing = array( 'my_custom' => array( 'interval' => 999, 'display' => 'Custom' ) );
		$result   = Scheduler::register_intervals( $existing );

		$this->assertArrayHasKey( 'my_custom', $result );
	}

	public function test_register_intervals_every_minute_correct_seconds(): void {
		$result = Scheduler::register_intervals( array() );

		$this->assertSame( MINUTE_IN_SECONDS, $result[ Scheduler::EVERY_MINUTE ]['interval'] );
	}

	public function test_register_intervals_weekly_correct_seconds(): void {
		$result = Scheduler::register_intervals( array() );

		$this->assertSame( 7 * DAY_IN_SECONDS, $result[ Scheduler::WEEKLY ]['interval'] );
	}

	// ---------------------------------------------------------------
	// SchedulerServiceProvider
	// ---------------------------------------------------------------

	public function test_provider_binds_scheduler_singleton(): void {
		WP_Mock::userFunction( 'add_filter' );
		WP_Mock::userFunction( 'add_action' );

		$app = Application::get_instance();
		$app->register( SchedulerServiceProvider::class );
		$app->boot_providers();

		$a = $app->make( 'scheduler' );
		$b = $app->make( 'scheduler' );

		$this->assertInstanceOf( Scheduler::class, $a );
		$this->assertSame( $a, $b );
	}

	public function test_provider_binds_scheduler_class(): void {
		WP_Mock::userFunction( 'add_filter' );
		WP_Mock::userFunction( 'add_action' );

		$app = Application::get_instance();
		$app->register( SchedulerServiceProvider::class );
		$app->boot_providers();

		$this->assertInstanceOf( Scheduler::class, $app->make( Scheduler::class ) );
	}

	// ---------------------------------------------------------------
	// Helper
	// ---------------------------------------------------------------

	public function test_wpflint_schedule_global_exists(): void {
		$this->assertTrue( function_exists( 'wpflint_schedule' ) );
	}

	public function test_wpflint_schedule_with_callback_returns_schedule_event(): void {
		WP_Mock::userFunction( 'add_filter' );
		WP_Mock::userFunction( 'add_action' );

		$app = Application::get_instance();
		$app->register( SchedulerServiceProvider::class );
		$app->boot_providers();

		$event = \wpflint_schedule( function () {} );

		$this->assertInstanceOf( ScheduleEvent::class, $event );
	}

	public function test_wpflint_schedule_without_callback_returns_scheduler(): void {
		WP_Mock::userFunction( 'add_filter' );
		WP_Mock::userFunction( 'add_action' );

		$app = Application::get_instance();
		$app->register( SchedulerServiceProvider::class );
		$app->boot_providers();

		$this->assertInstanceOf( Scheduler::class, \wpflint_schedule() );
	}
}

// ---------------------------------------------------------------
// Named stubs
// ---------------------------------------------------------------

/**
 * Schedulable job stub.
 */
class StubSchedulableJob extends Job {
	/** @var bool */
	public static bool $handled = false;

	public function handle(): void {
		static::$handled = true;
	}
}
