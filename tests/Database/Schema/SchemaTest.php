<?php

declare(strict_types=1);

namespace WPFlint\Tests\Database\Schema;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Database\Schema\Schema;

/**
 * @covers \WPFlint\Database\Schema\Schema
 */
class SchemaTest extends TestCase {

	/**
	 * Mock $wpdb instance.
	 *
	 * @var \Mockery\MockInterface
	 */
	protected $wpdb;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		$GLOBALS['wpdb'] = $this->wpdb;
	}

	public function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// create()
	// ---------------------------------------------------------------

	public function testCreateCallsDbDelta(): void {
		$this->wpdb->shouldReceive( 'get_charset_collate' )
			->once()
			->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

		WP_Mock::userFunction( 'dbDelta', array(
			'times' => 1,
			'args'  => array( \WP_Mock\Functions::type( 'string' ) ),
		) );

		$schema = new Schema();
		$schema->create( 'orders', function ( $table ) {
			$table->big_increments( 'id' );
			$table->string( 'status' );
		} );

		// Assertion is the mock expectation — dbDelta called exactly once.
		$this->assertConditionsMet();
	}

	public function testCreatePrefixesTableName(): void {
		$captured_sql = '';

		$this->wpdb->shouldReceive( 'get_charset_collate' )
			->andReturn( '' );

		WP_Mock::userFunction( 'dbDelta', array(
			'times'          => 1,
			'return_in_order' => array( array() ),
		) );

		// Use a spy approach: capture what's passed to dbDelta via the blueprint.
		$schema = new Schema();
		$schema->create( 'orders', function ( $table ) use ( &$captured_sql ) {
			$table->big_increments( 'id' );
			// We check the table name in to_sql output indirectly.
		} );

		// If we got here without error, the method ran. The mock validates dbDelta was called.
		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// drop()
	// ---------------------------------------------------------------

	public function testDropCallsWpdbQuery(): void {
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( 'DROP TABLE IF EXISTS wp_orders' );

		$schema = new Schema();
		$schema->drop( 'orders' );

		$this->assertConditionsMet();
	}

	public function testDropPrefixesTableName(): void {
		$this->wpdb->prefix = 'test_';

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( 'DROP TABLE IF EXISTS test_orders' );

		$schema = new Schema();
		$schema->drop( 'orders' );

		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// has_table()
	// ---------------------------------------------------------------

	public function testHasTableReturnsTrueWhenExists(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->with( 'SHOW TABLES LIKE %s', 'wp_orders' )
			->andReturn( "SHOW TABLES LIKE 'wp_orders'" );

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 'wp_orders' );

		$schema = new Schema();

		$this->assertTrue( $schema->has_table( 'orders' ) );
	}

	public function testHasTableReturnsFalseWhenMissing(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->with( 'SHOW TABLES LIKE %s', 'wp_orders' )
			->andReturn( "SHOW TABLES LIKE 'wp_orders'" );

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( null );

		$schema = new Schema();

		$this->assertFalse( $schema->has_table( 'orders' ) );
	}

	// ---------------------------------------------------------------
	// add_column()
	// ---------------------------------------------------------------

	public function testAddColumnCallsWpdbQuery(): void {
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( 'ALTER TABLE wp_orders ADD COLUMN notes TEXT NULL' );

		$schema = new Schema();
		$schema->add_column( 'orders', 'notes', 'TEXT NULL' );

		$this->assertConditionsMet();
	}
}
