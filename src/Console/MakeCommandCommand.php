<?php
/**
 * WP-CLI command to generate custom WP-CLI command stubs.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new WP-CLI command class.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:command SyncInventoryCommand
 *     wp wpflint make:command SyncInventoryCommand --path=app/Console
 */
class MakeCommandCommand extends Command {

	/**
	 * Generate a WP-CLI command file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The command class name (PascalCase, should end in Command).
	 *
	 * [--path=<path>]
	 * : Directory for the command file.
	 * ---
	 * default: app/Console
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0];
		$path = $assoc_args['path'] ?? 'app/Console';

		$base_dir = getcwd();
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/' . $name . '.php';

		$stub = $this->get_stub( $name );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Get the command stub content.
	 *
	 * @param string $name Command class name.
	 * @return string
	 */
	private function get_stub( string $name ): string {
		$command_name = $this->snake_case(
			preg_replace( '/Command$/', '', $name )
		);

		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\\Console\\Command;

/**
 * ## EXAMPLES
 *
 *     wp wpflint {$command_name}
 */
class {$name} extends Command {

	/**
	 * Execute the command.
	 *
	 * ## OPTIONS
	 *
	 * [--flag=<value>]
	 * : Example optional flag.
	 *
	 * @param array \$args       Positional arguments.
	 * @param array \$assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array \$args, array \$assoc_args ): void {
		\$this->info( __( 'Running {$command_name}...', 'text-domain' ) );

		// TODO: implement command logic.

		\$this->success( __( 'Done.', 'text-domain' ) );
	}
}

PHP;
	}
}
