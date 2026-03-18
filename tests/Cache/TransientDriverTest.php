<?php

declare(strict_types=1);

namespace WPFlint\Tests\Cache;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Cache\Drivers\TransientDriver;

/**
 * @covers \WPFlint\Cache\Drivers\TransientDriver
 */
class TransientDriverTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function testGetReturnsTransientValue(): void {
		WP_Mock::userFunction( 'get_transient', array(
			'args'   => array( 'wpflint_mykey' ),
			'return' => 'cached_value',
		) );

		$driver = new TransientDriver();
		$this->assertSame( 'cached_value', $driver->get( 'mykey' ) );
	}

	public function testGetReturnsDefaultWhenTransientFalse(): void {
		WP_Mock::userFunction( 'get_transient', array(
			'args'   => array( 'wpflint_mykey' ),
			'return' => false,
		) );

		$driver = new TransientDriver();
		$this->assertSame( 'default', $driver->get( 'mykey', 'default' ) );
	}

	public function testPutCallsSetTransient(): void {
		WP_Mock::userFunction( 'set_transient', array(
			'args'   => array( 'wpflint_mykey', 'value', 300 ),
			'return' => true,
		) );

		$driver = new TransientDriver();
		$this->assertTrue( $driver->put( 'mykey', 'value', 300 ) );
	}

	public function testForgetCallsDeleteTransient(): void {
		WP_Mock::userFunction( 'delete_transient', array(
			'args'   => array( 'wpflint_mykey' ),
			'return' => true,
		) );

		$driver = new TransientDriver();
		$this->assertTrue( $driver->forget( 'mykey' ) );
	}

	public function testHasReturnsTrueWhenTransientExists(): void {
		WP_Mock::userFunction( 'get_transient', array(
			'args'   => array( 'wpflint_mykey' ),
			'return' => 'something',
		) );

		$driver = new TransientDriver();
		$this->assertTrue( $driver->has( 'mykey' ) );
	}

	public function testHasReturnsFalseWhenTransientMissing(): void {
		WP_Mock::userFunction( 'get_transient', array(
			'args'   => array( 'wpflint_mykey' ),
			'return' => false,
		) );

		$driver = new TransientDriver();
		$this->assertFalse( $driver->has( 'mykey' ) );
	}

	public function testFlushReturnsTrue(): void {
		$driver = new TransientDriver();
		$this->assertTrue( $driver->flush() );
	}

	public function testRememberReturnsCachedValue(): void {
		WP_Mock::userFunction( 'get_transient', array(
			'args'   => array( 'wpflint_mykey' ),
			'return' => 'cached',
		) );

		$driver = new TransientDriver();
		$result = $driver->remember( 'mykey', 300, function () {
			return 'fresh';
		} );

		$this->assertSame( 'cached', $result );
	}

	public function testRememberStoresValueWhenMissing(): void {
		WP_Mock::userFunction( 'get_transient', array(
			'args'   => array( 'wpflint_mykey' ),
			'return' => false,
		) );

		WP_Mock::userFunction( 'set_transient', array(
			'args'   => array( 'wpflint_mykey', 'generated', 300 ),
			'return' => true,
		) );

		$driver = new TransientDriver();
		$result = $driver->remember( 'mykey', 300, function () {
			return 'generated';
		} );

		$this->assertSame( 'generated', $result );
	}

	public function testCustomPrefix(): void {
		WP_Mock::userFunction( 'get_transient', array(
			'args'   => array( 'myplugin_mykey' ),
			'return' => 'val',
		) );

		$driver = new TransientDriver( 'myplugin_' );
		$this->assertSame( 'val', $driver->get( 'mykey' ) );
	}
}
