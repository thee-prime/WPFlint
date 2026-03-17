<?php

declare(strict_types=1);

namespace WPFlint\Tests;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Application;
use WPFlint\Providers\ServiceProvider;

/**
 * @covers \WPFlint\Application
 */
class ApplicationTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		Application::clear_instance();
	}

	public function tearDown(): void {
		Application::clear_instance();
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Singleton
	// ---------------------------------------------------------------

	public function testGetInstanceReturnsSameInstance(): void {
		$a = Application::get_instance( '/tmp/plugin' );
		$b = Application::get_instance();

		$this->assertSame( $a, $b );
	}

	public function testClearInstanceAllowsNewInstance(): void {
		$a = Application::get_instance( '/tmp/plugin' );

		Application::clear_instance();

		$b = Application::get_instance( '/tmp/other' );

		$this->assertNotSame( $a, $b );
	}

	// ---------------------------------------------------------------
	// Base bindings
	// ---------------------------------------------------------------

	public function testBaseBindingsRegistered(): void {
		$app = new Application( '/tmp/plugin' );

		$this->assertSame( $app, $app->make( 'app' ) );
		$this->assertSame( $app, $app->make( Application::class ) );
	}

	// ---------------------------------------------------------------
	// Base path
	// ---------------------------------------------------------------

	public function testBasePathReturnsBasePath(): void {
		$app = new Application( '/tmp/plugin' );

		$this->assertSame( '/tmp/plugin', $app->base_path() );
	}

	public function testBasePathAppendsSubpath(): void {
		$app = new Application( '/tmp/plugin' );

		$this->assertSame( '/tmp/plugin/src/Http', $app->base_path( 'src/Http' ) );
	}

	// ---------------------------------------------------------------
	// Provider registration (non-deferred)
	// ---------------------------------------------------------------

	public function testRegisterNonDeferredProvider(): void {
		$app = new Application( '/tmp/plugin' );

		$provider = $app->register( AppStubProvider::class );

		$this->assertInstanceOf( AppStubProvider::class, $provider );
		$this->assertTrue( $provider->is_registered() );
		$this->assertTrue( $app->has( 'app.stub' ) );
		$this->assertSame( 'app-stub-value', $app->make( 'app.stub' ) );
	}

	public function testRegisterAcceptsProviderInstance(): void {
		$app      = new Application( '/tmp/plugin' );
		$provider = new AppStubProvider( $app );

		$result = $app->register( $provider );

		$this->assertSame( $provider, $result );
		$this->assertTrue( $provider->is_registered() );
	}

	public function testProviderIsBootedImmediatelyIfAppAlreadyBooted(): void {
		$app = new Application( '/tmp/plugin' );

		$app->boot_providers(); // Mark as booted.

		$provider = $app->register( AppStubProvider::class );

		$this->assertTrue( $provider->is_booted() );
	}

	// ---------------------------------------------------------------
	// Provider booting
	// ---------------------------------------------------------------

	public function testBootProvidersSetsBootedFlag(): void {
		$app = new Application( '/tmp/plugin' );

		$this->assertFalse( $app->is_booted() );

		$app->boot_providers();

		$this->assertTrue( $app->is_booted() );
	}

	public function testBootProvidersBootsAllRegistered(): void {
		$app = new Application( '/tmp/plugin' );

		$provider = $app->register( AppBootableProvider::class );

		$app->boot_providers();

		$this->assertTrue( $provider->is_booted() );
		$this->assertTrue( $app->has( 'booted.flag' ) );
	}

	public function testBootProvidersIsIdempotent(): void {
		$app = new Application( '/tmp/plugin' );

		$app->register( AppBootableProvider::class );

		$app->boot_providers();
		$app->boot_providers(); // Should not throw or double-boot.

		$this->assertTrue( $app->is_booted() );
	}

	// ---------------------------------------------------------------
	// Deferred providers
	// ---------------------------------------------------------------

	public function testDeferredProviderStoredInMap(): void {
		$app = new Application( '/tmp/plugin' );

		$app->register( AppDeferredProvider::class );

		$deferred = $app->get_deferred_providers();

		$this->assertArrayHasKey( 'deferred.thing', $deferred );
		$this->assertFalse( $app->has( 'deferred.thing' ) );
	}

	public function testDeferredProviderResolvedOnMake(): void {
		$app = new Application( '/tmp/plugin' );

		$app->register( AppDeferredProvider::class );

		$result = $app->make( 'deferred.thing' );

		$this->assertSame( 'deferred-thing-value', $result );
	}

	public function testDeferredProviderRemovedFromMapAfterMake(): void {
		$app = new Application( '/tmp/plugin' );

		$app->register( AppDeferredProvider::class );

		$app->make( 'deferred.thing' );

		$this->assertArrayNotHasKey( 'deferred.thing', $app->get_deferred_providers() );
	}

	public function testBootDeferredProvidersRegistersAll(): void {
		$app = new Application( '/tmp/plugin' );
		$app->boot_providers(); // Boot first.

		$app->register( AppDeferredProvider::class );

		$this->assertFalse( $app->has( 'deferred.thing' ) );

		$app->boot_deferred_providers();

		$this->assertTrue( $app->has( 'deferred.thing' ) );
		$this->assertEmpty( $app->get_deferred_providers() );
	}

	public function testDeferredProviderIsBootedOnWpLoaded(): void {
		$app = new Application( '/tmp/plugin' );
		$app->boot_providers();

		$app->register( AppDeferredProvider::class );
		$app->boot_deferred_providers();

		// Find the provider in the list and check it was booted.
		$found = false;
		foreach ( $app->get_providers() as $p ) {
			if ( $p instanceof AppDeferredProvider ) {
				$found = true;
				$this->assertTrue( $p->is_booted() );
			}
		}
		$this->assertTrue( $found );
	}

	// ---------------------------------------------------------------
	// Bootstrap hooks
	// ---------------------------------------------------------------

	public function testBootstrapRegistersWordPressHooks(): void {
		$app = new Application( '/tmp/plugin' );

		WP_Mock::expectActionAdded( 'plugins_loaded', \WP_Mock\Functions::type( 'callable' ), 1 );
		WP_Mock::expectActionAdded( 'init', \WP_Mock\Functions::type( 'callable' ), 5 );
		WP_Mock::expectActionAdded( 'wp_loaded', \WP_Mock\Functions::type( 'callable' ) );

		$app->bootstrap();

		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// get_providers
	// ---------------------------------------------------------------

	public function testGetProvidersReturnsRegisteredProviders(): void {
		$app = new Application( '/tmp/plugin' );

		$app->register( AppStubProvider::class );

		$providers = $app->get_providers();

		$this->assertCount( 1, $providers );
		$this->assertInstanceOf( AppStubProvider::class, $providers[0] );
	}

	// ---------------------------------------------------------------
	// Duplicate deferred provider prevention
	// ---------------------------------------------------------------

	public function testDeferredProviderNotRegisteredTwice(): void {
		$app = new Application( '/tmp/plugin' );

		$app->register( AppDeferredProvider::class );

		// First resolve triggers registration.
		$app->make( 'deferred.thing' );

		// Boot deferred should not duplicate.
		$app->boot_deferred_providers();

		$count = 0;
		foreach ( $app->get_providers() as $p ) {
			if ( $p instanceof AppDeferredProvider ) {
				++$count;
			}
		}

		$this->assertSame( 1, $count );
	}
}

// ---------------------------------------------------------------------------
// Test stubs.
// ---------------------------------------------------------------------------

class AppStubProvider extends ServiceProvider {

	public function register(): void {
		$this->app->bind(
			'app.stub',
			function () {
				return 'app-stub-value';
			}
		);
	}
}

class AppBootableProvider extends ServiceProvider {

	public function register(): void {
		// Nothing to register.
	}

	public function boot(): void {
		$this->app->instance( 'booted.flag', true );
	}
}

class AppDeferredProvider extends ServiceProvider {

	public bool $defer = true;

	public function register(): void {
		$this->app->bind(
			'deferred.thing',
			function () {
				return 'deferred-thing-value';
			}
		);
	}

	/**
	 * Get the services provided.
	 *
	 * @return array<int, string>
	 */
	public function provides(): array {
		return array( 'deferred.thing' );
	}
}
