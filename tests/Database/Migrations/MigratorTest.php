<?php

declare(strict_types=1);

namespace WPFlint\Tests\Database\Migrations;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Database\Migrations\Migration;
use WPFlint\Database\Migrations\MigrationRepository;
use WPFlint\Database\Migrations\Migrator;
use RuntimeException;

/**
 * @covers \WPFlint\Database\Migrations\Migrator
 */
class MigratorTest extends TestCase {

	/**
	 * Mock repository.
	 *
	 * @var \Mockery\MockInterface|MigrationRepository
	 */
	protected $repository;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->repository = Mockery::mock( MigrationRepository::class );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// run()
	// ---------------------------------------------------------------

	public function testRunCallsUpOnPendingMigrations(): void {
		$migration_class = $this->create_migration_class( 'TestMigrationA' );

		$this->repository->shouldReceive( 'get_ran' )
			->once()
			->andReturn( array() );

		$this->repository->shouldReceive( 'get_last_batch_number' )
			->once()
			->andReturn( 0 );

		$this->repository->shouldReceive( 'log' )
			->once()
			->with( $migration_class, 1 );

		$migrator = new Migrator( $this->repository, array( $migration_class ) );
		$result   = $migrator->run();

		$this->assertSame( array( $migration_class ), $result );
	}

	public function testRunReturnsEmptyArrayWhenNothingPending(): void {
		$migration_class = $this->create_migration_class( 'TestMigrationB' );

		$this->repository->shouldReceive( 'get_ran' )
			->once()
			->andReturn( array( $migration_class ) );

		$migrator = new Migrator( $this->repository, array( $migration_class ) );
		$result   = $migrator->run();

		$this->assertSame( array(), $result );
	}

	// ---------------------------------------------------------------
	// rollback()
	// ---------------------------------------------------------------

	public function testRollbackCallsDownOnLastBatch(): void {
		$migration_class = $this->create_migration_class( 'TestMigrationC' );

		$row            = new \stdClass();
		$row->migration = $migration_class;
		$row->batch     = 1;

		$this->repository->shouldReceive( 'get_last_batch' )
			->once()
			->andReturn( array( $row ) );

		$this->repository->shouldReceive( 'delete' )
			->once()
			->with( $migration_class );

		$migrator = new Migrator( $this->repository, array( $migration_class ) );
		$result   = $migrator->rollback();

		$this->assertSame( array( $migration_class ), $result );
	}

	public function testRollbackWithMultipleSteps(): void {
		$migration_a = $this->create_migration_class( 'TestMigrationD' );
		$migration_b = $this->create_migration_class( 'TestMigrationE' );

		$row_a            = new \stdClass();
		$row_a->migration = $migration_b;
		$row_a->batch     = 2;

		$row_b            = new \stdClass();
		$row_b->migration = $migration_a;
		$row_b->batch     = 1;

		$this->repository->shouldReceive( 'get_last_batch' )
			->twice()
			->andReturn( array( $row_a ), array( $row_b ) );

		$this->repository->shouldReceive( 'delete' )
			->twice();

		$migrator = new Migrator( $this->repository, array( $migration_a, $migration_b ) );
		$result   = $migrator->rollback( 2 );

		$this->assertSame( array( $migration_b, $migration_a ), $result );
	}

	// ---------------------------------------------------------------
	// fresh()
	// ---------------------------------------------------------------

	public function testFreshThrowsExceptionOutsideWpCli(): void {
		// Ensure WP_CLI is not defined (it shouldn't be in tests).
		if ( defined( 'WP_CLI' ) ) {
			$this->markTestSkipped( 'WP_CLI is defined, cannot test guard.' );
		}

		WP_Mock::userFunction( '__', array(
			'return' => function ( $text ) {
				return $text;
			},
		) );

		$migrator = new Migrator( $this->repository, array() );

		$this->expectException( RuntimeException::class );
		$migrator->fresh();
	}

	// ---------------------------------------------------------------
	// get_status()
	// ---------------------------------------------------------------

	public function testGetStatusReturnsCorrectMapping(): void {
		$migration_a = 'App\\Migrations\\CreateUsersTable';
		$migration_b = 'App\\Migrations\\CreateOrdersTable';

		$this->repository->shouldReceive( 'get_ran' )
			->once()
			->andReturn( array( $migration_a ) );

		$migrator = new Migrator( $this->repository, array( $migration_a, $migration_b ) );
		$result   = $migrator->get_status();

		$this->assertSame(
			array(
				array( 'migration' => $migration_a, 'ran' => true ),
				array( 'migration' => $migration_b, 'ran' => false ),
			),
			$result
		);
	}

	// ---------------------------------------------------------------
	// get_pending()
	// ---------------------------------------------------------------

	public function testGetPendingReturnsOnlyUnrunMigrations(): void {
		$migration_a = 'App\\Migrations\\CreateUsersTable';
		$migration_b = 'App\\Migrations\\CreateOrdersTable';

		$this->repository->shouldReceive( 'get_ran' )
			->once()
			->andReturn( array( $migration_a ) );

		$migrator = new Migrator( $this->repository, array( $migration_a, $migration_b ) );
		$result   = $migrator->get_pending();

		$this->assertSame( array( $migration_b ), $result );
	}

	// ---------------------------------------------------------------
	// resolve()
	// ---------------------------------------------------------------

	public function testResolveReturnsMigrationInstance(): void {
		$migration_class = $this->create_migration_class( 'TestMigrationF' );

		$migrator = new Migrator( $this->repository );
		$instance = $migrator->resolve( $migration_class );

		$this->assertInstanceOf( Migration::class, $instance );
	}

	// ---------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------

	/**
	 * Create a unique concrete Migration class for testing.
	 *
	 * @param string $name Unique class name.
	 * @return string Fully qualified class name.
	 */
	private function create_migration_class( string $name ): string {
		$fqcn = 'WPFlint\\Tests\\Database\\Migrations\\' . $name;

		if ( ! class_exists( $fqcn ) ) {
			eval( "namespace WPFlint\\Tests\\Database\\Migrations; class {$name} extends \\WPFlint\\Database\\Migrations\\Migration { public function up(): void {} public function down(): void {} }" );
		}

		return $fqcn;
	}
}
