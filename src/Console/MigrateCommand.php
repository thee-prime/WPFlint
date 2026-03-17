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
 *     wp wpflint migrate --make=CreateOrdersTable
 */
class MigrateCommand {

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
	 * [--make=<name>]
	 * : Generate a new migration file stub.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$this->ensure_repository();

		if ( isset( $assoc_args['make'] ) ) {
			$this->make_migration( $assoc_args['make'] );
			return;
		}

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
			\WP_CLI::line( __( 'Migration table created.', 'wpflint' ) );
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
			\WP_CLI::success( __( 'Nothing to migrate.', 'wpflint' ) );
			return;
		}

		foreach ( $ran as $migration ) {
			\WP_CLI::line(
				sprintf(
				/* translators: %s: migration class name */
					__( 'Migrated: %s', 'wpflint' ),
					$migration
				)
			);
		}

		\WP_CLI::success(
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
			\WP_CLI::success( __( 'Nothing to rollback.', 'wpflint' ) );
			return;
		}

		foreach ( $rolled_back as $migration ) {
			\WP_CLI::line(
				sprintf(
				/* translators: %s: migration class name */
					__( 'Rolled back: %s', 'wpflint' ),
					$migration
				)
			);
		}

		\WP_CLI::success(
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
		\WP_CLI::confirm( __( 'This will drop ALL tables and re-run migrations. Continue?', 'wpflint' ) );

		$ran = $this->migrator->fresh();

		foreach ( $ran as $migration ) {
			\WP_CLI::line(
				sprintf(
				/* translators: %s: migration class name */
					__( 'Migrated: %s', 'wpflint' ),
					$migration
				)
			);
		}

		\WP_CLI::success( __( 'Database was refreshed.', 'wpflint' ) );
	}

	/**
	 * Show migration status.
	 *
	 * @return void
	 */
	private function show_status(): void {
		$status = $this->migrator->get_status();

		if ( empty( $status ) ) {
			\WP_CLI::line( __( 'No migrations registered.', 'wpflint' ) );
			return;
		}

		foreach ( $status as $entry ) {
			$indicator = $entry['ran'] ? '[x]' : '[ ]';
			\WP_CLI::line( sprintf( '%s %s', $indicator, $entry['migration'] ) );
		}
	}

	/**
	 * Generate a migration file stub.
	 *
	 * @param string $name Migration class name.
	 * @return void
	 */
	private function make_migration( string $name ): void {
		$directory  = defined( 'ABSPATH' ) ? ABSPATH : '';
		$directory .= 'database/migrations';

		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}

		$filename = gmdate( 'Y_m_d_His' ) . '_' . $this->snake_case( $name ) . '.php';
		$filepath = $directory . '/' . $filename;

		$stub = $this->get_migration_stub( $name );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI dev tool, not production code.
		file_put_contents( $filepath, $stub );

		\WP_CLI::success(
			sprintf(
			/* translators: %s: file path */
				__( 'Created migration: %s', 'wpflint' ),
				$filepath
			)
		);
	}

	/**
	 * Get the migration stub content.
	 *
	 * @param string $name Migration class name.
	 * @return string
	 */
	private function get_migration_stub( string $name ): string {
		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\Database\Migrations\Migration;

class {$name} extends Migration {

	public function up(): void {
		\$this->schema()->create( 'table_name', function ( \$table ) {
			\$table->big_increments( 'id' );
			\$table->timestamps();
		} );
	}

	public function down(): void {
		\$this->schema()->drop( 'table_name' );
	}
}

PHP;
	}

	/**
	 * Convert a string to snake_case.
	 *
	 * @param string $value Input string.
	 * @return string
	 */
	private function snake_case( string $value ): string {
		$value = preg_replace( '/([a-z])([A-Z])/', '$1_$2', $value );
		return strtolower( $value );
	}
}
