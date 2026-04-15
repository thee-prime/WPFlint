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

/**
 * Get the Logger instance from the container.
 *
 * @return \WPFlint\Logging\LoggerInterface
 */
function logger_instance(): \WPFlint\Logging\LoggerInterface {
	try {
		return Application::get_instance()->make( 'logger' );
	} catch ( \Exception $e ) {
		return new \WPFlint\Logging\NullLogger();
	}
}

/**
 * Write a log entry via the registered logger.
 *
 * @param string               $message Log message (supports {placeholder} interpolation).
 * @param array<string, mixed> $context Context values for interpolation.
 * @param string               $level   PSR-3 log level. Default: debug.
 * @return void
 */
function log_message( string $message, array $context = array(), string $level = \WPFlint\Logging\LogLevel::DEBUG ): void {
	logger_instance()->log( $level, $message, $context );
}

/**
 * Dispatch a job onto the queue.
 *
 * @param \WPFlint\Queue\Job $job The job instance to dispatch.
 * @return int Inserted row ID.
 */
function dispatch_job( \WPFlint\Queue\Job $job ): int {
	return Application::get_instance()->make( 'queue' )->dispatch( $job );
}

/**
 * Get the Scheduler instance from the container.
 *
 * @return \WPFlint\Scheduling\Scheduler
 */
function scheduler(): \WPFlint\Scheduling\Scheduler {
	return Application::get_instance()->make( 'scheduler' );
}

/**
 * Dump one or more values to the screen and halt execution (like Laravel's dd()).
 *
 * Output is wrapped in `<pre>` tags when HTTP headers have not yet been sent.
 * In CLI / WP-CLI contexts the output is plain text.
 *
 * This function is intentionally a development/debug utility.
 *
 * @param mixed ...$values Values to dump.
 * @return void
 */
function dump_and_die( ...$values ): void {
	// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_dump, WordPress.Security.EscapeOutput.OutputNotEscaped
	$is_html = ! headers_sent() && 'cli' !== PHP_SAPI;

	if ( $is_html ) {
		echo '<pre style="background:#1e1e2e;color:#cdd6f4;padding:16px;border-radius:6px;font-size:13px;line-height:1.5;overflow:auto;">';
	}

	foreach ( $values as $value ) {
		var_dump( $value );
	}

	if ( $is_html ) {
		echo '</pre>';
	}
	// phpcs:enable

	die( 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- debug utility, intentional halt.
}
