<?php
/**
 * WP-CLI command to generate facade stubs.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new facade file.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:facade Order
 *     wp wpflint make:facade Order --path=app/Facades
 */
class MakeFacadeCommand extends Command {

	/**
	 * Generate a facade file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The facade class name (PascalCase).
	 *
	 * [--path=<path>]
	 * : Directory for the facade file.
	 * ---
	 * default: app/Facades
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0];
		$path = $assoc_args['path'] ?? 'app/Facades';

		$base_dir = defined( 'ABSPATH' ) ? ABSPATH : '';
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/' . $name . '.php';

		$stub = $this->get_stub( $name );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Get the facade stub content.
	 *
	 * @param string $name Facade class name.
	 * @return string
	 */
	private function get_stub( string $name ): string {
		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\\Facades\\Facade;

class {$name} extends Facade {

	protected static function get_facade_accessor(): string {
		return '';
	}
}

PHP;
	}
}
