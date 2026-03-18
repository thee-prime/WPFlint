<?php

declare(strict_types=1);

namespace WPFlint\Tests\Cache;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Cache\Drivers\ArrayDriver;

/**
 * @covers \WPFlint\Cache\Drivers\ArrayDriver
 */
class ArrayDriverTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function testGetReturnsNullForMissing(): void {
		$driver = new ArrayDriver();
		$this->assertNull( $driver->get( 'missing' ) );
	}

	public function testGetReturnsDefault(): void {
		$driver = new ArrayDriver();
		$this->assertSame( 'fallback', $driver->get( 'missing', 'fallback' ) );
	}

	public function testPutAndGet(): void {
		$driver = new ArrayDriver();
		$driver->put( 'key', 'value' );
		$this->assertSame( 'value', $driver->get( 'key' ) );
	}

	public function testPutReturnsTrue(): void {
		$driver = new ArrayDriver();
		$this->assertTrue( $driver->put( 'key', 'value' ) );
	}

	public function testForgetRemovesKey(): void {
		$driver = new ArrayDriver();
		$driver->put( 'key', 'value' );
		$driver->forget( 'key' );
		$this->assertNull( $driver->get( 'key' ) );
	}

	public function testForgetReturnsTrue(): void {
		$driver = new ArrayDriver();
		$this->assertTrue( $driver->forget( 'nonexistent' ) );
	}

	public function testHasReturnsTrueWhenExists(): void {
		$driver = new ArrayDriver();
		$driver->put( 'key', 'value' );
		$this->assertTrue( $driver->has( 'key' ) );
	}

	public function testHasReturnsFalseWhenMissing(): void {
		$driver = new ArrayDriver();
		$this->assertFalse( $driver->has( 'missing' ) );
	}

	public function testFlushClearsAll(): void {
		$driver = new ArrayDriver();
		$driver->put( 'a', 1 );
		$driver->put( 'b', 2 );
		$driver->flush();
		$this->assertFalse( $driver->has( 'a' ) );
		$this->assertFalse( $driver->has( 'b' ) );
	}

	public function testFlushReturnsTrue(): void {
		$driver = new ArrayDriver();
		$this->assertTrue( $driver->flush() );
	}

	public function testRememberReturnsCachedValue(): void {
		$driver = new ArrayDriver();
		$driver->put( 'key', 'cached' );

		$result = $driver->remember( 'key', 300, function () {
			return 'fresh';
		} );

		$this->assertSame( 'cached', $result );
	}

	public function testRememberCallsCallbackWhenMissing(): void {
		$driver = new ArrayDriver();

		$result = $driver->remember( 'key', 300, function () {
			return 'generated';
		} );

		$this->assertSame( 'generated', $result );
		$this->assertSame( 'generated', $driver->get( 'key' ) );
	}

	public function testGetStoreReturnsInternalArray(): void {
		$driver = new ArrayDriver();
		$driver->put( 'a', 1 );
		$driver->put( 'b', 2 );

		$this->assertSame( array( 'a' => 1, 'b' => 2 ), $driver->get_store() );
	}
}
