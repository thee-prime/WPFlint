<?php
/**
 * Migration orchestrator — runs, rolls back, and reports on migrations.
 *
 * @package WPFlint\Database\Migrations
 */

declare(strict_types=1);

namespace WPFlint\Database\Migrations;

use RuntimeException;

/**
 * Coordinates running and rolling back migrations.
 */
class Migrator {

	/**
	 * Migration repository for tracking runs.
	 *
	 * @var MigrationRepository
	 */
	private MigrationRepository $repository;

	/**
	 * Ordered array of migration class names (FQCNs).
	 *
	 * @var array
	 */
	private array $migrations;

	/**
	 * Constructor.
	 *
	 * @param MigrationRepository $repository Migration repository.
	 * @param array               $migrations Ordered array of migration class names.
	 */
	public function __construct( MigrationRepository $repository, array $migrations = array() ) {
		$this->repository = $repository;
		$this->migrations = $migrations;
	}

	/**
	 * Run all pending migrations.
	 *
	 * @return array Names of migrations that were run.
	 */
	public function run(): array {
		$pending = $this->get_pending();

		if ( empty( $pending ) ) {
			return array();
		}

		$batch = $this->repository->get_last_batch_number() + 1;
		$ran   = array();

		foreach ( $pending as $migration_class ) {
			$instance = $this->resolve( $migration_class );
			$instance->up();
			$this->repository->log( $migration_class, $batch );
			$ran[] = $migration_class;
		}

		return $ran;
	}

	/**
	 * Rollback the last N batches of migrations.
	 *
	 * @param int $steps Number of batches to roll back.
	 * @return array Names of migrations that were rolled back.
	 */
	public function rollback( int $steps = 1 ): array {
		$rolled_back = array();

		for ( $i = 0; $i < $steps; $i++ ) {
			$batch = $this->repository->get_last_batch();

			if ( empty( $batch ) ) {
				break;
			}

			foreach ( $batch as $row ) {
				$instance = $this->resolve( $row->migration );
				$instance->down();
				$this->repository->delete( $row->migration );
				$rolled_back[] = $row->migration;
			}
		}

		return $rolled_back;
	}

	/**
	 * Drop all tables and re-run all migrations.
	 *
	 * Only allowed in WP_CLI context to prevent accidental data loss.
	 *
	 * @return array Names of migrations that were run.
	 * @throws RuntimeException If called outside WP_CLI context.
	 */
	public function fresh(): array {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			throw new RuntimeException(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
				__( 'The fresh command is destructive and can only be run from WP-CLI.', 'wpflint' )
			);
		}

		// Roll back everything by running down() on all ran migrations in reverse.
		$ran = array_reverse( $this->repository->get_ran() );

		foreach ( $ran as $migration_class ) {
			$instance = $this->resolve( $migration_class );
			$instance->down();
			$this->repository->delete( $migration_class );
		}

		// Run all migrations from scratch.
		return $this->run();
	}

	/**
	 * Get the status of all known migrations.
	 *
	 * @return array Array of ['migration' => name, 'ran' => bool].
	 */
	public function get_status(): array {
		$ran    = $this->repository->get_ran();
		$status = array();

		foreach ( $this->migrations as $migration_class ) {
			$status[] = array(
				'migration' => $migration_class,
				'ran'       => in_array( $migration_class, $ran, true ),
			);
		}

		return $status;
	}

	/**
	 * Get migration class names that have not yet been run.
	 *
	 * @return array
	 */
	public function get_pending(): array {
		$ran = $this->repository->get_ran();

		return array_values(
			array_filter(
				$this->migrations,
				function ( string $migration ) use ( $ran ): bool {
					return ! in_array( $migration, $ran, true );
				}
			)
		);
	}

	/**
	 * Resolve a migration class name to an instance.
	 *
	 * @param string $migration_class Fully qualified class name.
	 * @return Migration
	 */
	public function resolve( string $migration_class ): Migration {
		return new $migration_class();
	}
}
