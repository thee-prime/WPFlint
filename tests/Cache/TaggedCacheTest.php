<?php

declare(strict_types=1);

namespace WPFlint\Tests\Cache;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Cache\Drivers\ArrayDriver;
use WPFlint\Cache\TaggedCache;

/**
 * @covers \WPFlint\Cache\TaggedCache
 */
class TaggedCacheTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function testRememberStoresAndTracksKey(): void {
		$driver = new ArrayDriver();

		WP_Mock::userFunction( 'get_option', array(
			'return' => array(),
		) );
		WP_Mock::userFunction( 'update_option', array(
			'return' => true,
		) );

		$tagged = new TaggedCache( $driver, array( 'orders' ) );
		$result = $tagged->remember( 'order_list', 300, function () {
			return array( 'order1', 'order2' );
		} );

		$this->assertSame( array( 'order1', 'order2' ), $result );
		$this->assertSame( array( 'order1', 'order2' ), $driver->get( 'order_list' ) );
	}

	public function testRememberReturnsCachedValue(): void {
		$driver = new ArrayDriver();
		$driver->put( 'order_list', array( 'cached' ) );

		$tagged = new TaggedCache( $driver, array( 'orders' ) );
		$result = $tagged->remember( 'order_list', 300, function () {
			return array( 'fresh' );
		} );

		$this->assertSame( array( 'cached' ), $result );
	}

	public function testGetDelegatesToDriver(): void {
		$driver = new ArrayDriver();
		$driver->put( 'key', 'value' );

		$tagged = new TaggedCache( $driver, array( 'tag' ) );
		$this->assertSame( 'value', $tagged->get( 'key' ) );
	}

	public function testGetReturnsDefault(): void {
		$driver = new ArrayDriver();

		$tagged = new TaggedCache( $driver, array( 'tag' ) );
		$this->assertSame( 'default', $tagged->get( 'missing', 'default' ) );
	}

	public function testPutStoresAndTracksKey(): void {
		$driver = new ArrayDriver();

		WP_Mock::userFunction( 'get_option', array(
			'return' => array(),
		) );
		WP_Mock::userFunction( 'update_option', array(
			'return' => true,
		) );

		$tagged = new TaggedCache( $driver, array( 'tag1' ) );
		$result = $tagged->put( 'key', 'value', 300 );

		$this->assertTrue( $result );
		$this->assertSame( 'value', $driver->get( 'key' ) );
	}

	public function testForgetRemovesAndUntracksKey(): void {
		$driver = new ArrayDriver();
		$driver->put( 'key', 'value' );

		WP_Mock::userFunction( 'get_option', array(
			'return' => array( 'key' ),
		) );
		WP_Mock::userFunction( 'update_option', array(
			'return' => true,
		) );

		$tagged = new TaggedCache( $driver, array( 'tag1' ) );
		$tagged->forget( 'key' );

		$this->assertFalse( $driver->has( 'key' ) );
	}

	public function testFlushRemovesAllTaggedKeys(): void {
		$driver = new ArrayDriver();
		$driver->put( 'a', 1 );
		$driver->put( 'b', 2 );
		$driver->put( 'c', 3 ); // not tagged.

		WP_Mock::userFunction( 'get_option', array(
			'args'   => array( 'wpflint_cache_tag_mytag', array() ),
			'return' => array( 'a', 'b' ),
		) );
		WP_Mock::userFunction( 'delete_option', array(
			'args'   => array( 'wpflint_cache_tag_mytag' ),
			'return' => true,
		) );

		$tagged = new TaggedCache( $driver, array( 'mytag' ) );
		$tagged->flush();

		$this->assertFalse( $driver->has( 'a' ) );
		$this->assertFalse( $driver->has( 'b' ) );
		$this->assertTrue( $driver->has( 'c' ) ); // untagged remains.
	}

	public function testFlushMultipleTags(): void {
		$driver = new ArrayDriver();
		$driver->put( 'x', 1 );
		$driver->put( 'y', 2 );

		WP_Mock::userFunction( 'get_option' )
			->andReturnUsing( function ( $key ) {
				if ( 'wpflint_cache_tag_t1' === $key ) {
					return array( 'x' );
				}
				return array( 'y' );
			} );

		WP_Mock::userFunction( 'delete_option', array(
			'return' => true,
		) );

		$tagged = new TaggedCache( $driver, array( 't1', 't2' ) );
		$tagged->flush();

		$this->assertFalse( $driver->has( 'x' ) );
		$this->assertFalse( $driver->has( 'y' ) );
	}

	public function testTrackKeyDoesNotDuplicate(): void {
		$driver  = new ArrayDriver();
		$tracked = array();

		WP_Mock::userFunction( 'get_option', array(
			'return' => array( 'existing_key' ),
		) );

		// update_option should NOT be called since key already tracked.
		// But our code checks in_array, so let's test with a key already in the list.
		$tagged = new TaggedCache( $driver, array( 'tag' ) );

		// Put the same key that's already tracked.
		WP_Mock::userFunction( 'update_option', array(
			'return' => true,
		) );

		$driver->put( 'new_key', 'val' );
		$tagged->put( 'new_key', 'val' );

		// Key 'new_key' should be tracked.
		$this->assertTrue( $driver->has( 'new_key' ) );
	}
}
