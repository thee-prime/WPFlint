<?php
/**
 * Dot-notation configuration repository.
 *
 * @package WPFlint\Config
 */

declare(strict_types=1);

namespace WPFlint\Config;

/**
 * Stores and retrieves configuration values using dot-notation keys.
 */
class Repository {

	/**
	 * All configuration items.
	 *
	 * @var array<string, mixed>
	 */
	protected array $items = array();

	/**
	 * Create a new configuration repository.
	 *
	 * @param array<string, mixed> $items Initial configuration items.
	 */
	public function __construct( array $items = array() ) {
		$this->items = $items;
	}

	/**
	 * Get a configuration value using dot-notation.
	 *
	 * @param string $key     The dot-notation key.
	 * @param mixed  $default Default value if key not found.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$segments = explode( '.', $key );
		$current  = $this->items;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return $default;
			}

			$current = $current[ $segment ];
		}

		return $current;
	}

	/**
	 * Set a configuration value using dot-notation.
	 *
	 * @param string $key   The dot-notation key.
	 * @param mixed  $value The value to set.
	 *
	 * @return void
	 */
	public function set( string $key, $value ): void {
		$segments = explode( '.', $key );
		$current  = &$this->items;

		foreach ( $segments as $i => $segment ) {
			if ( count( $segments ) - 1 === $i ) {
				$current[ $segment ] = $value;
			} else {
				if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) {
					$current[ $segment ] = array();
				}

				$current = &$current[ $segment ];
			}
		}
	}

	/**
	 * Check if a configuration key exists using dot-notation.
	 *
	 * @param string $key The dot-notation key.
	 *
	 * @return bool
	 */
	public function has( string $key ): bool {
		$segments = explode( '.', $key );
		$current  = $this->items;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return false;
			}

			$current = $current[ $segment ];
		}

		return true;
	}

	/**
	 * Get all configuration items.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->items;
	}

	/**
	 * Push a value onto an array configuration value.
	 *
	 * @param string $key   The dot-notation key.
	 * @param mixed  $value The value to push.
	 *
	 * @return void
	 */
	public function push( string $key, $value ): void {
		$array   = $this->get( $key, array() );
		$array[] = $value;

		$this->set( $key, $array );
	}

	/**
	 * Get a value from environment: WP constant, then $_ENV, then default.
	 *
	 * @param string $key     The environment variable name.
	 * @param mixed  $default Default value if not found.
	 *
	 * @return mixed
	 */
	public static function env( string $key, $default = null ) {
		if ( defined( $key ) ) {
			return constant( $key );
		}

		if ( isset( $_ENV[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( $_ENV[ $key ] ) );
		}

		return $default;
	}
}
