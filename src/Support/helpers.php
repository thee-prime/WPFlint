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

if ( ! function_exists( 'wpflint_schedule' ) ) {
	/**
	 * Get the Scheduler instance (or schedule a callable directly).
	 *
	 * With no arguments, returns the Scheduler for chaining.
	 * With a callable, returns a new ScheduleEvent for fluent timing config.
	 *
	 * @param callable|null $callback Optional callback to schedule.
	 * @return \WPFlint\Scheduling\Scheduler|\WPFlint\Scheduling\ScheduleEvent
	 */
	function wpflint_schedule( ?callable $callback = null ) {
		$scheduler = \WPFlint\Support\scheduler();
		if ( null !== $callback ) {
			return $scheduler->call( $callback );
		}
		return $scheduler;
	}
}

if ( ! function_exists( 'wpflint_dispatch' ) ) {
	/**
	 * Dispatch a job onto the queue.
	 *
	 * @param \WPFlint\Queue\Job $job The job instance to dispatch.
	 * @return int Inserted row ID.
	 */
	function wpflint_dispatch( \WPFlint\Queue\Job $job ): int {
		return \WPFlint\Support\dispatch_job( $job );
	}
}

if ( ! function_exists( 'wpflint_log' ) ) {
	/**
	 * Write a log entry via the framework logger.
	 *
	 * @param string               $message Log message (supports {placeholder} interpolation).
	 * @param array<string, mixed> $context Interpolation context.
	 * @param string               $level   PSR-3 level. Default: debug.
	 * @return void
	 */
	function wpflint_log( string $message, array $context = array(), string $level = \WPFlint\Logging\LogLevel::DEBUG ): void {
		\WPFlint\Support\log_message( $message, $context, $level );
	}
}

if ( ! function_exists( 'wpflint_dd' ) ) {
	/**
	 * Dump one or more values and halt execution (development utility).
	 *
	 * @param mixed ...$values Values to dump.
	 * @return void
	 */
	function wpflint_dd( ...$values ): void {
		\WPFlint\Support\dump_and_die( ...$values );
	}
}
