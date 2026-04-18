<?php
/**
 * Plugin helper functions.
 *
 * These are thin wrappers around the WPFlint canonical implementations.
 * You can use zplane_config(), zplane_app(), etc. throughout your
 * plugin codebase instead of the wpflint_* variants.
 *
 * Load this file early in your plugin bootstrap, before the autoloader:
 *
 *     require_once __DIR__ . '/app/helpers.php';
 */

declare(strict_types=1);

if ( ! function_exists( 'zplane_config' ) ) {
	/**
	 * Get a configuration value via dot-notation.
	 *
	 * @param string $key     Dot-notation key, e.g. 'app.name'.
	 * @param mixed  $default Returned when the key is not found.
	 * @return mixed
	 */
	function zplane_config( string $key, $default = null ) {
		return \WPFlint\Support\config_value( $key, $default );
	}
}

if ( ! function_exists( 'zplane_app' ) ) {
	/**
	 * Resolve a binding from the container, or return the Application.
	 *
	 * @param string|null $abstract Abstract type or alias.
	 * @return mixed
	 */
	function zplane_app( ?string $abstract = null ) {
		return \WPFlint\Support\app_make( $abstract );
	}
}

if ( ! function_exists( 'zplane_env' ) ) {
	/**
	 * Read a WP constant or $_ENV value.
	 *
	 * @param string $key     Constant / env variable name.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	function zplane_env( string $key, $default = null ) {
		return \WPFlint\Support\env_value( $key, $default );
	}
}

if ( ! function_exists( 'zplane_event' ) ) {
	/**
	 * Fire an event.
	 *
	 * @param object $event Event instance.
	 * @return void
	 */
	function zplane_event( object $event ): void {
		\WPFlint\Support\fire_event( $event );
	}
}

if ( ! function_exists( 'zplane_cache' ) ) {
	/**
	 * Get the cache manager, optionally scoped to a tag.
	 *
	 * @param string|string[]|null $tags Optional cache tag(s).
	 * @return \WPFlint\Cache\CacheManager|\WPFlint\Cache\TaggedCache
	 */
	function zplane_cache( $tags = null ) {
		return \WPFlint\Support\cache_manager( $tags );
	}
}
