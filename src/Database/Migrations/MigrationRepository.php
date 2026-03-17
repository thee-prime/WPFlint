<?php
/**
 * Migration repository — tracks which migrations have been run.
 *
 * @package WPFlint\Database\Migrations
 */

declare(strict_types=1);

namespace WPFlint\Database\Migrations;

use WPFlint\Database\Schema\Schema;

/**
 * Manages the wpflint_migrations tracking table.
 *
 * Each row is scoped by plugin_slug so multiple plugins sharing
 * WPFlint don't collide.
 */
class MigrationRepository {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	private string $table = 'wpflint_migrations';

	/**
	 * Plugin slug for scoping.
	 *
	 * @var string
	 */
	private string $plugin_slug;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_slug Plugin slug used to scope migration records.
	 */
	public function __construct( string $plugin_slug ) {
		$this->plugin_slug = $plugin_slug;
	}

	/**
	 * Create the migrations tracking table.
	 *
	 * @return void
	 */
	public function create_repository(): void {
		$schema = new Schema();
		$schema->create(
			$this->table,
			function ( $table ) {
				$table->big_increments( 'id' );
				$table->string( 'migration' );
				$table->string( 'plugin_slug', 100 );
				$table->integer( 'batch' );
				$table->timestamps();
			}
		);
	}

	/**
	 * Check whether the migrations table exists.
	 *
	 * @return bool
	 */
	public function repository_exists(): bool {
		$schema = new Schema();
		return $schema->has_table( $this->table );
	}

	/**
	 * Get all migration names that have been run for this plugin.
	 *
	 * @return array
	 */
	public function get_ran(): array {
		global $wpdb;

		$prefixed = $wpdb->prefix . $this->table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is developer-controlled.
				"SELECT migration FROM {$prefixed} WHERE plugin_slug = %s ORDER BY batch, migration",
				$this->plugin_slug
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get migrations from the last batch (for rollback).
	 *
	 * @return array Array of objects with migration and batch properties.
	 */
	public function get_last_batch(): array {
		global $wpdb;

		$prefixed = $wpdb->prefix . $this->table;
		$batch    = $this->get_last_batch_number();

		if ( 0 === $batch ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is developer-controlled.
				"SELECT migration, batch FROM {$prefixed} WHERE plugin_slug = %s AND batch = %d ORDER BY migration DESC",
				$this->plugin_slug,
				$batch
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get the last batch number.
	 *
	 * @return int
	 */
	public function get_last_batch_number(): int {
		global $wpdb;

		$prefixed = $wpdb->prefix . $this->table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is developer-controlled.
				"SELECT MAX(batch) FROM {$prefixed} WHERE plugin_slug = %s",
				$this->plugin_slug
			)
		);

		return null !== $result ? (int) $result : 0;
	}

	/**
	 * Log that a migration has been run.
	 *
	 * @param string $migration Migration class name.
	 * @param int    $batch     Batch number.
	 * @return void
	 */
	public function log( string $migration, int $batch ): void {
		global $wpdb;

		$prefixed = $wpdb->prefix . $this->table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$prefixed,
			array(
				'migration'   => $migration,
				'plugin_slug' => $this->plugin_slug,
				'batch'       => $batch,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Delete a migration record.
	 *
	 * @param string $migration Migration class name.
	 * @return void
	 */
	public function delete( string $migration ): void {
		global $wpdb;

		$prefixed = $wpdb->prefix . $this->table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$prefixed,
			array(
				'migration'   => $migration,
				'plugin_slug' => $this->plugin_slug,
			),
			array( '%s', '%s' )
		);
	}

	/**
	 * Get all migration records for this plugin.
	 *
	 * @return array Array of objects with migration, batch, and created_at properties.
	 */
	public function get_all(): array {
		global $wpdb;

		$prefixed = $wpdb->prefix . $this->table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is developer-controlled.
				"SELECT migration, batch, created_at FROM {$prefixed} WHERE plugin_slug = %s ORDER BY batch, migration",
				$this->plugin_slug
			)
		);

		return is_array( $results ) ? $results : array();
	}
}
