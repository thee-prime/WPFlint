<?php
/**
 * WP-CLI command to generate event stubs.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new event file.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:event OrderPlaced
 *     wp wpflint make:event OrderPlaced --path=app/Events
 */
class MakeEventCommand extends Command {

	/**
	 * Generate an event file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The event class name (PascalCase).
	 *
	 * [--path=<path>]
	 * : Directory for the event file.
	 * ---
	 * default: app/Events
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0];
		$path = $assoc_args['path'] ?? 'app/Events';

		$base_dir = defined( 'ABSPATH' ) ? ABSPATH : '';
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/' . $name . '.php';

		$stub = $this->get_stub( $name );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Get the event stub content.
	 *
	 * @param string $name Event class name.
	 * @return string
	 */
	private function get_stub( string $name ): string {
		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\\Events\\Event;

class {$name} extends Event {

	public function __construct() {
		//
	}
}

PHP;
	}
}
