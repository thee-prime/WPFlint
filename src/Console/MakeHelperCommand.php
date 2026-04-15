<?php
/**
 * WP-CLI command to generate a prefixed helpers stub.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Scaffolds a helpers.php file with the plugin's own function prefix.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:helper --prefix=zplane
 *     wp wpflint make:helper --prefix=my_plugin --path=app
 */
class MakeHelperCommand extends Command {

	/**
	 * Generate a prefixed helpers file.
	 *
	 * ## OPTIONS
	 *
	 * --prefix=<prefix>
	 * : The function prefix to use (e.g. zplane → zplane_config()).
	 *
	 * [--path=<path>]
	 * : Directory to write helpers.php into.
	 * ---
	 * default: app
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$prefix = $assoc_args['prefix'] ?? '';
		$path   = $assoc_args['path'] ?? 'app';

		if ( '' === $prefix ) {
			$this->error( __( 'Please supply --prefix=<your_prefix>', 'wpflint' ) );
			return;
		}

		$base_dir = defined( 'ABSPATH' ) ? ABSPATH : '';
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/helpers.php';

		$stub = $this->get_stub( $prefix );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Generate the helpers stub for the given prefix.
	 *
	 * @param string $prefix Function prefix, e.g. 'zplane'.
	 * @return string
	 */
	private function get_stub( string $prefix ): string {
		return <<<PHP
<?php
/**
 * Plugin helper functions.
 *
 * These are thin wrappers around the WPFlint canonical implementations.
 * You can use {$prefix}_config(), {$prefix}_app(), etc. throughout your
 * plugin codebase instead of the wpflint_* variants.
 *
 * Load this file early in your plugin bootstrap, before the autoloader:
 *
 *     require_once __DIR__ . '/app/helpers.php';
 */

declare(strict_types=1);

if ( ! function_exists( '{$prefix}_config' ) ) {
	/**
	 * Get a configuration value via dot-notation.
	 *
	 * @param string \$key     Dot-notation key, e.g. 'app.name'.
	 * @param mixed  \$default Returned when the key is not found.
	 * @return mixed
	 */
	function {$prefix}_config( string \$key, \$default = null ) {
		return \\WPFlint\\Support\\config_value( \$key, \$default );
	}
}

if ( ! function_exists( '{$prefix}_app' ) ) {
	/**
	 * Resolve a binding from the container, or return the Application.
	 *
	 * @param string|null \$abstract Abstract type or alias.
	 * @return mixed
	 */
	function {$prefix}_app( ?string \$abstract = null ) {
		return \\WPFlint\\Support\\app_make( \$abstract );
	}
}

if ( ! function_exists( '{$prefix}_env' ) ) {
	/**
	 * Read a WP constant or \$_ENV value.
	 *
	 * @param string \$key     Constant / env variable name.
	 * @param mixed  \$default Fallback value.
	 * @return mixed
	 */
	function {$prefix}_env( string \$key, \$default = null ) {
		return \\WPFlint\\Support\\env_value( \$key, \$default );
	}
}

if ( ! function_exists( '{$prefix}_event' ) ) {
	/**
	 * Fire an event.
	 *
	 * @param object \$event Event instance.
	 * @return void
	 */
	function {$prefix}_event( object \$event ): void {
		\\WPFlint\\Support\\fire_event( \$event );
	}
}

if ( ! function_exists( '{$prefix}_cache' ) ) {
	/**
	 * Get the cache manager, optionally scoped to a tag.
	 *
	 * @param string|string[]|null \$tags Optional cache tag(s).
	 * @return \\WPFlint\\Cache\\CacheManager|\\WPFlint\\Cache\\TaggedCache
	 */
	function {$prefix}_cache( \$tags = null ) {
		return \\WPFlint\\Support\\cache_manager( \$tags );
	}
}

PHP;
	}
}
