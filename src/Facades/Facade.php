<?php
/**
 * Abstract facade base class for static proxy access.
 *
 * @package WPFlint\Facades
 */

declare(strict_types=1);

namespace WPFlint\Facades;

use WPFlint\Application;

/**
 * Base class for all facades. Resolves the underlying instance from the container
 * and forwards static calls to it.
 */
abstract class Facade {

	/**
	 * Cached resolved facade instances.
	 *
	 * @var array<string, mixed>
	 */
	protected static array $resolved_instances = array();

	/**
	 * Get the registered name of the component in the container.
	 *
	 * @return string
	 */
	abstract protected static function get_facade_accessor(): string;

	/**
	 * Resolve the facade root instance from the container.
	 *
	 * @param string $name The container binding name.
	 *
	 * @return mixed
	 */
	protected static function resolve_facade_instance( string $name ) {
		if ( isset( static::$resolved_instances[ $name ] ) ) {
			return static::$resolved_instances[ $name ];
		}

		$instance = Application::get_instance()->make( $name );

		static::$resolved_instances[ $name ] = $instance;

		return $instance;
	}

	/**
	 * Handle dynamic static calls by forwarding to the resolved instance.
	 *
	 * @param string       $method The method name.
	 * @param array<mixed> $args   The method arguments.
	 *
	 * @return mixed
	 */
	public static function __callStatic( string $method, array $args ) {
		$instance = static::resolve_facade_instance( static::get_facade_accessor() );

		return $instance->$method( ...$args );
	}

	/**
	 * Clear all resolved facade instances (for testing).
	 *
	 * @return void
	 */
	public static function clear_resolved_instances(): void {
		static::$resolved_instances = array();
	}
}
