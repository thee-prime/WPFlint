<?php
/**
 * WP-CLI command to generate event listener stubs.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new event listener file.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:listener SendOrderConfirmation
 *     wp wpflint make:listener SendOrderConfirmation --event=OrderPlaced
 *     wp wpflint make:listener SendOrderConfirmation --path=app/Listeners
 */
class MakeListenerCommand extends Command {

	/**
	 * Generate an event listener file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The listener class name (PascalCase).
	 *
	 * [--event=<event>]
	 * : The event class this listener handles (used in type-hint).
	 *
	 * [--path=<path>]
	 * : Directory for the listener file.
	 * ---
	 * default: app/Listeners
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name  = $args[0];
		$event = $assoc_args['event'] ?? '';
		$path  = $assoc_args['path'] ?? 'app/Listeners';

		$base_dir = getcwd();
		$dir      = '/' === $path[0] ? $path : rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/' . $name . '.php';

		$stub = $this->get_stub( $name, $event );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Get the listener stub content.
	 *
	 * @param string $name  Listener class name.
	 * @param string $event Optional event class name.
	 * @return string
	 */
	private function get_stub( string $name, string $event ): string {
		$type_hint = '' !== $event ? $event . ' $event' : '$event';
		$use_event = '' !== $event ? "\nuse {$event};\n" : '';

		return <<<PHP
<?php

declare(strict_types=1);
{$use_event}
class {$name} {

	/**
	 * Handle the event.
	 *
	 * @param {$type_hint}
	 * @return void
	 */
	public function handle( {$type_hint} ): void {
		//
	}
}

PHP;
	}
}
