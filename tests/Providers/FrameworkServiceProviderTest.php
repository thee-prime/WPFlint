<?php

declare(strict_types=1);

namespace WPFlint\Tests\Providers;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Application;
use WPFlint\Container\Container;
use WPFlint\Container\ContainerInterface;
use WPFlint\Providers\FrameworkServiceProvider;

/**
 * @covers \WPFlint\Providers\FrameworkServiceProvider
 */
class FrameworkServiceProviderTest extends TestCase {

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

	public function testRegisterBindsContainerInterface(): void {
		$this->app->register( FrameworkServiceProvider::class );

		$resolved = $this->app->make( ContainerInterface::class );

		$this->assertSame( $this->app, $resolved );
	}

	public function testRegisterBindsContainerClass(): void {
		$this->app->register( FrameworkServiceProvider::class );

		$resolved = $this->app->make( Container::class );

		$this->assertSame( $this->app, $resolved );
	}

	public function testContainerInterfaceIsSingleton(): void {
		$this->app->register( FrameworkServiceProvider::class );

		$a = $this->app->make( ContainerInterface::class );
		$b = $this->app->make( ContainerInterface::class );

		$this->assertSame( $a, $b );
	}

	public function testBootDoesNotThrow(): void {
		$provider = new FrameworkServiceProvider( $this->app );

		$provider->register();
		$provider->boot();

		$this->assertTrue( true );
	}

	public function testIsNotDeferred(): void {
		$provider = new FrameworkServiceProvider( $this->app );

		$this->assertFalse( $provider->defer );
	}
}
