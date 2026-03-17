<?php
/**
 * Fluent column definition value object.
 *
 * @package WPFlint\Database\Schema
 */

declare(strict_types=1);

namespace WPFlint\Database\Schema;

/**
 * Represents a single column definition for a CREATE TABLE statement.
 * Column methods return $this so modifiers can be chained.
 */
class ColumnDefinition {

	/**
	 * Column name.
	 *
	 * @var string
	 */
	protected string $name;

	/**
	 * SQL column type (e.g. BIGINT UNSIGNED, VARCHAR(255)).
	 *
	 * @var string
	 */
	protected string $type;

	/**
	 * Whether the column allows NULL.
	 *
	 * @var bool
	 */
	protected bool $nullable = false;

	/**
	 * Whether a default value has been explicitly set.
	 *
	 * @var bool
	 */
	protected bool $has_default = false;

	/**
	 * The default value for the column.
	 *
	 * @var mixed
	 */
	protected $default = null;

	/**
	 * Whether the column auto-increments.
	 *
	 * @var bool
	 */
	protected bool $auto_increment = false;

	/**
	 * Create a new column definition.
	 *
	 * @param string $name Column name.
	 * @param string $type SQL type string.
	 */
	public function __construct( string $name, string $type ) {
		$this->name = $name;
		$this->type = $type;
	}

	/**
	 * Mark column as nullable.
	 *
	 * @return self
	 */
	public function nullable(): self {
		$this->nullable = true;
		return $this;
	}

	/**
	 * Set a default value.
	 *
	 * @param mixed $value Default value.
	 * @return self
	 */
	public function default( $value ): self {
		$this->has_default = true;
		$this->default     = $value;
		return $this;
	}

	/**
	 * Mark column as auto-incrementing.
	 *
	 * @return self
	 */
	public function auto_increment(): self {
		$this->auto_increment = true;
		return $this;
	}

	/**
	 * Get the column name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Generate the SQL fragment for this column.
	 *
	 * Uses two spaces between name and type as required by dbDelta().
	 *
	 * @return string
	 */
	public function to_sql(): string {
		// dbDelta requires two spaces between column name and type.
		$sql = $this->name . '  ' . $this->type;

		if ( ! $this->nullable ) {
			$sql .= ' NOT NULL';
		} else {
			$sql .= ' NULL';
		}

		if ( $this->has_default ) {
			$sql .= ' DEFAULT ' . $this->format_default( $this->default );
		}

		if ( $this->auto_increment ) {
			$sql .= ' AUTO_INCREMENT';
		}

		return $sql;
	}

	/**
	 * Format a default value for SQL output.
	 *
	 * @param mixed $value The default value.
	 * @return string
	 */
	protected function format_default( $value ): string {
		if ( null === $value ) {
			return 'NULL';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		return "'" . $value . "'";
	}
}
