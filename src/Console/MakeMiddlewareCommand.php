<?php
/**
 * WP-CLI command to generate middleware stubs.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new middleware file.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:middleware EnsureStoreIsOpen
 *     wp wpflint make:middleware EnsureStoreIsOpen --path=app/Http/Middleware
 */
class MakeMiddlewareCommand extends Command {

	/**
	 * Generate a middleware file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The middleware class name (PascalCase).
	 *
	 * [--path=<path>]
	 * : Directory for the middleware file.
	 * ---
	 * default: app/Http/Middleware
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0];
		$path = $assoc_args['path'] ?? 'app/Http/Middleware';

		$base_dir = defined( 'ABSPATH' ) ? ABSPATH : '';
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/' . $name . '.php';

		$stub = $this->get_stub( $name );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Get the middleware stub content.
	 *
	 * @param string $name Middleware class name.
	 * @return string
	 */
	private function get_stub( string $name ): string {
		return <<<PHP
<?php

declare(strict_types=1);

use Closure;
use WPFlint\\Http\\Request;
use WPFlint\\Http\\Middleware\\MiddlewareInterface;

class {$name} implements MiddlewareInterface {

	public function handle( Request \$request, Closure \$next ) {
		return \$next( \$request );
	}
}

PHP;
	}
}
