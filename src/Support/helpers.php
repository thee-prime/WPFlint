<?php
/**
 * Global helper functions — no namespace, loaded via composer autoload.files.
 *
 * Every function is guarded with if ( ! function_exists() ) so plugin authors
 * can define their own prefixed version that calls through to the same
 * internals, e.g.:
 *
 *     // In my-plugin/app/helpers.php, loaded early in plugin bootstrap:
 *     if ( ! function_exists( 'zplane_config' ) ) {
 *         function zplane_config( string $key, $default = null ) {
 *             return \WPFlint\Support\config_value( $key, $default );
 *         }
 *     }
 *
 * @package WPFlint
 */

if ( ! function_exists( 'wpflint_config' ) ) {
	/**
	 * Get a configuration value via dot-notation.
	 *
	 * Usage: wpflint_config('app.name') — get.
	 * Usage: wpflint_config('app.name', 'MyApp') — get with default.
	 *
	 * @param string $key     Dot-notation config key.
	 * @param mixed  $default Default value when key is absent.
	 * @return mixed
	 */
	function wpflint_config( string $key, $default = null ) {
		return \WPFlint\Support\config_value( $key, $default );
	}
}

if ( ! function_exists( 'wpflint_app' ) ) {
	/**
	 * Resolve a binding from the container, or return the Application itself.
	 *
	 * @param string|null $abstract Abstract type / alias.
	 * @return mixed
	 */
	function wpflint_app( ?string $abstract = null ) {
		return \WPFlint\Support\app_make( $abstract );
	}
}

if ( ! function_exists( 'wpflint_env' ) ) {
	/**
	 * Read an environment / constant value.
	 *
	 * @param string $key     WP constant name or $_ENV key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	function wpflint_env( string $key, $default = null ) {
		return \WPFlint\Support\env_value( $key, $default );
	}
}

if ( ! function_exists( 'wpflint_event' ) ) {
	/**
	 * Fire an event.
	 *
	 * @param \WPFlint\Events\Event $event Event instance.
	 * @return void
	 */
	function wpflint_event( \WPFlint\Events\Event $event ): void {
		\WPFlint\Support\fire_event( $event );
	}
}

if ( ! function_exists( 'wpflint_cache' ) ) {
	/**
	 * Get the cache manager, optionally scoped to tag(s).
	 *
	 * @param string|string[]|null $tags Optional cache tag(s).
	 * @return \WPFlint\Cache\CacheManager|\WPFlint\Cache\TaggedCache
	 */
	function wpflint_cache( $tags = null ) {
		return \WPFlint\Support\cache_manager( $tags );
	}
}
