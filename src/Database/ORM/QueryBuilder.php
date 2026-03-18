<?php
/**
 * Fluent query builder for WordPress $wpdb.
 *
 * @package WPFlint\Database\ORM
 */

declare(strict_types=1);

namespace WPFlint\Database\ORM;

use Closure;
use RuntimeException;

/**
 * Builds and executes SQL queries via $wpdb->prepare().
 *
 * Every data-bearing query uses $wpdb->prepare(). Table and column names
 * are developer-controlled identifiers (not user input).
 */
class QueryBuilder {

	/**
	 * Table name (fully prefixed).
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Columns to select.
	 *
	 * @var array
	 */
	protected array $columns = array( '*' );

	/**
	 * Whether to select distinct rows.
	 *
	 * @var bool
	 */
	protected bool $distinct = false;

	/**
	 * WHERE clauses.
	 *
	 * Each entry: ['type' => string, 'column' => string, 'operator' => string, 'value' => mixed, 'boolean' => 'AND'|'OR']
	 *
	 * @var array
	 */
	protected array $wheres = array();

	/**
	 * ORDER BY clauses.
	 *
	 * @var array
	 */
	protected array $orders = array();

	/**
	 * GROUP BY columns.
	 *
	 * @var array
	 */
	protected array $groups = array();

	/**
	 * HAVING clauses.
	 *
	 * @var array
	 */
	protected array $havings = array();

	/**
	 * JOIN clauses.
	 *
	 * @var array
	 */
	protected array $joins = array();

	/**
	 * LIMIT value.
	 *
	 * @var int|null
	 */
	protected ?int $limit_value = null;

	/**
	 * OFFSET value.
	 *
	 * @var int|null
	 */
	protected ?int $offset_value = null;

	/**
	 * Constructor.
	 *
	 * @param string $table Fully prefixed table name.
	 */
	public function __construct( string $table ) {
		$this->table = $table;
	}

	/**
	 * Create a new QueryBuilder for a table.
	 *
	 * @param string $table Unprefixed table name.
	 * @return static
	 */
	public static function table( string $table ): self {
		global $wpdb;

		return new self( $wpdb->prefix . $table );
	}

	// ---------------------------------------------------------------
	// SELECT modifiers
	// ---------------------------------------------------------------

	/**
	 * Set columns to select.
	 *
	 * @param array $columns Column names.
	 * @return $this
	 */
	public function select( array $columns ): self {
		$this->columns = $columns;
		return $this;
	}

	/**
	 * Add a column to the select list.
	 *
	 * @param string $column Column name.
	 * @return $this
	 */
	public function add_select( string $column ): self {
		if ( array( '*' ) === $this->columns ) {
			$this->columns = array();
		}
		$this->columns[] = $column;
		return $this;
	}

	/**
	 * Enable DISTINCT selection.
	 *
	 * @return $this
	 */
	public function distinct(): self {
		$this->distinct = true;
		return $this;
	}

	// ---------------------------------------------------------------
	// WHERE clauses
	// ---------------------------------------------------------------

	/**
	 * Add a basic WHERE clause.
	 *
	 * @param string $column   Column name.
	 * @param string $operator Comparison operator or value if two args.
	 * @param mixed  $value    Value to compare against.
	 * @return $this
	 */
	public function where( string $column, $operator = null, $value = null ): self {
		if ( null === $value ) {
			$value    = $operator;
			$operator = '=';
		}
		$this->wheres[] = array(
			'type'     => 'basic',
			'column'   => $column,
			'operator' => $operator,
			'value'    => $value,
			'boolean'  => 'AND',
		);
		return $this;
	}

	/**
	 * Add an OR WHERE clause.
	 *
	 * @param string $column   Column name.
	 * @param string $operator Comparison operator or value if two args.
	 * @param mixed  $value    Value to compare against.
	 * @return $this
	 */
	public function or_where( string $column, $operator = null, $value = null ): self {
		if ( null === $value ) {
			$value    = $operator;
			$operator = '=';
		}
		$this->wheres[] = array(
			'type'     => 'basic',
			'column'   => $column,
			'operator' => $operator,
			'value'    => $value,
			'boolean'  => 'OR',
		);
		return $this;
	}

	/**
	 * Add a WHERE IN clause.
	 *
	 * @param string $column Column name.
	 * @param array  $values Values to match.
	 * @return $this
	 */
	public function where_in( string $column, array $values ): self {
		$this->wheres[] = array(
			'type'    => 'in',
			'column'  => $column,
			'values'  => $values,
			'boolean' => 'AND',
			'not'     => false,
		);
		return $this;
	}

	/**
	 * Add a WHERE NOT IN clause.
	 *
	 * @param string $column Column name.
	 * @param array  $values Values to exclude.
	 * @return $this
	 */
	public function where_not_in( string $column, array $values ): self {
		$this->wheres[] = array(
			'type'    => 'in',
			'column'  => $column,
			'values'  => $values,
			'boolean' => 'AND',
			'not'     => true,
		);
		return $this;
	}

	/**
	 * Add a WHERE NULL clause.
	 *
	 * @param string $column Column name.
	 * @return $this
	 */
	public function where_null( string $column ): self {
		$this->wheres[] = array(
			'type'    => 'null',
			'column'  => $column,
			'boolean' => 'AND',
			'not'     => false,
		);
		return $this;
	}

	/**
	 * Add a WHERE NOT NULL clause.
	 *
	 * @param string $column Column name.
	 * @return $this
	 */
	public function where_not_null( string $column ): self {
		$this->wheres[] = array(
			'type'    => 'null',
			'column'  => $column,
			'boolean' => 'AND',
			'not'     => true,
		);
		return $this;
	}

	/**
	 * Add a WHERE BETWEEN clause.
	 *
	 * @param string $column Column name.
	 * @param mixed  $min    Minimum value.
	 * @param mixed  $max    Maximum value.
	 * @return $this
	 */
	public function where_between( string $column, $min, $max ): self {
		$this->wheres[] = array(
			'type'    => 'between',
			'column'  => $column,
			'min'     => $min,
			'max'     => $max,
			'boolean' => 'AND',
			'not'     => false,
		);
		return $this;
	}

	/**
	 * Add a WHERE NOT BETWEEN clause.
	 *
	 * @param string $column Column name.
	 * @param mixed  $min    Minimum value.
	 * @param mixed  $max    Maximum value.
	 * @return $this
	 */
	public function where_not_between( string $column, $min, $max ): self {
		$this->wheres[] = array(
			'type'    => 'between',
			'column'  => $column,
			'min'     => $min,
			'max'     => $max,
			'boolean' => 'AND',
			'not'     => true,
		);
		return $this;
	}

	/**
	 * Add a WHERE LIKE clause.
	 *
	 * @param string $column  Column name.
	 * @param string $pattern LIKE pattern (use % for wildcards).
	 * @return $this
	 */
	public function where_like( string $column, string $pattern ): self {
		$this->wheres[] = array(
			'type'     => 'basic',
			'column'   => $column,
			'operator' => 'LIKE',
			'value'    => $pattern,
			'boolean'  => 'AND',
		);
		return $this;
	}

	/**
	 * Add a raw WHERE clause.
	 *
	 * @param string $sql    Raw SQL fragment (with %s/%d placeholders).
	 * @param array  $params Parameters for prepare().
	 * @return $this
	 */
	public function where_raw( string $sql, array $params = array() ): self {
		$this->wheres[] = array(
			'type'    => 'raw',
			'sql'     => $sql,
			'params'  => $params,
			'boolean' => 'AND',
		);
		return $this;
	}

	// ---------------------------------------------------------------
	// JOIN clauses
	// ---------------------------------------------------------------

	/**
	 * Add an INNER JOIN clause.
	 *
	 * @param string $table  Table to join (unprefixed).
	 * @param string $first  First column.
	 * @param string $operator Comparison operator.
	 * @param string $second Second column.
	 * @return $this
	 */
	public function join( string $table, string $first, string $operator, string $second ): self {
		global $wpdb;

		$this->joins[] = array(
			'type'     => 'INNER',
			'table'    => $wpdb->prefix . $table,
			'first'    => $first,
			'operator' => $operator,
			'second'   => $second,
		);
		return $this;
	}

	/**
	 * Add a LEFT JOIN clause.
	 *
	 * @param string $table  Table to join (unprefixed).
	 * @param string $first  First column.
	 * @param string $operator Comparison operator.
	 * @param string $second Second column.
	 * @return $this
	 */
	public function left_join( string $table, string $first, string $operator, string $second ): self {
		global $wpdb;

		$this->joins[] = array(
			'type'     => 'LEFT',
			'table'    => $wpdb->prefix . $table,
			'first'    => $first,
			'operator' => $operator,
			'second'   => $second,
		);
		return $this;
	}

	/**
	 * Add a RIGHT JOIN clause.
	 *
	 * @param string $table  Table to join (unprefixed).
	 * @param string $first  First column.
	 * @param string $operator Comparison operator.
	 * @param string $second Second column.
	 * @return $this
	 */
	public function right_join( string $table, string $first, string $operator, string $second ): self {
		global $wpdb;

		$this->joins[] = array(
			'type'     => 'RIGHT',
			'table'    => $wpdb->prefix . $table,
			'first'    => $first,
			'operator' => $operator,
			'second'   => $second,
		);
		return $this;
	}

	// ---------------------------------------------------------------
	// ORDER BY / GROUP BY / HAVING
	// ---------------------------------------------------------------

	/**
	 * Add an ORDER BY clause.
	 *
	 * @param string $column    Column name.
	 * @param string $direction Sort direction (ASC or DESC).
	 * @return $this
	 */
	public function order_by( string $column, string $direction = 'ASC' ): self {
		$direction      = strtoupper( $direction );
		$direction      = in_array( $direction, array( 'ASC', 'DESC' ), true ) ? $direction : 'ASC';
		$this->orders[] = array(
			'column'    => $column,
			'direction' => $direction,
		);
		return $this;
	}

	/**
	 * Order by latest (created_at DESC).
	 *
	 * @param string $column Column name.
	 * @return $this
	 */
	public function latest( string $column = 'created_at' ): self {
		return $this->order_by( $column, 'DESC' );
	}

	/**
	 * Order by oldest (created_at ASC).
	 *
	 * @param string $column Column name.
	 * @return $this
	 */
	public function oldest( string $column = 'created_at' ): self {
		return $this->order_by( $column, 'ASC' );
	}

	/**
	 * Add a GROUP BY clause.
	 *
	 * @param string $column Column name.
	 * @return $this
	 */
	public function group_by( string $column ): self {
		$this->groups[] = $column;
		return $this;
	}

	/**
	 * Add a HAVING clause.
	 *
	 * @param string $column   Column or aggregate.
	 * @param string $operator Comparison operator.
	 * @param mixed  $value    Value to compare against.
	 * @return $this
	 */
	public function having( string $column, string $operator, $value ): self {
		$this->havings[] = array(
			'column'   => $column,
			'operator' => $operator,
			'value'    => $value,
		);
		return $this;
	}

	// ---------------------------------------------------------------
	// LIMIT / OFFSET
	// ---------------------------------------------------------------

	/**
	 * Set the LIMIT.
	 *
	 * @param int $value Number of rows.
	 * @return $this
	 */
	public function limit( int $value ): self {
		$this->limit_value = $value;
		return $this;
	}

	/**
	 * Set the OFFSET.
	 *
	 * @param int $value Number of rows to skip.
	 * @return $this
	 */
	public function offset( int $value ): self {
		$this->offset_value = $value;
		return $this;
	}

	/**
	 * Alias for limit() and offset() combined.
	 *
	 * @param int $count Number of rows.
	 * @param int $start Offset.
	 * @return $this
	 */
	public function take( int $count, int $start = 0 ): self {
		$this->limit_value  = $count;
		$this->offset_value = $start;
		return $this;
	}

	// ---------------------------------------------------------------
	// Retrieval methods
	// ---------------------------------------------------------------

	/**
	 * Execute the query and return all results.
	 *
	 * @return array Array of associative arrays.
	 */
	public function get(): array {
		global $wpdb;

		$sql    = $this->build_select();
		$params = $this->get_bindings();

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- built via prepare().
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- no user input in query.
			$results = $wpdb->get_results( $sql, ARRAY_A );
		}

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get the first result.
	 *
	 * @return array|null Associative array or null.
	 */
	public function first(): ?array {
		$this->limit_value = 1;
		$results           = $this->get();

		return ! empty( $results ) ? $results[0] : null;
	}

	/**
	 * Find a row by primary key.
	 *
	 * @param mixed  $id     Primary key value.
	 * @param string $column Primary key column name.
	 * @return array|null Associative array or null.
	 */
	public function find( $id, string $column = 'id' ): ?array {
		return $this->where( $column, '=', $id )->first();
	}

	/**
	 * Get a single column value from the first row.
	 *
	 * @param string $column Column name.
	 * @return mixed|null
	 */
	public function value( string $column ) {
		$row = $this->select( array( $column ) )->first();

		return null !== $row ? ( $row[ $column ] ?? null ) : null;
	}

	/**
	 * Get an array of values for a single column.
	 *
	 * @param string      $column Column to pluck.
	 * @param string|null $key    Optional column to use as array keys.
	 * @return array
	 */
	public function pluck( string $column, ?string $key = null ): array {
		if ( null !== $key ) {
			$this->columns = array( $key, $column );
		} else {
			$this->columns = array( $column );
		}

		$results = $this->get();
		$plucked = array();

		foreach ( $results as $row ) {
			if ( null !== $key ) {
				$plucked[ $row[ $key ] ] = $row[ $column ];
			} else {
				$plucked[] = $row[ $column ];
			}
		}

		return $plucked;
	}

	/**
	 * Check if any rows exist.
	 *
	 * @return bool
	 */
	public function exists(): bool {
		return null !== $this->first();
	}

	/**
	 * Check if no rows exist.
	 *
	 * @return bool
	 */
	public function doesnt_exist(): bool {
		return ! $this->exists();
	}

	// ---------------------------------------------------------------
	// Aggregates
	// ---------------------------------------------------------------

	/**
	 * Get the count of matching rows.
	 *
	 * @param string $column Column name (default '*').
	 * @return int
	 */
	public function count( string $column = '*' ): int {
		return (int) $this->aggregate( 'COUNT', $column );
	}

	/**
	 * Get the maximum value of a column.
	 *
	 * @param string $column Column name.
	 * @return mixed
	 */
	public function max( string $column ) {
		return $this->aggregate( 'MAX', $column );
	}

	/**
	 * Get the minimum value of a column.
	 *
	 * @param string $column Column name.
	 * @return mixed
	 */
	public function min( string $column ) {
		return $this->aggregate( 'MIN', $column );
	}

	/**
	 * Get the average value of a column.
	 *
	 * @param string $column Column name.
	 * @return mixed
	 */
	public function avg( string $column ) {
		return $this->aggregate( 'AVG', $column );
	}

	/**
	 * Get the sum of a column.
	 *
	 * @param string $column Column name.
	 * @return mixed
	 */
	public function sum( string $column ) {
		return $this->aggregate( 'SUM', $column );
	}

	/**
	 * Execute an aggregate function.
	 *
	 * @param string $function Aggregate function name.
	 * @param string $column   Column name.
	 * @return mixed
	 */
	protected function aggregate( string $function, string $column ) {
		global $wpdb;

		$this->columns = array( "{$function}({$column}) as aggregate" );

		$sql    = $this->build_select();
		$params = $this->get_bindings();

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- built via prepare().
			$result = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- no user input in query.
			$result = $wpdb->get_var( $sql );
		}

		return $result;
	}

	// ---------------------------------------------------------------
	// Pagination / Chunking
	// ---------------------------------------------------------------

	/**
	 * Paginate results.
	 *
	 * @param int $per_page Items per page.
	 * @param int $page     Current page number (1-based).
	 * @return array With 'data', 'total', 'per_page', 'current_page', 'last_page' keys.
	 */
	public function paginate( int $per_page = 15, int $page = 1 ): array {
		$total     = $this->count();
		$last_page = (int) ceil( $total / $per_page );

		$this->columns      = array( '*' );
		$this->limit_value  = $per_page;
		$this->offset_value = ( $page - 1 ) * $per_page;

		$data = $this->get();

		return array(
			'data'         => $data,
			'total'        => $total,
			'per_page'     => $per_page,
			'current_page' => $page,
			'last_page'    => $last_page,
		);
	}

	/**
	 * Process results in chunks.
	 *
	 * @param int     $size     Chunk size.
	 * @param Closure $callback Receives array of rows. Return false to stop.
	 * @return void
	 */
	public function chunk( int $size, Closure $callback ): void {
		$page = 1;

		do {
			$this->limit_value  = $size;
			$this->offset_value = ( $page - 1 ) * $size;

			$results = $this->get();

			if ( empty( $results ) ) {
				break;
			}

			if ( false === $callback( $results ) ) {
				break;
			}

			$result_count = count( $results );
			++$page;
		} while ( $result_count >= $size );
	}

	// ---------------------------------------------------------------
	// Insert / Update / Delete
	// ---------------------------------------------------------------

	/**
	 * Insert a row.
	 *
	 * @param array $data Column => value pairs.
	 * @return int|false Insert ID or false on failure.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$format = $this->build_format( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert( $this->table, $data, $format );

		return false !== $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Insert multiple rows.
	 *
	 * @param array $rows Array of column => value arrays.
	 * @return int Number of rows inserted.
	 */
	public function insert_many( array $rows ): int {
		$inserted = 0;

		foreach ( $rows as $data ) {
			$result = $this->insert( $data );
			if ( false !== $result ) {
				++$inserted;
			}
		}

		return $inserted;
	}

	/**
	 * Update rows matching the current WHERE clauses.
	 *
	 * @param array $data Column => value pairs to update.
	 * @return int|false Number of rows affected or false on error.
	 */
	public function update( array $data ) {
		global $wpdb;

		$where_clause = $this->compile_wheres();
		$params       = $this->get_bindings();

		$set_parts  = array();
		$set_params = array();

		foreach ( $data as $column => $value ) {
			$set_parts[]  = "{$column} = " . $this->get_placeholder( $value );
			$set_params[] = $value;
		}

		$set_sql = implode( ', ', $set_parts );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column names are developer-controlled.
		$sql = "UPDATE {$this->table} SET {$set_sql}";

		if ( '' !== $where_clause ) {
			$sql .= " WHERE {$where_clause}";
		}

		$all_params = array_merge( $set_params, $params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- built via prepare().
		$result = $wpdb->query( $wpdb->prepare( $sql, $all_params ) );

		return false !== $result ? (int) $result : false;
	}

	/**
	 * Delete rows matching the current WHERE clauses.
	 *
	 * @return int|false Number of rows deleted or false on error.
	 */
	public function delete() {
		global $wpdb;

		$where_clause = $this->compile_wheres();
		$params       = $this->get_bindings();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is developer-controlled.
		$sql = "DELETE FROM {$this->table}";

		if ( '' !== $where_clause ) {
			$sql .= " WHERE {$where_clause}";
		}

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- built via prepare().
			$result = $wpdb->query( $wpdb->prepare( $sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- no user input in query.
			$result = $wpdb->query( $sql );
		}

		return false !== $result ? (int) $result : false;
	}

	/**
	 * Increment a column value.
	 *
	 * @param string $column Column name.
	 * @param int    $amount Amount to increment.
	 * @return int|false Number of rows affected or false.
	 */
	public function increment( string $column, int $amount = 1 ) {
		return $this->update( array( $column => new RawExpression( "{$column} + {$amount}" ) ) );
	}

	/**
	 * Decrement a column value.
	 *
	 * @param string $column Column name.
	 * @param int    $amount Amount to decrement.
	 * @return int|false Number of rows affected or false.
	 */
	public function decrement( string $column, int $amount = 1 ) {
		return $this->update( array( $column => new RawExpression( "{$column} - {$amount}" ) ) );
	}

	// ---------------------------------------------------------------
	// SQL compilation
	// ---------------------------------------------------------------

	/**
	 * Build the full SELECT SQL statement.
	 *
	 * @return string
	 */
	public function build_select(): string {
		$columns  = implode( ', ', $this->columns );
		$distinct = $this->distinct ? 'DISTINCT ' : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are developer-controlled.
		$sql = "SELECT {$distinct}{$columns} FROM {$this->table}";

		$sql .= $this->compile_joins();

		$where_clause = $this->compile_wheres();
		if ( '' !== $where_clause ) {
			$sql .= " WHERE {$where_clause}";
		}

		if ( ! empty( $this->groups ) ) {
			$sql .= ' GROUP BY ' . implode( ', ', $this->groups );
		}

		$having_clause = $this->compile_havings();
		if ( '' !== $having_clause ) {
			$sql .= " HAVING {$having_clause}";
		}

		if ( ! empty( $this->orders ) ) {
			$order_parts = array();
			foreach ( $this->orders as $order ) {
				$order_parts[] = "{$order['column']} {$order['direction']}";
			}
			$sql .= ' ORDER BY ' . implode( ', ', $order_parts );
		}

		if ( null !== $this->limit_value ) {
			$sql .= ' LIMIT %d';
		}

		if ( null !== $this->offset_value ) {
			$sql .= ' OFFSET %d';
		}

		return $sql;
	}

	/**
	 * Get all parameter bindings for prepare().
	 *
	 * @return array
	 */
	public function get_bindings(): array {
		$bindings = array();

		$bindings = array_merge( $bindings, $this->get_where_bindings() );
		$bindings = array_merge( $bindings, $this->get_having_bindings() );

		if ( null !== $this->limit_value ) {
			$bindings[] = $this->limit_value;
		}

		if ( null !== $this->offset_value ) {
			$bindings[] = $this->offset_value;
		}

		return $bindings;
	}

	/**
	 * Compile WHERE clauses into SQL.
	 *
	 * @return string
	 */
	protected function compile_wheres(): string {
		if ( empty( $this->wheres ) ) {
			return '';
		}

		$parts = array();

		foreach ( $this->wheres as $index => $where ) {
			$clause = '';

			switch ( $where['type'] ) {
				case 'basic':
					$placeholder = $this->get_placeholder( $where['value'] );
					$clause      = "{$where['column']} {$where['operator']} {$placeholder}";
					break;

				case 'in':
					$placeholders = array();
					foreach ( $where['values'] as $val ) {
						$placeholders[] = $this->get_placeholder( $val );
					}
					$in_list = implode( ', ', $placeholders );
					$not     = $where['not'] ? 'NOT ' : '';
					$clause  = "{$where['column']} {$not}IN ({$in_list})";
					break;

				case 'null':
					$not    = $where['not'] ? 'NOT ' : '';
					$clause = "{$where['column']} IS {$not}NULL";
					break;

				case 'between':
					$min_placeholder = $this->get_placeholder( $where['min'] );
					$max_placeholder = $this->get_placeholder( $where['max'] );
					$not             = $where['not'] ? 'NOT ' : '';
					$clause          = "{$where['column']} {$not}BETWEEN {$min_placeholder} AND {$max_placeholder}";
					break;

				case 'raw':
					$clause = $where['sql'];
					break;
			}

			if ( 0 === $index ) {
				$parts[] = $clause;
			} else {
				$parts[] = $where['boolean'] . ' ' . $clause;
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * Get bindings from WHERE clauses.
	 *
	 * @return array
	 */
	protected function get_where_bindings(): array {
		$bindings = array();

		foreach ( $this->wheres as $where ) {
			switch ( $where['type'] ) {
				case 'basic':
					if ( ! ( $where['value'] instanceof RawExpression ) ) {
						$bindings[] = $where['value'];
					}
					break;

				case 'in':
					foreach ( $where['values'] as $val ) {
						$bindings[] = $val;
					}
					break;

				case 'between':
					$bindings[] = $where['min'];
					$bindings[] = $where['max'];
					break;

				case 'raw':
					foreach ( $where['params'] as $param ) {
						$bindings[] = $param;
					}
					break;
			}
		}

		return $bindings;
	}

	/**
	 * Compile JOIN clauses.
	 *
	 * @return string
	 */
	protected function compile_joins(): string {
		if ( empty( $this->joins ) ) {
			return '';
		}

		$parts = array();

		foreach ( $this->joins as $join ) {
			$parts[] = " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
		}

		return implode( '', $parts );
	}

	/**
	 * Compile HAVING clauses.
	 *
	 * @return string
	 */
	protected function compile_havings(): string {
		if ( empty( $this->havings ) ) {
			return '';
		}

		$parts = array();

		foreach ( $this->havings as $having ) {
			$placeholder = $this->get_placeholder( $having['value'] );
			$parts[]     = "{$having['column']} {$having['operator']} {$placeholder}";
		}

		return implode( ' AND ', $parts );
	}

	/**
	 * Get bindings from HAVING clauses.
	 *
	 * @return array
	 */
	protected function get_having_bindings(): array {
		$bindings = array();

		foreach ( $this->havings as $having ) {
			$bindings[] = $having['value'];
		}

		return $bindings;
	}

	/**
	 * Get the appropriate placeholder for a value.
	 *
	 * @param mixed $value Value to get placeholder for.
	 * @return string %s, %d, or %f placeholder.
	 */
	protected function get_placeholder( $value ): string {
		if ( $value instanceof RawExpression ) {
			return $value->get_expression();
		}

		if ( is_int( $value ) ) {
			return '%d';
		}

		if ( is_float( $value ) ) {
			return '%f';
		}

		return '%s';
	}

	/**
	 * Build format array for $wpdb->insert().
	 *
	 * @param array $data Column => value pairs.
	 * @return array Format strings.
	 */
	protected function build_format( array $data ): array {
		$format = array();

		foreach ( $data as $value ) {
			$format[] = $this->get_placeholder( $value );
		}

		return $format;
	}

	/**
	 * Clone the builder for sub-queries.
	 *
	 * @return static
	 */
	public function clone_builder(): self {
		return clone $this;
	}

	/**
	 * Reset the WHERE clauses.
	 *
	 * @return $this
	 */
	public function reset_wheres(): self {
		$this->wheres = array();
		return $this;
	}

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function get_table(): string {
		return $this->table;
	}
}
