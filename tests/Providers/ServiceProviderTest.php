<?php

declare(strict_types=1);

namespace WPFlint\Tests\Providers;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Application;
use WPFlint\Providers\ServiceProvider;

/**
 * @covers \WPFlint\Providers\ServiceProvider
 */
class ServiceProviderTest extends TestCase {

	protected Application $app;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		Application::clear_instance();
		$this->app = new Application( '/tmp/plugin' );
	}

	public function tearDown(): void {
		Application::clear_instance();
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function testRegisterIsCalledOnConcreteProvider(): void {
		$provider = new StubProvider( $this->app );

		$provider->register();

		$this->assertTrue( $this->app->has( 'stub.service' ) );
	}

	public function testBootDoesNothingByDefault(): void {
		$provider = new StubProvider( $this->app );

		// Should not throw.
		$provider->boot();

		$this->assertFalse( $provider->is_booted() );
	}

	public function testProvidesReturnsEmptyByDefault(): void {
		$provider = new StubProvider( $this->app );

		$this->assertSame( array(), $provider->provides() );
	}

	public function testDeferDefaultsToFalse(): void {
		$provider = new StubProvider( $this->app );

		$this->assertFalse( $provider->defer );
	}

	public function testMarkRegisteredAndIsRegistered(): void {
		$provider = new StubProvider( $this->app );

		$this->assertFalse( $provider->is_registered() );

		$provider->mark_registered();

		$this->assertTrue( $provider->is_registered() );
	}

	public function testMarkBootedAndIsBooted(): void {
		$provider = new StubProvider( $this->app );

		$this->assertFalse( $provider->is_booted() );

		$provider->mark_booted();

		$this->assertTrue( $provider->is_booted() );
	}

	public function testDeferredProviderReturnsProvides(): void {
		$provider = new DeferredStubProvider( $this->app );

		$this->assertTrue( $provider->defer );
		$this->assertSame( array( 'deferred.service' ), $provider->provides() );
	}
}

// ---------------------------------------------------------------------------
// Test stubs.
// ---------------------------------------------------------------------------

class StubProvider extends ServiceProvider {

	public function register(): void {
		$this->app->bind(
			'stub.service',
			function () {
				return 'stub-value';
			}
		);
	}
}

class DeferredStubProvider extends ServiceProvider {

	public bool $defer = true;

	public function register(): void {
		$this->app->bind(
			'deferred.service',
			function () {
				return 'deferred-value';
			}
		);
	}

	/**
	 * Get the services provided by this provider.
	 *
	 * @return array<int, string>
	 */
	public function provides(): array {
		return array( 'deferred.service' );
	}
}
