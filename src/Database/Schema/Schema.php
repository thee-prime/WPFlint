<?php
/**
 * Schema orchestrator — manages table creation, dropping, and inspection via $wpdb.
 *
 * @package WPFlint\Database\Schema
 */

declare(strict_types=1);

namespace WPFlint\Database\Schema;

use Closure;

/**
 * Provides static-like methods to create, drop, and inspect database tables.
 *
 * Uses global $wpdb inside each method (standard WordPress pattern).
 */
class Schema {

	/**
	 * Create a table using a Blueprint callback and dbDelta().
	 *
	 * @param string  $table    Unprefixed table name.
	 * @param Closure $callback Receives a Blueprint instance.
	 * @return void
	 */
	public function create( string $table, Closure $callback ): void {
		global $wpdb;

		$prefixed  = $wpdb->prefix . $table;
		$blueprint = new Blueprint();

		$callback( $blueprint );

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = $blueprint->to_sql( $prefixed, $charset_collate );

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );
	}

	/**
	 * Drop a table if it exists.
	 *
	 * Table name is developer-controlled (not user input), so direct interpolation
	 * is safe here. WordPress does not support parameterised identifiers.
	 *
	 * @param string $table Unprefixed table name.
	 * @return void
	 */
	public function drop( string $table ): void {
		global $wpdb;

		$prefixed = $wpdb->prefix . $table;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is developer-controlled.
		$wpdb->query( "DROP TABLE IF EXISTS {$prefixed}" );
	}

	/**
	 * Check whether a table exists.
	 *
	 * @param string $table Unprefixed table name.
	 * @return bool
	 */
	public function has_table( string $table ): bool {
		global $wpdb;

		$prefixed = $wpdb->prefix . $table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $prefixed )
		);

		return null !== $result;
	}

	/**
	 * Add a column to an existing table.
	 *
	 * Table and column names are developer-controlled (not user input), so direct
	 * interpolation is safe here.
	 *
	 * @param string $table  Unprefixed table name.
	 * @param string $column Column name.
	 * @param string $type   SQL column type (e.g. 'VARCHAR(255) NOT NULL').
	 * @return void
	 */
	public function add_column( string $table, string $column, string $type ): void {
		global $wpdb;

		$prefixed = $wpdb->prefix . $table;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- identifiers are developer-controlled.
		$wpdb->query( "ALTER TABLE {$prefixed} ADD COLUMN {$column} {$type}" );
	}
}
