<?php

declare(strict_types=1);

namespace WPFlint\Tests\Queue;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Application;
use WPFlint\Logging\NullLogger;
use WPFlint\Queue\Job;
use WPFlint\Queue\JobRecord;
use WPFlint\Queue\QueueManager;
use WPFlint\Queue\QueueServiceProvider;
use WPFlint\Queue\QueueWorker;

/**
 * @covers \WPFlint\Queue\Job
 * @covers \WPFlint\Queue\JobRecord
 * @covers \WPFlint\Queue\QueueManager
 * @covers \WPFlint\Queue\QueueWorker
 * @covers \WPFlint\Queue\QueueServiceProvider
 */
class QueueTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );

		// Reset shared state on stub jobs.
		StubJob::$executed  = false;
		StubJob::$handler   = null;
		StubFailedJob::$failed_called = false;
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		Application::clear_instance();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Job base class
	// ---------------------------------------------------------------

	public function test_job_defaults(): void {
		$job = new StubJob();

		$this->assertSame( 'default', $job->get_queue() );
		$this->assertSame( 3, $job->get_max_attempts() );
		$this->assertSame( 0, $job->get_delay() );
		$this->assertSame( 0, $job->get_attempts() );
	}

	public function test_on_queue_sets_queue_name(): void {
		$job = new StubJob();
		$job->on_queue( 'emails' );

		$this->assertSame( 'emails', $job->get_queue() );
	}

	public function test_delay_sets_delay_seconds(): void {
		$job = new StubJob();
		$job->delay( 120 );

		$this->assertSame( 120, $job->get_delay() );
	}

	public function test_tries_sets_max_attempts(): void {
		$job = new StubJob();
		$job->tries( 5 );

		$this->assertSame( 5, $job->get_max_attempts() );
	}

	public function test_set_attempts_updates_attempts(): void {
		$job = new StubJob();
		$job->set_attempts( 2 );

		$this->assertSame( 2, $job->get_attempts() );
	}

	public function test_fluent_chaining(): void {
		$job    = new StubJob();
		$result = $job->on_queue( 'slow' )->delay( 30 )->tries( 1 );

		$this->assertSame( $job, $result );
		$this->assertSame( 'slow', $job->get_queue() );
		$this->assertSame( 30, $job->get_delay() );
		$this->assertSame( 1, $job->get_max_attempts() );
	}

	// ---------------------------------------------------------------
	// JobRecord
	// ---------------------------------------------------------------

	public function test_job_record_maps_row(): void {
		$record = new JobRecord( $this->make_row() );

		$this->assertSame( 1, $record->id );
		$this->assertSame( 'default', $record->queue );
		$this->assertSame( 0, $record->attempts );
		$this->assertSame( 0, $record->reserved_at );
	}

	public function test_job_record_unserializes_job(): void {
		$job    = new StubJob();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$record = new JobRecord( $this->make_row( array( 'payload' => serialize( $job ) ) ) );

		$this->assertInstanceOf( Job::class, $record->unserialize_job() );
	}

	public function test_job_record_throws_on_invalid_payload(): void {
		$this->expectException( \InvalidArgumentException::class );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$record = new JobRecord( $this->make_row( array( 'payload' => serialize( new \stdClass() ) ) ) );
		$record->unserialize_job();
	}

	// ---------------------------------------------------------------
	// QueueManager — dispatch
	// ---------------------------------------------------------------

	public function test_dispatch_inserts_row_and_schedules_event(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->insert_id = 7;
		$db->shouldReceive( 'insert' )->once()->andReturn( 1 );
		WP_Mock::userFunction( 'wp_schedule_single_event' )->once();

		$id = $mgr->dispatch( new StubJob() );

		$this->assertSame( 7, $id );
	}

	public function test_dispatch_with_delay_schedules_at_future_time(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->insert_id = 1;
		$db->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$scheduled_time = null;
		WP_Mock::userFunction( 'wp_schedule_single_event' )->andReturnUsing(
			function ( int $time ) use ( &$scheduled_time ) {
				$scheduled_time = $time;
			}
		);

		$job = new StubJob();
		$job->delay( 60 );
		$mgr->dispatch( $job );

		$this->assertGreaterThan( time(), $scheduled_time );
	}

	// ---------------------------------------------------------------
	// QueueManager — reserve
	// ---------------------------------------------------------------

	public function test_reserve_returns_null_when_queue_empty(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->shouldReceive( 'query' )->once();
		$db->shouldReceive( 'get_row' )->once()->andReturn( null );

		$this->assertNull( $mgr->reserve() );
	}

	public function test_reserve_returns_job_record_and_increments_attempts(): void {
		$job = new StubJob();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$row = $this->make_row( array( 'payload' => serialize( $job ), 'attempts' => 0 ) );

		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->shouldReceive( 'query' )->once();
		$db->shouldReceive( 'get_row' )->once()->andReturn( $row );
		$db->shouldReceive( 'update' )->once()->andReturn( 1 );

		$record = $mgr->reserve();

		$this->assertInstanceOf( JobRecord::class, $record );
		$this->assertSame( 1, $record->attempts ); // incremented from 0.
	}

	// ---------------------------------------------------------------
	// QueueManager — delete / release / fail
	// ---------------------------------------------------------------

	public function test_delete_removes_job_row(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->shouldReceive( 'delete' )
			->once()
			->with( Mockery::any(), array( 'id' => 1 ), array( '%d' ) )
			->andReturn( 1 );

		$mgr->delete( new JobRecord( $this->make_row() ) );

		// Mockery expectation above is the assertion.
		$this->addToAssertionCount( 1 );
	}

	public function test_release_clears_reserved_at(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->shouldReceive( 'update' )->once()->andReturnUsing(
			function ( $table, $data ) {
				$this->assertNull( $data['reserved_at'] );
				return 1;
			}
		);

		$mgr->release( new JobRecord( $this->make_row() ) );
	}

	public function test_fail_moves_job_to_failed_table(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->shouldReceive( 'insert' )->once()->andReturn( 1 );
		$db->shouldReceive( 'delete' )->once()->andReturn( 1 );

		$mgr->fail( new JobRecord( $this->make_row() ), new \RuntimeException( 'oops' ) );

		$this->addToAssertionCount( 1 );
	}

	// ---------------------------------------------------------------
	// QueueManager — counts / clear
	// ---------------------------------------------------------------

	public function test_pending_count(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->shouldReceive( 'get_var' )->once()->andReturn( '5' );

		$this->assertSame( 5, $mgr->pending_count() );
	}

	public function test_total_count(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->shouldReceive( 'get_var' )->once()->andReturn( '3' );

		$this->assertSame( 3, $mgr->total_count() );
	}

	public function test_clear_deletes_all_for_queue(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->rows_affected = 4;
		$db->shouldReceive( 'query' )->once()->andReturn( 4 );

		$this->assertSame( 4, $mgr->clear( 'default' ) );
	}

	public function test_clear_wildcard_clears_all_queues(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->rows_affected = 10;
		$db->shouldReceive( 'query' )->once()->andReturnUsing(
			function ( string $sql ) {
				$this->assertStringNotContainsString( 'WHERE', $sql );
				return 10;
			}
		);

		$mgr->clear( '*' );
	}

	// ---------------------------------------------------------------
	// QueueManager — retry failed job
	// ---------------------------------------------------------------

	public function test_retry_returns_false_when_failed_job_not_found(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$db->shouldReceive( 'get_row' )->once()->andReturn( null );

		$this->assertFalse( $mgr->retry( 99 ) );
	}

	public function test_retry_requeues_failed_job(): void {
		$db  = $this->mock_wpdb();
		$mgr = new QueueManager( $db );

		$job = new StubJob();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$failed_row = array( 'id' => 5, 'queue' => 'default', 'payload' => serialize( $job ), 'failed_at' => time() );

		$db->insert_id = 6;
		$db->shouldReceive( 'get_row' )->once()->andReturn( $failed_row );
		$db->shouldReceive( 'insert' )->once()->andReturn( 1 );
		$db->shouldReceive( 'delete' )->once()->andReturn( 1 );

		$this->assertTrue( $mgr->retry( 5 ) );
	}

	// ---------------------------------------------------------------
	// QueueWorker
	// ---------------------------------------------------------------

	public function test_process_returns_false_when_queue_empty(): void {
		$db  = $this->mock_wpdb();
		$mgr = Mockery::mock( QueueManager::class, array( $db ) )->makePartial();
		$mgr->shouldReceive( 'reserve' )->once()->andReturn( null );

		$worker = new QueueWorker( $mgr, new NullLogger() );

		$this->assertFalse( $worker->process() );
	}

	public function test_process_runs_job_and_deletes_on_success(): void {
		$job = new StubJob();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$record = new JobRecord( $this->make_row( array( 'payload' => serialize( $job ) ) ) );

		$db  = $this->mock_wpdb();
		$mgr = Mockery::mock( QueueManager::class, array( $db ) )->makePartial();
		$mgr->shouldReceive( 'reserve' )->once()->andReturn( $record );
		$mgr->shouldReceive( 'delete' )->once();

		$worker = new QueueWorker( $mgr, new NullLogger() );
		$result = $worker->process();

		$this->assertTrue( $result );
		$this->assertTrue( StubJob::$executed );
	}

	public function test_process_releases_job_for_retry_on_failure(): void {
		$job = new StubThrowingJob();
		$job->tries( 3 );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$record = new JobRecord( $this->make_row( array( 'payload' => serialize( $job ), 'attempts' => 1 ) ) );

		$db  = $this->mock_wpdb();
		$mgr = Mockery::mock( QueueManager::class, array( $db ) )->makePartial();
		$mgr->shouldReceive( 'reserve' )->once()->andReturn( $record );
		$mgr->shouldReceive( 'release' )->once();

		$worker = new QueueWorker( $mgr, new NullLogger() );
		$worker->process();

		$this->addToAssertionCount( 1 );
	}

	public function test_process_moves_to_failed_when_attempts_exhausted(): void {
		$job = new StubThrowingJob();
		$job->tries( 3 );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$record = new JobRecord( $this->make_row( array( 'payload' => serialize( $job ), 'attempts' => 3 ) ) );

		$db  = $this->mock_wpdb();
		$mgr = Mockery::mock( QueueManager::class, array( $db ) )->makePartial();
		$mgr->shouldReceive( 'reserve' )->once()->andReturn( $record );
		$mgr->shouldReceive( 'fail' )->once();

		$worker = new QueueWorker( $mgr, new NullLogger() );
		$worker->process();

		$this->addToAssertionCount( 1 );
	}

	public function test_process_calls_failed_hook_on_permanent_failure(): void {
		$job = new StubFailedJob();
		$job->tries( 1 );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$record = new JobRecord( $this->make_row( array( 'payload' => serialize( $job ), 'attempts' => 1 ) ) );

		$db  = $this->mock_wpdb();
		$mgr = Mockery::mock( QueueManager::class, array( $db ) )->makePartial();
		$mgr->shouldReceive( 'reserve' )->once()->andReturn( $record );
		$mgr->shouldReceive( 'fail' )->once();

		$worker = new QueueWorker( $mgr, new NullLogger() );
		$worker->process();

		$this->assertTrue( StubFailedJob::$failed_called );
	}

	public function test_process_all_drains_queue(): void {
		$job = new StubJob();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$record = new JobRecord( $this->make_row( array( 'payload' => serialize( $job ) ) ) );

		$db  = $this->mock_wpdb();
		$mgr = Mockery::mock( QueueManager::class, array( $db ) )->makePartial();
		$mgr->shouldReceive( 'reserve' )->andReturn( $record, $record, null );
		$mgr->shouldReceive( 'delete' )->twice();

		$worker = new QueueWorker( $mgr, new NullLogger() );

		$this->assertSame( 2, $worker->process_all() );
	}

	public function test_process_all_respects_limit(): void {
		$job = new StubJob();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$record = new JobRecord( $this->make_row( array( 'payload' => serialize( $job ) ) ) );

		$db  = $this->mock_wpdb();
		$mgr = Mockery::mock( QueueManager::class, array( $db ) )->makePartial();
		$mgr->shouldReceive( 'reserve' )->andReturn( $record );
		$mgr->shouldReceive( 'delete' )->times( 2 );

		$worker = new QueueWorker( $mgr, new NullLogger() );

		$this->assertSame( 2, $worker->process_all( 'default', 2 ) );
	}

	// ---------------------------------------------------------------
	// QueueServiceProvider
	// ---------------------------------------------------------------

	public function test_provider_binds_queue_manager(): void {
		WP_Mock::userFunction( 'add_action' );

		// Inject a mock wpdb global so the provider closure can build QueueManager.
		global $wpdb;
		$wpdb = $this->mock_wpdb(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test only.

		$app = Application::get_instance();
		$app->register( QueueServiceProvider::class );
		$app->boot_providers();

		$this->assertInstanceOf( QueueManager::class, $app->make( 'queue' ) );
	}

	public function test_provider_binds_queue_worker(): void {
		WP_Mock::userFunction( 'add_action' );

		global $wpdb;
		$wpdb = $this->mock_wpdb(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test only.

		$app = Application::get_instance();
		$app->register( QueueServiceProvider::class );
		$app->boot_providers();

		$this->assertInstanceOf( QueueWorker::class, $app->make( 'queue.worker' ) );
	}

	// ---------------------------------------------------------------
	// Global helper
	// ---------------------------------------------------------------

	public function test_wpflint_dispatch_global_exists(): void {
		$this->assertTrue( function_exists( 'wpflint_dispatch' ) );
	}

	// ---------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------

	/**
	 * Build a raw DB row array for JobRecord construction.
	 *
	 * @param array<string, mixed> $overrides Column overrides.
	 * @return array<string, mixed>
	 */
	private function make_row( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'           => 1,
				'queue'        => 'default',
				'payload'      => '',
				'attempts'     => 0,
				'available_at' => time(),
				'created_at'   => time(),
				'reserved_at'  => 0,
			),
			$overrides
		);
	}

	/**
	 * Build a partial Mockery mock of $wpdb with prepare() stubbed.
	 *
	 * @return \wpdb&\Mockery\MockInterface
	 */
	private function mock_wpdb() {
		$db         = Mockery::mock( 'wpdb' );
		$db->prefix = 'wp_';

		$db->shouldReceive( 'prepare' )->andReturnUsing(
			function ( string $sql ) {
				// Return the SQL unchanged — test assertions don't depend on param substitution.
				return $sql;
			}
		);

		return $db;
	}
}

// ---------------------------------------------------------------
// Named job stubs (anonymous classes cannot be serialized)
// ---------------------------------------------------------------

/**
 * Basic stub job that records when it was executed.
 */
class StubJob extends Job {
	/** @var bool */
	public static bool $executed = false;

	/** @var callable|null */
	public static $handler = null;

	public function handle(): void {
		static::$executed = true;
		if ( null !== static::$handler ) {
			( static::$handler )();
		}
	}
}

/**
 * Stub job that always throws.
 */
class StubThrowingJob extends Job {
	public function handle(): void {
		throw new \RuntimeException( 'intentional test failure' );
	}
}

/**
 * Stub job that throws and records when failed() is called.
 */
class StubFailedJob extends Job {
	/** @var bool */
	public static bool $failed_called = false;

	public function handle(): void {
		throw new \RuntimeException( 'bang' );
	}

	public function failed( \Throwable $e ): void {
		static::$failed_called = true;
	}
}
