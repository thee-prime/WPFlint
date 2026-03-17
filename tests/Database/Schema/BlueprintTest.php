<?php

declare(strict_types=1);

namespace WPFlint\Tests\Database\Schema;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Database\Schema\Blueprint;
use WPFlint\Database\Schema\ColumnDefinition;
use WPFlint\Database\Schema\ForeignKeyDefinition;

/**
 * @covers \WPFlint\Database\Schema\Blueprint
 * @covers \WPFlint\Database\Schema\ForeignKeyDefinition
 */
class BlueprintTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Column type methods
	// ---------------------------------------------------------------

	public function testBigIncrements(): void {
		$bp  = new Blueprint();
		$col = $bp->big_increments( 'id' );

		$this->assertInstanceOf( ColumnDefinition::class, $col );
		$this->assertSame( 'id', $bp->get_primary_key() );
		$this->assertStringContainsString( 'BIGINT UNSIGNED', $col->to_sql() );
		$this->assertStringContainsString( 'AUTO_INCREMENT', $col->to_sql() );
	}

	public function testString(): void {
		$bp  = new Blueprint();
		$col = $bp->string( 'name' );

		$this->assertSame( 'name  VARCHAR(255) NOT NULL', $col->to_sql() );
	}

	public function testStringCustomLength(): void {
		$bp  = new Blueprint();
		$col = $bp->string( 'code', 50 );

		$this->assertSame( 'code  VARCHAR(50) NOT NULL', $col->to_sql() );
	}

	public function testInteger(): void {
		$bp  = new Blueprint();
		$col = $bp->integer( 'quantity' );

		$this->assertSame( 'quantity  INT NOT NULL', $col->to_sql() );
	}

	public function testDecimal(): void {
		$bp  = new Blueprint();
		$col = $bp->decimal( 'price', 10, 2 );

		$this->assertSame( 'price  DECIMAL(10,2) NOT NULL', $col->to_sql() );
	}

	public function testDecimalDefaults(): void {
		$bp  = new Blueprint();
		$col = $bp->decimal( 'amount' );

		$this->assertSame( 'amount  DECIMAL(8,2) NOT NULL', $col->to_sql() );
	}

	public function testBoolean(): void {
		$bp  = new Blueprint();
		$col = $bp->boolean( 'active' );

		$this->assertSame( 'active  TINYINT(1) NOT NULL', $col->to_sql() );
	}

	public function testText(): void {
		$bp  = new Blueprint();
		$col = $bp->text( 'body' );

		$this->assertSame( 'body  TEXT NOT NULL', $col->to_sql() );
	}

	// ---------------------------------------------------------------
	// Timestamps and soft deletes
	// ---------------------------------------------------------------

	public function testTimestamps(): void {
		$bp = new Blueprint();
		$bp->timestamps();

		$columns = $bp->get_columns();

		$this->assertCount( 2, $columns );
		$this->assertSame( 'created_at  DATETIME NULL', $columns[0]->to_sql() );
		$this->assertSame( 'updated_at  DATETIME NULL', $columns[1]->to_sql() );
	}

	public function testSoftDeletes(): void {
		$bp = new Blueprint();
		$bp->soft_deletes();

		$columns = $bp->get_columns();

		$this->assertCount( 1, $columns );
		$this->assertSame( 'deleted_at  DATETIME NULL', $columns[0]->to_sql() );
	}

	// ---------------------------------------------------------------
	// Indexes
	// ---------------------------------------------------------------

	public function testIndex(): void {
		$bp = new Blueprint();
		$bp->string( 'email' );
		$bp->index( 'email' );

		$sql = $bp->to_sql( 'wp_users' );

		$this->assertStringContainsString( 'KEY idx_email (email)', $sql );
	}

	public function testCompositeIndex(): void {
		$bp = new Blueprint();
		$bp->string( 'first_name' );
		$bp->string( 'last_name' );
		$bp->index( array( 'first_name', 'last_name' ) );

		$sql = $bp->to_sql( 'wp_users' );

		$this->assertStringContainsString( 'KEY idx_first_name_last_name (first_name,last_name)', $sql );
	}

	public function testUnique(): void {
		$bp = new Blueprint();
		$bp->string( 'email' );
		$bp->unique( 'email' );

		$sql = $bp->to_sql( 'wp_users' );

		$this->assertStringContainsString( 'UNIQUE KEY uniq_email (email)', $sql );
	}

	// ---------------------------------------------------------------
	// Foreign keys
	// ---------------------------------------------------------------

	public function testForeignKey(): void {
		$bp = new Blueprint();
		$bp->integer( 'user_id' );
		$bp->foreign( 'user_id' )->references( 'id' )->on( 'wp_users' );

		$sql = $bp->to_sql( 'wp_orders' );

		$this->assertStringContainsString(
			'CONSTRAINT fk_wp_orders_user_id FOREIGN KEY (user_id) REFERENCES wp_users(id) ON DELETE CASCADE ON UPDATE CASCADE',
			$sql
		);
	}

	public function testForeignKeyCustomActions(): void {
		$bp = new Blueprint();
		$bp->integer( 'category_id' );
		$bp->foreign( 'category_id' )
			->references( 'id' )
			->on( 'wp_categories' )
			->on_delete( 'SET NULL' )
			->on_update( 'RESTRICT' );

		$sql = $bp->to_sql( 'wp_posts' );

		$this->assertStringContainsString( 'ON DELETE SET NULL', $sql );
		$this->assertStringContainsString( 'ON UPDATE RESTRICT', $sql );
	}

	// ---------------------------------------------------------------
	// Full to_sql() output
	// ---------------------------------------------------------------

	public function testFullCreateTableSql(): void {
		$bp = new Blueprint();
		$bp->big_increments( 'id' );
		$bp->string( 'status', 50 )->default( 'pending' );
		$bp->decimal( 'total', 10, 2 );
		$bp->timestamps();

		$sql = $bp->to_sql( 'wp_orders', 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );

		$this->assertStringContainsString( 'CREATE TABLE wp_orders', $sql );
		$this->assertStringContainsString( 'id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql );
		$this->assertStringContainsString( "status  VARCHAR(50) NOT NULL DEFAULT 'pending'", $sql );
		$this->assertStringContainsString( 'total  DECIMAL(10,2) NOT NULL', $sql );
		$this->assertStringContainsString( 'created_at  DATETIME NULL', $sql );
		$this->assertStringContainsString( 'updated_at  DATETIME NULL', $sql );
		// PRIMARY KEY with two spaces before the opening paren.
		$this->assertStringContainsString( 'PRIMARY KEY  (id)', $sql );
		$this->assertStringContainsString( 'DEFAULT CHARACTER SET utf8mb4', $sql );
	}

	public function testPrimaryKeyTwoSpaceFormat(): void {
		$bp = new Blueprint();
		$bp->big_increments( 'id' );

		$sql = $bp->to_sql( 'wp_test' );

		// Exactly two spaces between PRIMARY KEY and the parenthesis.
		$this->assertMatchesRegularExpression( '/PRIMARY KEY  \(id\)/', $sql );
	}

	public function testModifierChaining(): void {
		$bp  = new Blueprint();
		$col = $bp->string( 'email' )->nullable()->default( null );

		$this->assertInstanceOf( ColumnDefinition::class, $col );
		$this->assertSame( 'email  VARCHAR(255) NULL DEFAULT NULL', $col->to_sql() );
	}
}
