<?php
/**
 * Table definition builder.
 *
 * @package WPFlint\Database\Schema
 */

declare(strict_types=1);

namespace WPFlint\Database\Schema;

/**
 * Assembles a full CREATE TABLE statement from column, index, and foreign key definitions.
 */
class Blueprint {

	/**
	 * Column definitions.
	 *
	 * @var ColumnDefinition[]
	 */
	protected array $columns = array();

	/**
	 * The primary key column name.
	 *
	 * @var string|null
	 */
	protected ?string $primary_key = null;

	/**
	 * Index definitions.
	 *
	 * @var array<int, array{type: string, columns: array<int, string>}>
	 */
	protected array $indexes = array();

	/**
	 * Foreign key definitions.
	 *
	 * @var ForeignKeyDefinition[]
	 */
	protected array $foreign_keys = array();

	/**
	 * Add an auto-incrementing BIGINT UNSIGNED primary key column.
	 *
	 * @param string $column Column name.
	 * @return ColumnDefinition
	 */
	public function big_increments( string $column ): ColumnDefinition {
		$definition = new ColumnDefinition( $column, 'BIGINT UNSIGNED' );
		$definition->auto_increment();

		$this->columns[]   = $definition;
		$this->primary_key = $column;

		return $definition;
	}

	/**
	 * Add a VARCHAR column.
	 *
	 * @param string $column Column name.
	 * @param int    $length Maximum length.
	 * @return ColumnDefinition
	 */
	public function string( string $column, int $length = 255 ): ColumnDefinition {
		$definition      = new ColumnDefinition( $column, 'VARCHAR(' . $length . ')' );
		$this->columns[] = $definition;

		return $definition;
	}

	/**
	 * Add an INT column.
	 *
	 * @param string $column Column name.
	 * @return ColumnDefinition
	 */
	public function integer( string $column ): ColumnDefinition {
		$definition      = new ColumnDefinition( $column, 'INT' );
		$this->columns[] = $definition;

		return $definition;
	}

	/**
	 * Add a DECIMAL column.
	 *
	 * @param string $column    Column name.
	 * @param int    $precision Total digits.
	 * @param int    $scale     Decimal digits.
	 * @return ColumnDefinition
	 */
	public function decimal( string $column, int $precision = 8, int $scale = 2 ): ColumnDefinition {
		$type            = 'DECIMAL(' . $precision . ',' . $scale . ')';
		$definition      = new ColumnDefinition( $column, $type );
		$this->columns[] = $definition;

		return $definition;
	}

	/**
	 * Add a TINYINT(1) boolean column.
	 *
	 * @param string $column Column name.
	 * @return ColumnDefinition
	 */
	public function boolean( string $column ): ColumnDefinition {
		$definition      = new ColumnDefinition( $column, 'TINYINT(1)' );
		$this->columns[] = $definition;

		return $definition;
	}

	/**
	 * Add a TEXT column.
	 *
	 * @param string $column Column name.
	 * @return ColumnDefinition
	 */
	public function text( string $column ): ColumnDefinition {
		$definition      = new ColumnDefinition( $column, 'TEXT' );
		$this->columns[] = $definition;

		return $definition;
	}

	/**
	 * Add created_at and updated_at DATETIME nullable columns.
	 *
	 * @return void
	 */
	public function timestamps(): void {
		$created = new ColumnDefinition( 'created_at', 'DATETIME' );
		$created->nullable();
		$this->columns[] = $created;

		$updated = new ColumnDefinition( 'updated_at', 'DATETIME' );
		$updated->nullable();
		$this->columns[] = $updated;
	}

	/**
	 * Add a deleted_at DATETIME nullable column for soft deletes.
	 *
	 * @return void
	 */
	public function soft_deletes(): void {
		$deleted = new ColumnDefinition( 'deleted_at', 'DATETIME' );
		$deleted->nullable();
		$this->columns[] = $deleted;
	}

	/**
	 * Add a regular index.
	 *
	 * @param string|array<int, string> $columns Column(s) to index.
	 * @return void
	 */
	public function index( $columns ): void {
		$columns = (array) $columns;

		$this->indexes[] = array(
			'type'    => 'KEY',
			'columns' => $columns,
		);
	}

	/**
	 * Add a unique index.
	 *
	 * @param string|array<int, string> $columns Column(s) to index.
	 * @return void
	 */
	public function unique( $columns ): void {
		$columns = (array) $columns;

		$this->indexes[] = array(
			'type'    => 'UNIQUE KEY',
			'columns' => $columns,
		);
	}

	/**
	 * Add a foreign key constraint.
	 *
	 * @param string $column Local column name.
	 * @return ForeignKeyDefinition
	 */
	public function foreign( string $column ): ForeignKeyDefinition {
		$fk                   = new ForeignKeyDefinition( $column );
		$this->foreign_keys[] = $fk;

		return $fk;
	}

	/**
	 * Get all column definitions.
	 *
	 * @return ColumnDefinition[]
	 */
	public function get_columns(): array {
		return $this->columns;
	}

	/**
	 * Get the primary key column name.
	 *
	 * @return string|null
	 */
	public function get_primary_key(): ?string {
		return $this->primary_key;
	}

	/**
	 * Generate the full CREATE TABLE SQL statement.
	 *
	 * @param string $table           Full table name (already prefixed).
	 * @param string $charset_collate The charset/collation string from $wpdb.
	 * @return string
	 */
	public function to_sql( string $table, string $charset_collate = '' ): string {
		$lines = array();

		foreach ( $this->columns as $column ) {
			$lines[] = $column->to_sql();
		}

		// PRIMARY KEY uses two spaces before the keyword (dbDelta requirement).
		if ( null !== $this->primary_key ) {
			$lines[] = 'PRIMARY KEY  (' . $this->primary_key . ')';
		}

		foreach ( $this->indexes as $index ) {
			$name    = $this->build_index_name( $index['type'], $index['columns'] );
			$cols    = implode( ',', $index['columns'] );
			$lines[] = $index['type'] . ' ' . $name . ' (' . $cols . ')';
		}

		foreach ( $this->foreign_keys as $fk ) {
			$lines[] = $fk->to_sql( $table );
		}

		$body = implode( ",\n", $lines );

		$sql = "CREATE TABLE {$table} (\n{$body}\n) {$charset_collate};";

		return $sql;
	}

	/**
	 * Build an index name from its type and columns.
	 *
	 * @param string             $type    Index type (KEY or UNIQUE KEY).
	 * @param array<int, string> $columns Columns in the index.
	 * @return string
	 */
	protected function build_index_name( string $type, array $columns ): string {
		$prefix = 'UNIQUE KEY' === $type ? 'uniq' : 'idx';

		return $prefix . '_' . implode( '_', $columns );
	}
}
