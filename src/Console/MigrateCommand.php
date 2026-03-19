<?php
/**
 * WP-CLI command for database migrations.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

use WPFlint\Database\Migrations\Migrator;
use WPFlint\Database\Migrations\MigrationRepository;

/**
 * Manages database migrations via WP-CLI.
 *
 * ## EXAMPLES
 *
 *     wp wpflint migrate
 *     wp wpflint migrate --rollback
 *     wp wpflint migrate --rollback --steps=2
 *     wp wpflint migrate --fresh
 *     wp wpflint migrate --status
 */
class MigrateCommand extends Command {

	/**
	 * Migrator instance.
	 *
	 * @var Migrator
	 */
	private Migrator $migrator;

	/**
	 * Migration repository.
	 *
	 * @var MigrationRepository
	 */
	private MigrationRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param Migrator            $migrator   Migrator instance.
	 * @param MigrationRepository $repository Migration repository.
	 */
	public function __construct( Migrator $migrator, MigrationRepository $repository ) {
		$this->migrator   = $migrator;
		$this->repository = $repository;
	}

	/**
	 * Run database migrations.
	 *
	 * ## OPTIONS
	 *
	 * [--rollback]
	 * : Roll back the last batch of migrations.
	 *
	 * [--steps=<steps>]
	 * : Number of batches to roll back.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--fresh]
	 * : Drop all tables and re-run all migrations.
	 *
	 * [--status]
	 * : Show the status of each migration.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$this->ensure_repository();

		if ( isset( $assoc_args['status'] ) ) {
			$this->show_status();
			return;
		}

		if ( isset( $assoc_args['fresh'] ) ) {
			$this->run_fresh();
			return;
		}

		if ( isset( $assoc_args['rollback'] ) ) {
			$steps = isset( $assoc_args['steps'] ) ? (int) $assoc_args['steps'] : 1;
			$this->run_rollback( $steps );
			return;
		}

		$this->run_migrations();
	}

	/**
	 * Ensure the migration repository table exists.
	 *
	 * @return void
	 */
	private function ensure_repository(): void {
		if ( ! $this->repository->repository_exists() ) {
			$this->repository->create_repository();
			$this->info( __( 'Migration table created.', 'wpflint' ) );
		}
	}

	/**
	 * Run pending migrations.
	 *
	 * @return void
	 */
	private function run_migrations(): void {
		$ran = $this->migrator->run();

		if ( empty( $ran ) ) {
			$this->success( __( 'Nothing to migrate.', 'wpflint' ) );
			return;
		}

		foreach ( $ran as $migration ) {
			$this->info(
				sprintf(
					/* translators: %s: migration class name */
					__( 'Migrated: %s', 'wpflint' ),
					$migration
				)
			);
		}

		$this->success(
			sprintf(
				/* translators: %d: number of migrations run */
				__( 'Ran %d migration(s).', 'wpflint' ),
				count( $ran )
			)
		);
	}

	/**
	 * Roll back migrations.
	 *
	 * @param int $steps Number of batches to roll back.
	 * @return void
	 */
	private function run_rollback( int $steps ): void {
		$rolled_back = $this->migrator->rollback( $steps );

		if ( empty( $rolled_back ) ) {
			$this->success( __( 'Nothing to rollback.', 'wpflint' ) );
			return;
		}

		foreach ( $rolled_back as $migration ) {
			$this->info(
				sprintf(
					/* translators: %s: migration class name */
					__( 'Rolled back: %s', 'wpflint' ),
					$migration
				)
			);
		}

		$this->success(
			sprintf(
				/* translators: %d: number of migrations rolled back */
				__( 'Rolled back %d migration(s).', 'wpflint' ),
				count( $rolled_back )
			)
		);
	}

	/**
	 * Drop all tables and re-run migrations.
	 *
	 * @return void
	 */
	private function run_fresh(): void {
		$this->confirm( __( 'This will drop ALL tables and re-run migrations. Continue?', 'wpflint' ) );

		$ran = $this->migrator->fresh();

		foreach ( $ran as $migration ) {
			$this->info(
				sprintf(
					/* translators: %s: migration class name */
					__( 'Migrated: %s', 'wpflint' ),
					$migration
				)
			);
		}

		$this->success( __( 'Database was refreshed.', 'wpflint' ) );
	}

	/**
	 * Show migration status.
	 *
	 * @return void
	 */
	private function show_status(): void {
		$status = $this->migrator->get_status();

		if ( empty( $status ) ) {
			$this->info( __( 'No migrations registered.', 'wpflint' ) );
			return;
		}

		foreach ( $status as $entry ) {
			$indicator = $entry['ran'] ? '[x]' : '[ ]';
			$this->info( sprintf( '%s %s', $indicator, $entry['migration'] ) );
		}
	}
}
