<?php

declare(strict_types=1);

namespace WPFlint\Tests\Database\Migrations;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Database\Migrations\Migration;
use WPFlint\Database\Schema\Schema;

/**
 * @covers \WPFlint\Database\Migrations\Migration
 */
class MigrationTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function testUpIsCallableOnConcreteSubclass(): void {
		$migration = $this->get_concrete_migration();
		$migration->up();

		$this->assertTrue( $migration->up_called );
	}

	public function testDownIsCallableOnConcreteSubclass(): void {
		$migration = $this->get_concrete_migration();
		$migration->down();

		$this->assertTrue( $migration->down_called );
	}

	public function testSchemaReturnsSchemaInstance(): void {
		$migration = $this->get_concrete_migration();

		$this->assertInstanceOf( Schema::class, $migration->schema() );
	}

	public function testSchemaReturnsNewInstanceEachCall(): void {
		$migration = $this->get_concrete_migration();

		$schema1 = $migration->schema();
		$schema2 = $migration->schema();

		$this->assertNotSame( $schema1, $schema2 );
	}

	/**
	 * Create a concrete migration for testing.
	 *
	 * @return Migration
	 */
	private function get_concrete_migration(): Migration {
		return new class extends Migration {
			/** @var bool */
			public bool $up_called = false;

			/** @var bool */
			public bool $down_called = false;

			public function up(): void {
				$this->up_called = true;
			}

			public function down(): void {
				$this->down_called = true;
			}
		};
	}
}
