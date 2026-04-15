<?php
/**
 * Namespaced canonical helper implementations.
 *
 * Plugin authors can alias these directly to create their own prefix:
 *
 *     function zplane_config( string $key, $default = null ) {
 *         return \WPFlint\Support\config_value( $key, $default );
 *     }
 *
 * @package WPFlint\Support
 */

declare(strict_types=1);

namespace WPFlint\Support;

use WPFlint\Application;

/**
 * Get a configuration value via dot-notation.
 *
 * @param string $key     Dot-notation key, e.g. 'app.name'.
 * @param mixed  $default Returned when the key is not found.
 * @return mixed
 */
function config_value( string $key, $default = null ) {
	try {
		return Application::get_instance()->make( 'config' )->get( $key, $default );
	} catch ( \Exception $e ) {
		return $default; // App not booted or config not bound.
	}
}

/**
 * Set a configuration value at runtime.
 *
 * @param string $key   Dot-notation key.
 * @param mixed  $value Value to set.
 * @return void
 */
function config_set( string $key, $value ): void {
	try {
		Application::get_instance()->make( 'config' )->set( $key, $value );
	} catch ( \Exception $e ) {
		return; // Silently fail if app not yet booted.
	}
}

/**
 * Resolve a binding from the application container.
 *
 * @param string|null $abstract Abstract type or alias. Null returns the Application.
 * @return mixed
 */
function app_make( ?string $abstract = null ) {
	$instance = Application::get_instance();

	if ( null === $abstract ) {
		return $instance;
	}

	return $instance->make( $abstract );
}

/**
 * Read an environment value: WP constant first, then $_ENV, then default.
 *
 * @param string $key     Constant or environment variable name.
 * @param mixed  $default Fallback value.
 * @return mixed
 */
function env_value( string $key, $default = null ) {
	if ( defined( $key ) ) {
		return constant( $key );
	}

	if ( isset( $_ENV[ $key ] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw env value; caller sanitizes.
		return $_ENV[ $key ];
	}

	return $default;
}

/**
 * Fire an event through the application dispatcher.
 *
 * @param \WPFlint\Events\Event $event The event instance to dispatch.
 * @return void
 */
function fire_event( \WPFlint\Events\Event $event ): void {
	try {
		Application::get_instance()->make( 'events' )->fire( $event );
	} catch ( \Exception $e ) {
		// Silently fail — dispatcher not bound or app not booted.
		return;
	}
}

/**
 * Get the CacheManager instance (or a tagged cache if tags are supplied).
 *
 * @param string|string[]|null $tags Optional cache tag(s).
 * @return \WPFlint\Cache\CacheManager|\WPFlint\Cache\TaggedCache
 */
function cache_manager( $tags = null ) {
	$cache = Application::get_instance()->make( 'cache' );

	if ( null !== $tags ) {
		return $cache->tags( $tags );
	}

	return $cache;
}
