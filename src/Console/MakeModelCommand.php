<?php
/**
 * WP-CLI command to generate model stubs.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new model file.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:model Order
 *     wp wpflint make:model Order --path=app/Models
 *     wp wpflint make:model Order --migration
 */
class MakeModelCommand extends Command {

	/**
	 * Generate a model file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The model class name (PascalCase).
	 *
	 * [--path=<path>]
	 * : Directory for the model file.
	 * ---
	 * default: app/Models
	 * ---
	 *
	 * [--migration]
	 * : Also generate a migration for this model.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0];
		$path = $assoc_args['path'] ?? 'app/Models';

		$base_dir = defined( 'ABSPATH' ) ? ABSPATH : '';
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/' . $name . '.php';

		$stub = $this->get_stub( $name );
		$this->write_file( $filepath, $stub );

		if ( isset( $assoc_args['migration'] ) ) {
			$table_name     = $this->snake_case( $name ) . 's';
			$migration_name = 'Create' . $name . 'sTable';

			$migration_cmd = new MakeMigrationCommand();
			$migration_cmd->__invoke( array( $migration_name ), $assoc_args );
		}
	}

	/**
	 * Get the model stub content.
	 *
	 * @param string $name Model class name.
	 * @return string
	 */
	private function get_stub( string $name ): string {
		$table = $this->snake_case( $name ) . 's';

		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\\Database\\ORM\\Model;

class {$name} extends Model {

	protected static string \$table = '{$table}';

	protected array \$fillable = array();

	protected array \$casts = array();
}

PHP;
	}
}
