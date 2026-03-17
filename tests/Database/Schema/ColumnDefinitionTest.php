<?php

declare(strict_types=1);

namespace WPFlint\Tests\Database\Schema;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Database\Schema\ColumnDefinition;

/**
 * @covers \WPFlint\Database\Schema\ColumnDefinition
 */
class ColumnDefinitionTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Basic SQL generation
	// ---------------------------------------------------------------

	public function testBasicColumnSql(): void {
		$col = new ColumnDefinition( 'name', 'VARCHAR(255)' );

		$this->assertSame( 'name  VARCHAR(255) NOT NULL', $col->to_sql() );
	}

	public function testTwoSpacesBetweenNameAndType(): void {
		$col = new ColumnDefinition( 'id', 'BIGINT UNSIGNED' );

		// dbDelta requires exactly two spaces between name and type.
		$this->assertStringContainsString( 'id  BIGINT', $col->to_sql() );
	}

	// ---------------------------------------------------------------
	// Nullable
	// ---------------------------------------------------------------

	public function testNullableColumn(): void {
		$col = new ColumnDefinition( 'deleted_at', 'DATETIME' );
		$col->nullable();

		$this->assertSame( 'deleted_at  DATETIME NULL', $col->to_sql() );
	}

	// ---------------------------------------------------------------
	// Default values
	// ---------------------------------------------------------------

	public function testDefaultStringValue(): void {
		$col = new ColumnDefinition( 'status', 'VARCHAR(50)' );
		$col->default( 'pending' );

		$this->assertSame( "status  VARCHAR(50) NOT NULL DEFAULT 'pending'", $col->to_sql() );
	}

	public function testDefaultNumericValue(): void {
		$col = new ColumnDefinition( 'quantity', 'INT' );
		$col->default( 0 );

		$this->assertSame( 'quantity  INT NOT NULL DEFAULT 0', $col->to_sql() );
	}

	public function testDefaultNullValue(): void {
		$col = new ColumnDefinition( 'notes', 'TEXT' );
		$col->nullable()->default( null );

		$this->assertSame( 'notes  TEXT NULL DEFAULT NULL', $col->to_sql() );
	}

	public function testDefaultBooleanTrueValue(): void {
		$col = new ColumnDefinition( 'active', 'TINYINT(1)' );
		$col->default( true );

		$this->assertSame( 'active  TINYINT(1) NOT NULL DEFAULT 1', $col->to_sql() );
	}

	public function testDefaultBooleanFalseValue(): void {
		$col = new ColumnDefinition( 'active', 'TINYINT(1)' );
		$col->default( false );

		$this->assertSame( 'active  TINYINT(1) NOT NULL DEFAULT 0', $col->to_sql() );
	}

	// ---------------------------------------------------------------
	// Auto increment
	// ---------------------------------------------------------------

	public function testAutoIncrement(): void {
		$col = new ColumnDefinition( 'id', 'BIGINT UNSIGNED' );
		$col->auto_increment();

		$this->assertSame( 'id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $col->to_sql() );
	}

	// ---------------------------------------------------------------
	// Chained modifiers
	// ---------------------------------------------------------------

	public function testChainedModifiers(): void {
		$col = new ColumnDefinition( 'price', 'DECIMAL(10,2)' );
		$result = $col->nullable()->default( 0.00 );

		// Chaining returns self.
		$this->assertSame( $col, $result );
		$this->assertSame( 'price  DECIMAL(10,2) NULL DEFAULT 0', $col->to_sql() );
	}

	// ---------------------------------------------------------------
	// get_name()
	// ---------------------------------------------------------------

	public function testGetName(): void {
		$col = new ColumnDefinition( 'email', 'VARCHAR(255)' );

		$this->assertSame( 'email', $col->get_name() );
	}

	// ---------------------------------------------------------------
	// Default float value
	// ---------------------------------------------------------------

	public function testDefaultFloatValue(): void {
		$col = new ColumnDefinition( 'rate', 'DECIMAL(5,2)' );
		$col->default( 9.99 );

		$this->assertSame( 'rate  DECIMAL(5,2) NOT NULL DEFAULT 9.99', $col->to_sql() );
	}
}
