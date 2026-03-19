<?php
/**
 * WP-CLI command to generate request stubs.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new form request file.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:request StoreOrderRequest
 *     wp wpflint make:request StoreOrderRequest --path=app/Http/Requests
 */
class MakeRequestCommand extends Command {

	/**
	 * Generate a form request file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The request class name (PascalCase).
	 *
	 * [--path=<path>]
	 * : Directory for the request file.
	 * ---
	 * default: app/Http/Requests
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0];
		$path = $assoc_args['path'] ?? 'app/Http/Requests';

		$base_dir = defined( 'ABSPATH' ) ? ABSPATH : '';
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/' . $name . '.php';

		$stub = $this->get_stub( $name );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Get the request stub content.
	 *
	 * @param string $name Request class name.
	 * @return string
	 */
	private function get_stub( string $name ): string {
		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\\Http\\Request;

class {$name} extends Request {

	public function authorize(): bool {
		return false;
	}

	public function rules(): array {
		return array();
	}

	public function sanitize(): array {
		return array();
	}
}

PHP;
	}
}
