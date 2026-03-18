<?php

declare(strict_types=1);

namespace WPFlint\Tests\Cache;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Cache\CacheManager;
use WPFlint\Cache\Drivers\ArrayDriver;
use WPFlint\Cache\Drivers\TransientDriver;
use WPFlint\Cache\TaggedCache;

/**
 * @covers \WPFlint\Cache\CacheManager
 */
class CacheManagerTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function testDefaultDriverIsTransient(): void {
		$manager = new CacheManager();
		$this->assertInstanceOf( TransientDriver::class, $manager->get_driver() );
	}

	public function testArrayDriver(): void {
		$manager = new CacheManager( 'array' );
		$this->assertInstanceOf( ArrayDriver::class, $manager->get_driver() );
	}

	public function testGetAndPut(): void {
		$manager = new CacheManager( 'array' );
		$manager->put( 'key', 'value', 300 );
		$this->assertSame( 'value', $manager->get( 'key' ) );
	}

	public function testGetReturnsDefault(): void {
		$manager = new CacheManager( 'array' );
		$this->assertSame( 'default', $manager->get( 'missing', 'default' ) );
	}

	public function testForget(): void {
		$manager = new CacheManager( 'array' );
		$manager->put( 'key', 'value' );
		$manager->forget( 'key' );
		$this->assertNull( $manager->get( 'key' ) );
	}

	public function testHas(): void {
		$manager = new CacheManager( 'array' );
		$manager->put( 'key', 'value' );
		$this->assertTrue( $manager->has( 'key' ) );
		$this->assertFalse( $manager->has( 'missing' ) );
	}

	public function testFlush(): void {
		$manager = new CacheManager( 'array' );
		$manager->put( 'a', 1 );
		$manager->put( 'b', 2 );
		$manager->flush();
		$this->assertFalse( $manager->has( 'a' ) );
		$this->assertFalse( $manager->has( 'b' ) );
	}

	public function testRememberReturnsCached(): void {
		$manager = new CacheManager( 'array' );
		$manager->put( 'key', 'cached' );

		$result = $manager->remember( 'key', 300, function () {
			return 'fresh';
		} );

		$this->assertSame( 'cached', $result );
	}

	public function testRememberCallsCallbackWhenMissing(): void {
		$manager = new CacheManager( 'array' );

		$result = $manager->remember( 'key', 300, function () {
			return 'generated';
		} );

		$this->assertSame( 'generated', $result );
		$this->assertSame( 'generated', $manager->get( 'key' ) );
	}

	// ---------------------------------------------------------------
	// fresh() — bypass cache reads
	// ---------------------------------------------------------------

	public function testFreshGetBypassesCache(): void {
		$manager = new CacheManager( 'array' );
		$manager->put( 'key', 'cached' );

		$result = $manager->fresh()->get( 'key', 'default' );
		$this->assertSame( 'default', $result );
	}

	public function testFreshHasReturnsFalse(): void {
		$manager = new CacheManager( 'array' );
		$manager->put( 'key', 'cached' );

		$this->assertFalse( $manager->fresh()->has( 'key' ) );
	}

	public function testFreshRememberBypassesAndStores(): void {
		$manager = new CacheManager( 'array' );
		$manager->put( 'key', 'stale' );

		$result = $manager->fresh()->remember( 'key', 300, function () {
			return 'fresh_value';
		} );

		$this->assertSame( 'fresh_value', $result );
		$this->assertSame( 'fresh_value', $manager->get( 'key' ) );
	}

	public function testFreshResetsAfterOneCall(): void {
		$manager = new CacheManager( 'array' );
		$manager->put( 'key', 'cached' );

		$manager->fresh()->get( 'key' );

		// Second call should read from cache again.
		$this->assertSame( 'cached', $manager->get( 'key' ) );
	}

	// ---------------------------------------------------------------
	// tags()
	// ---------------------------------------------------------------

	public function testTagsReturnsTaggedCache(): void {
		$manager = new CacheManager( 'array' );
		$tagged  = $manager->tags( 'orders' );

		$this->assertInstanceOf( TaggedCache::class, $tagged );
	}

	public function testTagsAcceptsArray(): void {
		$manager = new CacheManager( 'array' );
		$tagged  = $manager->tags( array( 'orders', 'users' ) );

		$this->assertInstanceOf( TaggedCache::class, $tagged );
	}

	public function testTagsSharesSameDriver(): void {
		$manager = new CacheManager( 'array' );
		$manager->put( 'shared_key', 'value' );

		$tagged = $manager->tags( 'tag' );
		$this->assertSame( 'value', $tagged->get( 'shared_key' ) );
	}
}
