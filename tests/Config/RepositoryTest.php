<?php

declare(strict_types=1);

namespace WPFlint\Tests\Config;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Config\Repository;

/**
 * @covers \WPFlint\Config\Repository
 */
class RepositoryTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Constructor
	// ---------------------------------------------------------------

	public function testConstructorAcceptsItems(): void {
		$repo = new Repository( array( 'app' => array( 'debug' => true ) ) );

		$this->assertSame( array( 'app' => array( 'debug' => true ) ), $repo->all() );
	}

	public function testConstructorDefaultsToEmptyArray(): void {
		$repo = new Repository();

		$this->assertSame( array(), $repo->all() );
	}

	// ---------------------------------------------------------------
	// get()
	// ---------------------------------------------------------------

	public function testGetTopLevelKey(): void {
		$repo = new Repository( array( 'name' => 'wpflint' ) );

		$this->assertSame( 'wpflint', $repo->get( 'name' ) );
	}

	public function testGetNestedDotNotation(): void {
		$repo = new Repository( array(
			'app' => array(
				'debug' => true,
				'db'    => array( 'host' => 'localhost' ),
			),
		) );

		$this->assertTrue( $repo->get( 'app.debug' ) );
		$this->assertSame( 'localhost', $repo->get( 'app.db.host' ) );
	}

	public function testGetReturnsDefaultWhenKeyMissing(): void {
		$repo = new Repository();

		$this->assertNull( $repo->get( 'missing' ) );
		$this->assertSame( 'fallback', $repo->get( 'missing', 'fallback' ) );
	}

	public function testGetReturnsDefaultForMissingNestedKey(): void {
		$repo = new Repository( array( 'app' => array( 'debug' => true ) ) );

		$this->assertSame( 'nope', $repo->get( 'app.nonexistent', 'nope' ) );
		$this->assertSame( 'nope', $repo->get( 'app.debug.deep', 'nope' ) );
	}

	public function testGetReturnsNullValueNotDefault(): void {
		$repo = new Repository( array( 'key' => null ) );

		$this->assertNull( $repo->get( 'key', 'default' ) );
	}

	public function testGetReturnsFalseValueNotDefault(): void {
		$repo = new Repository( array( 'key' => false ) );

		$this->assertFalse( $repo->get( 'key', 'default' ) );
	}

	// ---------------------------------------------------------------
	// set()
	// ---------------------------------------------------------------

	public function testSetTopLevelKey(): void {
		$repo = new Repository();
		$repo->set( 'name', 'wpflint' );

		$this->assertSame( 'wpflint', $repo->get( 'name' ) );
	}

	public function testSetNestedDotNotation(): void {
		$repo = new Repository();
		$repo->set( 'app.debug', true );

		$this->assertTrue( $repo->get( 'app.debug' ) );
		$this->assertSame( array( 'debug' => true ), $repo->get( 'app' ) );
	}

	public function testSetDeeplyNestedCreatesIntermediateArrays(): void {
		$repo = new Repository();
		$repo->set( 'a.b.c.d', 'deep' );

		$this->assertSame( 'deep', $repo->get( 'a.b.c.d' ) );
	}

	public function testSetOverwritesExistingValue(): void {
		$repo = new Repository( array( 'key' => 'old' ) );
		$repo->set( 'key', 'new' );

		$this->assertSame( 'new', $repo->get( 'key' ) );
	}

	public function testSetOverwritesNestedValue(): void {
		$repo = new Repository( array( 'app' => array( 'debug' => false ) ) );
		$repo->set( 'app.debug', true );

		$this->assertTrue( $repo->get( 'app.debug' ) );
	}

	public function testSetOverwritesScalarWithArray(): void {
		$repo = new Repository( array( 'app' => 'string' ) );
		$repo->set( 'app.debug', true );

		$this->assertTrue( $repo->get( 'app.debug' ) );
	}

	// ---------------------------------------------------------------
	// has()
	// ---------------------------------------------------------------

	public function testHasReturnsTrueForExistingKey(): void {
		$repo = new Repository( array( 'key' => 'value' ) );

		$this->assertTrue( $repo->has( 'key' ) );
	}

	public function testHasReturnsTrueForNestedKey(): void {
		$repo = new Repository( array( 'app' => array( 'debug' => true ) ) );

		$this->assertTrue( $repo->has( 'app.debug' ) );
	}

	public function testHasReturnsFalseForMissingKey(): void {
		$repo = new Repository();

		$this->assertFalse( $repo->has( 'missing' ) );
	}

	public function testHasReturnsFalseForMissingNestedKey(): void {
		$repo = new Repository( array( 'app' => array() ) );

		$this->assertFalse( $repo->has( 'app.debug' ) );
	}

	public function testHasReturnsTrueForNullValue(): void {
		$repo = new Repository( array( 'key' => null ) );

		$this->assertTrue( $repo->has( 'key' ) );
	}

	// ---------------------------------------------------------------
	// all()
	// ---------------------------------------------------------------

	public function testAllReturnsFullArray(): void {
		$items = array(
			'app'  => array( 'debug' => true ),
			'name' => 'wpflint',
		);

		$repo = new Repository( $items );

		$this->assertSame( $items, $repo->all() );
	}

	// ---------------------------------------------------------------
	// push()
	// ---------------------------------------------------------------

	public function testPushAppendsToExistingArray(): void {
		$repo = new Repository( array( 'items' => array( 'a', 'b' ) ) );
		$repo->push( 'items', 'c' );

		$this->assertSame( array( 'a', 'b', 'c' ), $repo->get( 'items' ) );
	}

	public function testPushCreatesArrayIfKeyMissing(): void {
		$repo = new Repository();
		$repo->push( 'items', 'first' );

		$this->assertSame( array( 'first' ), $repo->get( 'items' ) );
	}

	public function testPushWorksWithDotNotation(): void {
		$repo = new Repository( array( 'app' => array( 'providers' => array( 'A' ) ) ) );
		$repo->push( 'app.providers', 'B' );

		$this->assertSame( array( 'A', 'B' ), $repo->get( 'app.providers' ) );
	}

	// ---------------------------------------------------------------
	// env()
	// ---------------------------------------------------------------

	public function testEnvReturnsDefinedConstant(): void {
		$this->assertSame( '/tmp/wordpress/', Repository::env( 'ABSPATH', 'fallback' ) );
	}

	public function testEnvReturnsEnvVariable(): void {
		$_ENV['WPFLINT_TEST_VAR'] = 'from_env';

		WP_Mock::userFunction( 'wp_unslash' )->once()->andReturnUsing(
			function ( $value ) {
				return $value;
			}
		);
		WP_Mock::userFunction( 'sanitize_text_field' )->once()->andReturnUsing(
			function ( $value ) {
				return $value;
			}
		);

		$this->assertSame( 'from_env', Repository::env( 'WPFLINT_TEST_VAR' ) );

		unset( $_ENV['WPFLINT_TEST_VAR'] );
	}

	public function testEnvReturnsDefaultWhenNotFound(): void {
		$this->assertSame( 'default', Repository::env( 'TOTALLY_UNDEFINED_CONST_XYZ', 'default' ) );
	}

	public function testEnvReturnsNullWhenNotFoundAndNoDefault(): void {
		$this->assertNull( Repository::env( 'TOTALLY_UNDEFINED_CONST_XYZ' ) );
	}
}
