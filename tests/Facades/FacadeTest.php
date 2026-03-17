<?php

declare(strict_types=1);

namespace WPFlint\Tests\Facades;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Application;
use WPFlint\Config\Repository;
use WPFlint\Facades\Config;
use WPFlint\Facades\Facade;

/**
 * @covers \WPFlint\Facades\Facade
 * @covers \WPFlint\Facades\Config
 */
class FacadeTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		Application::clear_instance();
		Facade::clear_resolved_instances();
	}

	public function tearDown(): void {
		Facade::clear_resolved_instances();
		Application::clear_instance();
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Facade resolution
	// ---------------------------------------------------------------

	public function testFacadeResolvesFromContainer(): void {
		$app  = Application::get_instance();
		$repo = new Repository( array( 'key' => 'value' ) );

		$app->instance( 'config', $repo );

		$this->assertSame( 'value', Config::get( 'key' ) );
	}

	public function testFacadeForwardsMultipleMethods(): void {
		$app  = Application::get_instance();
		$repo = new Repository();

		$app->instance( 'config', $repo );

		Config::set( 'app.name', 'WPFlint' );

		$this->assertSame( 'WPFlint', Config::get( 'app.name' ) );
		$this->assertTrue( Config::has( 'app.name' ) );
		$this->assertFalse( Config::has( 'missing' ) );
	}

	public function testFacadeCachesResolvedInstance(): void {
		$app  = Application::get_instance();
		$repo = new Repository( array( 'x' => 1 ) );

		$app->instance( 'config', $repo );

		// First call resolves.
		Config::get( 'x' );

		// Replace in container — facade should still use cached instance.
		$app->instance( 'config', new Repository( array( 'x' => 2 ) ) );

		$this->assertSame( 1, Config::get( 'x' ) );
	}

	public function testClearResolvedInstancesAllowsReResolution(): void {
		$app  = Application::get_instance();
		$repo = new Repository( array( 'x' => 1 ) );

		$app->instance( 'config', $repo );

		Config::get( 'x' );

		// Replace and clear cache.
		$app->instance( 'config', new Repository( array( 'x' => 2 ) ) );
		Facade::clear_resolved_instances();

		$this->assertSame( 2, Config::get( 'x' ) );
	}

	// ---------------------------------------------------------------
	// Config facade specifically
	// ---------------------------------------------------------------

	public function testConfigFacadeAll(): void {
		$app   = Application::get_instance();
		$items = array( 'a' => 1, 'b' => 2 );

		$app->instance( 'config', new Repository( $items ) );

		$this->assertSame( $items, Config::all() );
	}

	public function testConfigFacadePush(): void {
		$app = Application::get_instance();
		$app->instance( 'config', new Repository( array( 'list' => array( 'one' ) ) ) );

		Config::push( 'list', 'two' );

		$this->assertSame( array( 'one', 'two' ), Config::get( 'list' ) );
	}
}
