<?php
/**
 * WP-CLI command to generate service provider stubs.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new service provider file.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:provider OrderServiceProvider
 *     wp wpflint make:provider OrderServiceProvider --path=app/Providers
 */
class MakeProviderCommand extends Command {

	/**
	 * Generate a service provider file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The provider class name (PascalCase).
	 *
	 * [--path=<path>]
	 * : Directory for the provider file.
	 * ---
	 * default: app/Providers
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0];
		$path = $assoc_args['path'] ?? 'app/Providers';

		$base_dir = defined( 'ABSPATH' ) ? ABSPATH : '';
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/' . $name . '.php';

		$stub = $this->get_stub( $name );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Get the provider stub content.
	 *
	 * @param string $name Provider class name.
	 * @return string
	 */
	private function get_stub( string $name ): string {
		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\\Providers\\ServiceProvider;

class {$name} extends ServiceProvider {

	public function register(): void {
		//
	}

	public function boot(): void {
		//
	}
}

PHP;
	}
}
