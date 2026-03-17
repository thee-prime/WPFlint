<?php

declare(strict_types=1);

namespace WPFlint\Tests\Database\Migrations;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Database\Migrations\MigrationRepository;

/**
 * @covers \WPFlint\Database\Migrations\MigrationRepository
 */
class MigrationRepositoryTest extends TestCase {

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
	// create_repository()
	// ---------------------------------------------------------------

	public function testCreateRepositoryCallsSchemaCreate(): void {
		$this->wpdb->shouldReceive( 'get_charset_collate' )
			->once()
			->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

		WP_Mock::userFunction( 'dbDelta', array(
			'times' => 1,
			'args'  => array( WP_Mock\Functions::type( 'string' ) ),
		) );

		$repo = new MigrationRepository( 'my-plugin' );
		$repo->create_repository();

		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// repository_exists()
	// ---------------------------------------------------------------

	public function testRepositoryExistsReturnsTrueWhenTableExists(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->with( 'SHOW TABLES LIKE %s', 'wp_wpflint_migrations' )
			->andReturn( "SHOW TABLES LIKE 'wp_wpflint_migrations'" );

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 'wp_wpflint_migrations' );

		$repo = new MigrationRepository( 'my-plugin' );

		$this->assertTrue( $repo->repository_exists() );
	}

	public function testRepositoryExistsReturnsFalseWhenTableMissing(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->with( 'SHOW TABLES LIKE %s', 'wp_wpflint_migrations' )
			->andReturn( "SHOW TABLES LIKE 'wp_wpflint_migrations'" );

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( null );

		$repo = new MigrationRepository( 'my-plugin' );

		$this->assertFalse( $repo->repository_exists() );
	}

	// ---------------------------------------------------------------
	// get_ran()
	// ---------------------------------------------------------------

	public function testGetRanReturnsArrayOfMigrationNames(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared query' );

		$this->wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array( 'CreateUsersTable', 'CreateOrdersTable' ) );

		$repo   = new MigrationRepository( 'my-plugin' );
		$result = $repo->get_ran();

		$this->assertSame( array( 'CreateUsersTable', 'CreateOrdersTable' ), $result );
	}

	public function testGetRanReturnsEmptyArrayWhenNoneRun(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared query' );

		$this->wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array() );

		$repo   = new MigrationRepository( 'my-plugin' );
		$result = $repo->get_ran();

		$this->assertSame( array(), $result );
	}

	// ---------------------------------------------------------------
	// get_last_batch_number()
	// ---------------------------------------------------------------

	public function testGetLastBatchNumberReturnsInt(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared query' );

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '3' );

		$repo   = new MigrationRepository( 'my-plugin' );
		$result = $repo->get_last_batch_number();

		$this->assertSame( 3, $result );
	}

	public function testGetLastBatchNumberReturnsZeroWhenEmpty(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared query' );

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( null );

		$repo   = new MigrationRepository( 'my-plugin' );
		$result = $repo->get_last_batch_number();

		$this->assertSame( 0, $result );
	}

	// ---------------------------------------------------------------
	// get_last_batch()
	// ---------------------------------------------------------------

	public function testGetLastBatchReturnsMigrationsFromHighestBatch(): void {
		// First call: get_last_batch_number via get_var.
		$this->wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturn( 'prepared query' );

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '2' );

		$row            = new \stdClass();
		$row->migration = 'CreateOrdersTable';
		$row->batch     = 2;

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( $row ) );

		$repo   = new MigrationRepository( 'my-plugin' );
		$result = $repo->get_last_batch();

		$this->assertCount( 1, $result );
		$this->assertSame( 'CreateOrdersTable', $result[0]->migration );
	}

	public function testGetLastBatchReturnsEmptyWhenNoBatches(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared query' );

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( null );

		$repo   = new MigrationRepository( 'my-plugin' );
		$result = $repo->get_last_batch();

		$this->assertSame( array(), $result );
	}

	// ---------------------------------------------------------------
	// log()
	// ---------------------------------------------------------------

	public function testLogInsertsRow(): void {
		WP_Mock::userFunction( 'current_time', array(
			'times'  => 1,
			'args'   => array( 'mysql' ),
			'return' => '2026-03-17 12:00:00',
		) );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_wpflint_migrations',
				array(
					'migration'   => 'CreateOrdersTable',
					'plugin_slug' => 'my-plugin',
					'batch'       => 1,
					'created_at'  => '2026-03-17 12:00:00',
				),
				array( '%s', '%s', '%d', '%s' )
			);

		$repo = new MigrationRepository( 'my-plugin' );
		$repo->log( 'CreateOrdersTable', 1 );

		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// delete()
	// ---------------------------------------------------------------

	public function testDeleteRemovesRow(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with(
				'wp_wpflint_migrations',
				array(
					'migration'   => 'CreateOrdersTable',
					'plugin_slug' => 'my-plugin',
				),
				array( '%s', '%s' )
			);

		$repo = new MigrationRepository( 'my-plugin' );
		$repo->delete( 'CreateOrdersTable' );

		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// get_all()
	// ---------------------------------------------------------------

	public function testGetAllReturnsAllRowsForPluginSlug(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared query' );

		$row1             = new \stdClass();
		$row1->migration  = 'CreateUsersTable';
		$row1->batch      = 1;
		$row1->created_at = '2026-03-17 12:00:00';

		$row2             = new \stdClass();
		$row2->migration  = 'CreateOrdersTable';
		$row2->batch      = 2;
		$row2->created_at = '2026-03-17 13:00:00';

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( $row1, $row2 ) );

		$repo   = new MigrationRepository( 'my-plugin' );
		$result = $repo->get_all();

		$this->assertCount( 2, $result );
		$this->assertSame( 'CreateUsersTable', $result[0]->migration );
		$this->assertSame( 'CreateOrdersTable', $result[1]->migration );
	}
}
