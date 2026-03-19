<?php
/**
 * Integration test — exercises the full WPFlint framework lifecycle.
 *
 * Bootstraps Application, registers providers, runs migrations, creates models,
 * fires events, caches data, and asserts WP compliance throughout.
 *
 * @package WPFlint\Tests\Integration
 */

declare(strict_types=1);

namespace WPFlint\Tests\Integration;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Application;
use WPFlint\Cache\CacheManager;
use WPFlint\Container\Container;
use WPFlint\Database\Migrations\Migration;
use WPFlint\Database\Migrations\MigrationRepository;
use WPFlint\Database\Migrations\Migrator;
use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;
use WPFlint\Events\Dispatcher;
use WPFlint\Events\Event;
use WPFlint\Providers\ServiceProvider;

/**
 * @covers \WPFlint\Application
 * @covers \WPFlint\Container\Container
 * @covers \WPFlint\Cache\CacheManager
 * @covers \WPFlint\Events\Dispatcher
 * @covers \WPFlint\Events\Event
 * @covers \WPFlint\Database\ORM\Model
 * @covers \WPFlint\Database\Migrations\Migrator
 * @covers \WPFlint\Providers\ServiceProvider
 */
class FullPluginTest extends TestCase {

	/**
	 * Mock $wpdb instance.
	 *
	 * @var \Mockery\MockInterface
	 */
	protected $wpdb;

	/**
	 * Application instance.
	 *
	 * @var Application
	 */
	protected Application $app;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		// Reset the singleton so each test gets a fresh app.
		Application::clear_instance();

		// Mock wpdb.
		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';
		$GLOBALS['wpdb']    = $this->wpdb;

		// Create application.
		$this->app = Application::get_instance( '/tmp/wpflint-test' );

		WP_Mock::userFunction( '__' )->andReturnArg( 0 );

		// Simulate WP options storage for TaggedCache key tracking.
		$options = array();
		WP_Mock::userFunction( 'get_option' )->andReturnUsing(
			function ( $key, $default = false ) use ( &$options ) {
				return $options[ $key ] ?? $default;
			}
		);
		WP_Mock::userFunction( 'update_option' )->andReturnUsing(
			function ( $key, $value ) use ( &$options ) {
				$options[ $key ] = $value;
				return true;
			}
		);
		WP_Mock::userFunction( 'delete_option' )->andReturnUsing(
			function ( $key ) use ( &$options ) {
				unset( $options[ $key ] );
				return true;
			}
		);
	}

	public function tearDown(): void {
		Application::clear_instance();
		IntegrationClassListener::$received_event = null;
		unset( $GLOBALS['wpdb'] );
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	// -----------------------------------------------------------------
	// 1. Application bootstrap + ServiceProvider
	// -----------------------------------------------------------------

	public function testApplicationBootstrapsAndRegistersProvider(): void {
		$provider = $this->app->register( IntegrationTestProvider::class );

		$this->assertInstanceOf( ServiceProvider::class, $provider );
		$this->assertTrue( $provider->is_registered() );

		// Provider bound the event dispatcher.
		$dispatcher = $this->app->make( Dispatcher::class );
		$this->assertInstanceOf( Dispatcher::class, $dispatcher );

		// Provider bound the cache manager.
		$cache = $this->app->make( 'cache' );
		$this->assertInstanceOf( CacheManager::class, $cache );
	}

	public function testBootProviderCallsBoot(): void {
		$this->app->register( IntegrationTestProvider::class );
		$this->app->boot_providers();

		$this->assertTrue( $this->app->is_booted() );
	}

	// -----------------------------------------------------------------
	// 2. Container auto-resolution
	// -----------------------------------------------------------------

	public function testContainerAutoResolvesDependencies(): void {
		$this->app->register( IntegrationTestProvider::class );

		$dispatcher = $this->app->make( Dispatcher::class );
		$this->assertInstanceOf( Dispatcher::class, $dispatcher );

		// Singleton check — same instance returned.
		$this->assertSame( $dispatcher, $this->app->make( Dispatcher::class ) );
	}

	// -----------------------------------------------------------------
	// 3. Migration — run pending
	// -----------------------------------------------------------------

	public function testMigratorRunsPendingMigrations(): void {
		$repository = Mockery::mock( MigrationRepository::class );
		$repository->shouldReceive( 'get_ran' )->andReturn( array() );
		$repository->shouldReceive( 'get_last_batch_number' )->andReturn( 0 );
		$repository->shouldReceive( 'log' )->once();

		$migration_class = $this->create_integration_migration( 'IntegrationCreateOrdersTable' );

		$migrator = new Migrator( $repository, array( $migration_class ) );
		$ran      = $migrator->run();

		$this->assertSame( array( $migration_class ), $ran );
	}

	public function testMigratorStatusReportsCorrectly(): void {
		$migration_class = $this->create_integration_migration( 'IntegrationStatusMigration' );

		$repository = Mockery::mock( MigrationRepository::class );
		$repository->shouldReceive( 'get_ran' )->andReturn( array( $migration_class ) );

		$migrator = new Migrator( $repository, array( $migration_class ) );
		$status   = $migrator->get_status();

		$this->assertCount( 1, $status );
		$this->assertTrue( $status[0]['ran'] );
		$this->assertSame( $migration_class, $status[0]['migration'] );
	}

	// -----------------------------------------------------------------
	// 4. Model — create, find, query
	// -----------------------------------------------------------------

	public function testModelCreatePersistsToDatabase(): void {
		WP_Mock::userFunction( 'current_time', array(
			'return' => '2026-03-19 10:00:00',
		) );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$this->wpdb->insert_id = 1;

		$order = IntegrationOrder::create( array(
			'status' => 'pending',
			'total'  => 99.50,
		) );

		$this->assertInstanceOf( IntegrationOrder::class, $order );
		$this->assertTrue( $order->exists() );
		$this->assertSame( 1, $order->get_attribute( 'id' ) );
		$this->assertSame( 'pending', $order->get_attribute( 'status' ) );
	}

	public function testModelFindQueriesWithPrepare(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared_sql' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array(
				array( 'id' => '1', 'status' => 'pending', 'total' => '99.50' ),
			) );

		$order = IntegrationOrder::find( 1 );

		$this->assertInstanceOf( IntegrationOrder::class, $order );
		$this->assertTrue( $order->exists() );
	}

	public function testModelAllReturnsCollection(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array(
				array( 'id' => '1', 'status' => 'pending', 'total' => '10' ),
				array( 'id' => '2', 'status' => 'paid', 'total' => '20' ),
			) );

		$orders = IntegrationOrder::all();

		$this->assertCount( 2, $orders );
		$this->assertInstanceOf( IntegrationOrder::class, $orders[0] );
	}

	public function testModelWhereChaining(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array(
				array( 'id' => '3', 'status' => 'pending', 'total' => '150' ),
			) );

		$orders = IntegrationOrder::where( 'status', '=', 'pending' )
			->where( 'total', '>', 100 )
			->get_models();

		$this->assertCount( 1, $orders );
		$this->assertSame( 'pending', $orders[0]->get_attribute( 'status' ) );
	}

	public function testModelCastsAttributes(): void {
		$model = IntegrationOrder::hydrate_one( array(
			'id'     => '42',
			'status' => 'paid',
			'total'  => '99.99',
		) );

		$this->assertSame( 42, $model->get_attribute( 'id' ) );
		$this->assertSame( 99.99, $model->get_attribute( 'total' ) );
	}

	public function testModelSaveUpdatesExistingRecord(): void {
		WP_Mock::userFunction( 'current_time', array(
			'return' => '2026-03-19 10:00:00',
		) );

		$model = IntegrationOrder::hydrate_one( array(
			'id'     => '1',
			'status' => 'pending',
			'total'  => '50',
		) );

		$model->set_attribute( 'status', 'paid' );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$result = $model->save();

		$this->assertTrue( $result );
		$this->assertSame( 'paid', $model->get_attribute( 'status' ) );
	}

	// -----------------------------------------------------------------
	// 5. Events — fire + listener
	// -----------------------------------------------------------------

	public function testEventFireCallsClosureListener(): void {
		$dispatcher = new Dispatcher( $this->app );
		$received   = null;

		$dispatcher->listen(
			IntegrationOrderPlaced::class,
			function ( IntegrationOrderPlaced $event ) use ( &$received ) {
				$received = $event->order_id;
			}
		);

		$dispatcher->fire( new IntegrationOrderPlaced( 42 ) );

		$this->assertSame( 42, $received );
	}

	public function testEventFireCallsClassListener(): void {
		$dispatcher = new Dispatcher( $this->app );

		$dispatcher->listen( IntegrationOrderPlaced::class, IntegrationClassListener::class );
		$dispatcher->fire( new IntegrationOrderPlaced( 99 ) );

		$this->assertNotNull( IntegrationClassListener::$received_event );
		$this->assertSame( 99, IntegrationClassListener::$received_event->order_id );
	}

	public function testEventStopPropagation(): void {
		$dispatcher = new Dispatcher();
		$log        = array();

		$dispatcher->listen( IntegrationOrderPlaced::class, function ( IntegrationOrderPlaced $event ) use ( &$log ) {
			$log[] = 'first';
			$event->stop_propagation();
		} );

		$dispatcher->listen( IntegrationOrderPlaced::class, function () use ( &$log ) {
			$log[] = 'second';
		} );

		$dispatcher->fire( new IntegrationOrderPlaced( 1 ) );

		$this->assertSame( array( 'first' ), $log );
	}

	public function testEventRegisteredViaProvider(): void {
		$this->app->register( IntegrationTestProvider::class );
		$this->app->boot_providers();

		$dispatcher = $this->app->make( Dispatcher::class );
		$this->assertTrue( $dispatcher->has_listeners( IntegrationOrderPlaced::class ) );

		$dispatcher->fire( new IntegrationOrderPlaced( 77 ) );

		$this->assertNotNull( IntegrationClassListener::$received_event );
		$this->assertSame( 77, IntegrationClassListener::$received_event->order_id );
	}

	// -----------------------------------------------------------------
	// 6. Cache — remember, tags, flush
	// -----------------------------------------------------------------

	public function testCacheRememberStoresAndReturns(): void {
		$cache = new CacheManager( 'array' );

		$result = $cache->remember( 'orders_all', 3600, function () {
			return array( 'order1', 'order2' );
		} );

		$this->assertSame( array( 'order1', 'order2' ), $result );
		$this->assertSame( array( 'order1', 'order2' ), $cache->get( 'orders_all' ) );
	}

	public function testCacheRememberReturnsCachedOnSubsequentCall(): void {
		$cache = new CacheManager( 'array' );
		$calls = 0;

		$callback = function () use ( &$calls ) {
			$calls++;
			return 'expensive_result';
		};

		$cache->remember( 'key', 3600, $callback );
		$cache->remember( 'key', 3600, $callback );

		$this->assertSame( 1, $calls );
	}

	public function testCacheTagsFlush(): void {
		$cache = new CacheManager( 'array' );

		$cache->tags( 'orders' )->put( 'list', 'cached_orders', 3600 );
		$this->assertSame( 'cached_orders', $cache->tags( 'orders' )->get( 'list' ) );

		$cache->tags( 'orders' )->flush();

		// After flush, the tagged key should be gone.
		$this->assertNull( $cache->tags( 'orders' )->get( 'list' ) );
	}

	public function testCacheFreshBypassesRead(): void {
		$cache = new CacheManager( 'array' );
		$cache->put( 'key', 'stale', 3600 );

		$result = $cache->fresh()->remember( 'key', 3600, function () {
			return 'fresh_value';
		} );

		$this->assertSame( 'fresh_value', $result );
		$this->assertSame( 'fresh_value', $cache->get( 'key' ) );
	}

	public function testCacheIntegrationViaContainer(): void {
		$this->app->register( IntegrationTestProvider::class );

		$cache = $this->app->make( 'cache' );
		$cache->put( 'test_key', 'test_value', 300 );

		$this->assertSame( 'test_value', $cache->get( 'test_key' ) );
	}

	// -----------------------------------------------------------------
	// 7. WP compliance — prepare() on all queries
	// -----------------------------------------------------------------

	public function testSelectQueryUsesPrepare(): void {
		// Verify that QueryBuilder uses prepare() for SELECT with WHERE.
		$prepare_called = false;

		$this->wpdb->shouldReceive( 'prepare' )
			->atLeast()
			->once()
			->andReturnUsing( function () use ( &$prepare_called ) {
				$prepare_called = true;
				return 'prepared_sql';
			} );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		IntegrationOrder::where( 'status', '=', 'pending' )->get();

		$this->assertTrue( $prepare_called, 'SELECT with WHERE must use $wpdb->prepare()' );
	}

	public function testFindUsesPrepare(): void {
		$prepare_called = false;

		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function () use ( &$prepare_called ) {
				$prepare_called = true;
				return 'prepared';
			} );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		IntegrationOrder::find( 1 );

		$this->assertTrue( $prepare_called, 'find() must use $wpdb->prepare()' );
	}

	public function testDeleteUsesPrepare(): void {
		$prepare_called = false;

		$model = IntegrationOrder::hydrate_one( array(
			'id'     => '5',
			'status' => 'pending',
			'total'  => '10',
		) );

		$this->wpdb->shouldReceive( 'prepare' )
			->atLeast()
			->once()
			->andReturnUsing( function () use ( &$prepare_called ) {
				$prepare_called = true;
				return 'prepared';
			} );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$model->delete();

		$this->assertTrue( $prepare_called, 'delete() must use $wpdb->prepare()' );
	}

	// -----------------------------------------------------------------
	// 8. Full lifecycle — end-to-end
	// -----------------------------------------------------------------

	public function testFullLifecycleEndToEnd(): void {
		// 1. Bootstrap app + register provider.
		$this->app->register( IntegrationTestProvider::class );
		$this->app->boot_providers();

		// 2. Run migration.
		$repository = Mockery::mock( MigrationRepository::class );
		$repository->shouldReceive( 'get_ran' )->andReturn( array() );
		$repository->shouldReceive( 'get_last_batch_number' )->andReturn( 0 );
		$repository->shouldReceive( 'log' )->once();

		$migration_class = $this->create_integration_migration( 'LifecycleCreateOrdersTable' );
		$migrator        = new Migrator( $repository, array( $migration_class ) );
		$ran             = $migrator->run();

		$this->assertCount( 1, $ran );

		// 3. Create a model.
		WP_Mock::userFunction( 'current_time', array(
			'return' => '2026-03-19 12:00:00',
		) );

		$this->wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );
		$this->wpdb->insert_id = 1;

		$order = IntegrationOrder::create( array(
			'status' => 'pending',
			'total'  => 250,
		) );

		$this->assertTrue( $order->exists() );

		// 4. Fire event.
		$dispatcher = $this->app->make( Dispatcher::class );
		$dispatcher->fire( new IntegrationOrderPlaced( $order->get_attribute( 'id' ) ) );

		$this->assertNotNull( IntegrationClassListener::$received_event );
		$this->assertSame( 1, IntegrationClassListener::$received_event->order_id );

		// 5. Cache the result.
		$cache  = $this->app->make( 'cache' );
		$cached = $cache->remember( 'order_1', 3600, function () use ( $order ) {
			return $order->to_array();
		} );

		$this->assertSame( 'pending', $cached['status'] );
		$this->assertSame( $cached, $cache->get( 'order_1' ) );

		// 6. Flush tagged cache.
		$cache->tags( 'orders' )->put( 'list', 'cached', 3600 );
		$cache->tags( 'orders' )->flush();
		$this->assertNull( $cache->tags( 'orders' )->get( 'list' ) );
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	/**
	 * Dynamically create a migration class for testing.
	 *
	 * @param string $class_name Migration class name.
	 * @return string The fully qualified class name.
	 */
	private function create_integration_migration( string $class_name ): string {
		if ( ! class_exists( $class_name ) ) {
			eval( // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test-only dynamic class creation.
				'class ' . $class_name . ' extends \\WPFlint\\Database\\Migrations\\Migration {
					public function up(): void {}
					public function down(): void {}
				}'
			);
		}
		return $class_name;
	}
}

// -------------------------------------------------------------------------
// Test stubs
// -------------------------------------------------------------------------

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

/**
 * Test service provider that binds dispatcher and cache.
 */
class IntegrationTestProvider extends ServiceProvider {

	public function register(): void {
		$this->app->singleton( Dispatcher::class, function ( $app ) {
			return new Dispatcher( $app );
		} );

		$this->app->singleton( 'cache', function () {
			return new CacheManager( 'array' );
		} );
	}

	public function boot(): void {
		$dispatcher = $this->app->make( Dispatcher::class );
		$dispatcher->listen( IntegrationOrderPlaced::class, IntegrationClassListener::class );
	}
}

/**
 * Test model.
 */
class IntegrationOrder extends Model {

	protected static string $table = 'orders';

	protected array $fillable = array( 'status', 'total' );

	protected array $casts = array(
		'id'    => 'integer',
		'total' => 'float',
	);
}

/**
 * Test event.
 */
class IntegrationOrderPlaced extends Event {

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	public int $order_id;

	/**
	 * Constructor.
	 *
	 * @param int $order_id Order ID.
	 */
	public function __construct( int $order_id ) {
		$this->order_id = $order_id;
	}
}

/**
 * Test event listener (class-based).
 */
class IntegrationClassListener {

	/**
	 * Last received event.
	 *
	 * @var IntegrationOrderPlaced|null
	 */
	public static ?IntegrationOrderPlaced $received_event = null;

	/**
	 * Handle the event.
	 *
	 * @param IntegrationOrderPlaced $event The event.
	 */
	public function handle( IntegrationOrderPlaced $event ): void {
		static::$received_event = $event;
	}
}

// phpcs:enable
