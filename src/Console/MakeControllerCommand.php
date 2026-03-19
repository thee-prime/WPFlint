<?php
/**
 * WP-CLI command to generate controller stubs.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new controller file.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:controller OrderController
 *     wp wpflint make:controller OrderController --rest
 *     wp wpflint make:controller OrderController --path=app/Http/Controllers
 */
class MakeControllerCommand extends Command {

	/**
	 * Generate a controller file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The controller class name (PascalCase).
	 *
	 * [--rest]
	 * : Generate a REST API controller instead of an AJAX controller.
	 *
	 * [--path=<path>]
	 * : Directory for the controller file.
	 * ---
	 * default: app/Http/Controllers
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0];
		$path = $assoc_args['path'] ?? 'app/Http/Controllers';

		$base_dir = defined( 'ABSPATH' ) ? ABSPATH : '';
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/' . $name . '.php';

		$is_rest = isset( $assoc_args['rest'] );
		$stub    = $is_rest ? $this->get_rest_stub( $name ) : $this->get_stub( $name );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Get the controller stub content.
	 *
	 * @param string $name Controller class name.
	 * @return string
	 */
	private function get_stub( string $name ): string {
		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\\Http\\Controller;

class {$name} extends Controller {

	public function __construct() {
		//
	}
}

PHP;
	}

	/**
	 * Get the REST controller stub content.
	 *
	 * @param string $name Controller class name.
	 * @return string
	 */
	private function get_rest_stub( string $name ): string {
		$base = $this->snake_case(
			str_replace( 'Controller', '', $name )
		);

		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\\Http\\RestController;

class {$name} extends RestController {

	protected string \$namespace = 'my-plugin/v1';

	protected string \$rest_base = '{$base}';

	public function index( \WP_REST_Request \$request ): \WP_REST_Response {
		return \$this->respond( array() );
	}

	public function store( \WP_REST_Request \$request ): \WP_REST_Response {
		return \$this->respond( array(), 201 );
	}
}

PHP;
	}
}
