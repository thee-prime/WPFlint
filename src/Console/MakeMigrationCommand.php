<?php
/**
 * WP-CLI command to generate migration stubs.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new migration file.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:migration CreateOrdersTable
 *     wp wpflint make:migration CreateOrdersTable --path=database/migrations
 */
class MakeMigrationCommand extends Command {

	/**
	 * Generate a migration file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The migration class name (PascalCase).
	 *
	 * [--path=<path>]
	 * : Directory for the migration file.
	 * ---
	 * default: database/migrations
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0];
		$path = $assoc_args['path'] ?? 'database/migrations';

		$base_dir = defined( 'ABSPATH' ) ? ABSPATH : '';
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );

		$filename = gmdate( 'Y_m_d_His' ) . '_' . $this->snake_case( $name ) . '.php';
		$filepath = $dir . '/' . $filename;

		$stub = $this->get_stub( $name );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Get the migration stub content.
	 *
	 * @param string $name Migration class name.
	 * @return string
	 */
	private function get_stub( string $name ): string {
		$table = $this->guess_table_name( $name );

		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\\Database\\Migrations\\Migration;
use WPFlint\\Database\\Schema\\Blueprint;

class {$name} extends Migration {

	public function up(): void {
		\$this->schema()->create( '{$table}', function ( Blueprint \$table ) {
			\$table->big_increments( 'id' );
			\$table->timestamps();
		} );
	}

	public function down(): void {
		\$this->schema()->drop( '{$table}' );
	}
}

PHP;
	}

	/**
	 * Guess the table name from the migration name.
	 *
	 * CreateOrdersTable -> orders
	 * CreateUserProfilesTable -> user_profiles
	 *
	 * @param string $name Migration class name.
	 * @return string
	 */
	private function guess_table_name( string $name ): string {
		$name = preg_replace( '/^Create/', '', $name );
		$name = preg_replace( '/Table$/', '', $name );
		return $this->snake_case( $name );
	}
}
