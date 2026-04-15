<?php

declare(strict_types=1);

namespace WPFlint\Tests\Support;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Application;
use WPFlint\Config\Repository;
use WPFlint\Cache\CacheManager;
use WPFlint\Events\Event;

/**
 * @covers \WPFlint\Support\config_value
 * @covers \WPFlint\Support\config_set
 * @covers \WPFlint\Support\app_make
 * @covers \WPFlint\Support\env_value
 * @covers \WPFlint\Support\fire_event
 * @covers \WPFlint\Support\cache_manager
 */
class HelpersTest extends TestCase {

	/** @var Application */
	private Application $app;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		Application::clear_instance();

		$this->app = Application::get_instance();

		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
	}

	public function tearDown(): void {
		Application::clear_instance();
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// config_value
	// ---------------------------------------------------------------

	public function testConfigValueReturnsValueFromRepository(): void {
		$repo = new Repository( array( 'app' => array( 'name' => 'TestPlugin' ) ) );
		$this->app->instance( 'config', $repo );

		$result = \WPFlint\Support\config_value( 'app.name' );

		$this->assertSame( 'TestPlugin', $result );
	}

	public function testConfigValueReturnsDefaultWhenKeyMissing(): void {
		$repo = new Repository( array() );
		$this->app->instance( 'config', $repo );

		$result = \WPFlint\Support\config_value( 'app.missing', 'fallback' );

		$this->assertSame( 'fallback', $result );
	}

	public function testConfigValueReturnsDefaultWhenAppNotBooted(): void {
		Application::clear_instance();

		// Force a fresh instance with no config bound to trigger exception path.
		$result = \WPFlint\Support\config_value( 'app.name', 'safe' );

		// Must not throw; returns default.
		$this->assertSame( 'safe', $result );
	}

	// ---------------------------------------------------------------
	// config_set
	// ---------------------------------------------------------------

	public function testConfigSetWritesValueToRepository(): void {
		$repo = new Repository( array() );
		$this->app->instance( 'config', $repo );

		\WPFlint\Support\config_set( 'app.debug', true );

		$this->assertTrue( $repo->get( 'app.debug' ) );
	}

	// ---------------------------------------------------------------
	// app_make
	// ---------------------------------------------------------------

	public function testAppMakeWithNullReturnsApplication(): void {
		$result = \WPFlint\Support\app_make();

		$this->assertSame( $this->app, $result );
	}

	public function testAppMakeResolvesBinding(): void {
		$this->app->bind( 'test_service', function () {
			return new \stdClass();
		} );

		$result = \WPFlint\Support\app_make( 'test_service' );

		$this->assertInstanceOf( \stdClass::class, $result );
	}

	// ---------------------------------------------------------------
	// env_value
	// ---------------------------------------------------------------

	public function testEnvValueReadsDefinedConstant(): void {
		if ( ! defined( 'WPFLINT_TEST_CONST' ) ) {
			define( 'WPFLINT_TEST_CONST', 'constant_value' );
		}

		$result = \WPFlint\Support\env_value( 'WPFLINT_TEST_CONST' );

		$this->assertSame( 'constant_value', $result );
	}

	public function testEnvValueReturnsDefaultWhenNotFound(): void {
		$result = \WPFlint\Support\env_value( 'WPFLINT_NONEXISTENT_CONST_XYZ', 'default_val' );

		$this->assertSame( 'default_val', $result );
	}

	// ---------------------------------------------------------------
	// fire_event
	// ---------------------------------------------------------------

	public function testFireEventDispatchesToEventService(): void {
		$fired = false;
		$event = new TestHelpersEvent();

		$dispatcher = Mockery::mock( 'WPFlint\Events\Dispatcher' );
		$dispatcher->shouldReceive( 'fire' )
			->once()
			->with( $event )
			->andReturnUsing( function ( \WPFlint\Events\Event $e ) use ( &$fired ) {
				$fired = true;
				return $e;
			} );

		$this->app->instance( 'events', $dispatcher );

		\WPFlint\Support\fire_event( $event );

		$this->assertTrue( $fired );
	}

	public function testFireEventSilentlyFailsWhenDispatcherNotBound(): void {
		// No 'events' binding. Should not throw.
		\WPFlint\Support\fire_event( new TestHelpersEvent() );

		$this->assertTrue( true );
	}

	// ---------------------------------------------------------------
	// cache_manager
	// ---------------------------------------------------------------

	public function testCacheManagerReturnsCacheInstance(): void {
		$cache = Mockery::mock( CacheManager::class );
		$this->app->instance( 'cache', $cache );

		$result = \WPFlint\Support\cache_manager();

		$this->assertSame( $cache, $result );
	}

	public function testCacheManagerWithTagsReturnsTaggedCache(): void {
		$tagged = Mockery::mock( 'WPFlint\Cache\TaggedCache' );

		$cache = Mockery::mock( CacheManager::class );
		$cache->shouldReceive( 'tags' )->with( 'orders' )->once()->andReturn( $tagged );

		$this->app->instance( 'cache', $cache );

		$result = \WPFlint\Support\cache_manager( 'orders' );

		$this->assertSame( $tagged, $result );
	}

	// ---------------------------------------------------------------
	// Global functions exist and delegate correctly
	// ---------------------------------------------------------------

	public function testGlobalWpflintConfigFunctionExists(): void {
		$this->assertTrue( function_exists( 'wpflint_config' ) );
	}

	public function testGlobalWpflintAppFunctionExists(): void {
		$this->assertTrue( function_exists( 'wpflint_app' ) );
	}

	public function testGlobalWpflintEnvFunctionExists(): void {
		$this->assertTrue( function_exists( 'wpflint_env' ) );
	}

	public function testGlobalWpflintEventFunctionExists(): void {
		$this->assertTrue( function_exists( 'wpflint_event' ) );
	}

	public function testGlobalWpflintCacheFunctionExists(): void {
		$this->assertTrue( function_exists( 'wpflint_cache' ) );
	}

	public function testWpflintConfigDelegatesToNamespacedFunction(): void {
		$repo = new Repository( array( 'plugin' => array( 'version' => '2.0' ) ) );
		$this->app->instance( 'config', $repo );

		// Call global function with leading backslash from within namespace.
		$this->assertSame( '2.0', \wpflint_config( 'plugin.version' ) );
	}
}

// ---------------------------------------------------------------------------
// In-test Event stub
// ---------------------------------------------------------------------------

/**
 * Concrete event used in fire_event tests.
 */
class TestHelpersEvent extends \WPFlint\Events\Event {}
