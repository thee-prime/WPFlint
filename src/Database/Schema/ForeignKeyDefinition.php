<?php
/**
 * Fluent foreign key builder.
 *
 * @package WPFlint\Database\Schema
 */

declare(strict_types=1);

namespace WPFlint\Database\Schema;

/**
 * Builds a FOREIGN KEY constraint for a CREATE TABLE statement.
 */
class ForeignKeyDefinition {

	/**
	 * The local column name.
	 *
	 * @var string
	 */
	protected string $column;

	/**
	 * The referenced column name.
	 *
	 * @var string|null
	 */
	protected ?string $references_column = null;

	/**
	 * The referenced table name.
	 *
	 * @var string|null
	 */
	protected ?string $references_table = null;

	/**
	 * ON DELETE action.
	 *
	 * @var string
	 */
	protected string $on_delete = 'CASCADE';

	/**
	 * ON UPDATE action.
	 *
	 * @var string
	 */
	protected string $on_update = 'CASCADE';

	/**
	 * Create a new foreign key definition.
	 *
	 * @param string $column The local column name.
	 */
	public function __construct( string $column ) {
		$this->column = $column;
	}

	/**
	 * Set the referenced column.
	 *
	 * @param string $column Referenced column name.
	 * @return self
	 */
	public function references( string $column ): self {
		$this->references_column = $column;
		return $this;
	}

	/**
	 * Set the referenced table.
	 *
	 * @param string $table Referenced table name.
	 * @return self
	 */
	public function on( string $table ): self {
		$this->references_table = $table;
		return $this;
	}

	/**
	 * Set the ON DELETE action.
	 *
	 * @param string $action SQL action (CASCADE, SET NULL, RESTRICT, etc.).
	 * @return self
	 */
	public function on_delete( string $action ): self {
		$this->on_delete = $action;
		return $this;
	}

	/**
	 * Set the ON UPDATE action.
	 *
	 * @param string $action SQL action (CASCADE, SET NULL, RESTRICT, etc.).
	 * @return self
	 */
	public function on_update( string $action ): self {
		$this->on_update = $action;
		return $this;
	}

	/**
	 * Generate the SQL fragment for this foreign key.
	 *
	 * @param string $table The local table name (used for constraint naming).
	 * @return string
	 */
	public function to_sql( string $table ): string {
		$constraint = 'fk_' . $table . '_' . $this->column;

		return sprintf(
			'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s) ON DELETE %s ON UPDATE %s',
			$constraint,
			$this->column,
			$this->references_table,
			$this->references_column,
			$this->on_delete,
			$this->on_update
		);
	}
}
