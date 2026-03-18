<?php
/**
 * In-memory array cache driver (for testing).
 *
 * @package WPFlint\Cache\Drivers
 */

declare(strict_types=1);

namespace WPFlint\Cache\Drivers;

use WPFlint\Cache\CacheDriverInterface;

/**
 * Cache driver that stores values in a PHP array.
 *
 * Useful for unit tests. Values do not persist between requests.
 */
class ArrayDriver implements CacheDriverInterface {

	/**
	 * In-memory cache store.
	 *
	 * @var array<string, mixed>
	 */
	protected array $store = array();

	/**
	 * Get a value from cache.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		if ( ! array_key_exists( $key, $this->store ) ) {
			return $default;
		}

		return $this->store[ $key ];
	}

	/**
	 * Store a value in cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time to live in seconds.
	 * @return bool
	 */
	public function put( string $key, $value, int $ttl = 0 ): bool {
		$this->store[ $key ] = $value;
		return true;
	}

	/**
	 * Delete a value from cache.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function forget( string $key ): bool {
		unset( $this->store[ $key ] );
		return true;
	}

	/**
	 * Check if a key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->store );
	}

	/**
	 * Clear all cached values.
	 *
	 * @return bool
	 */
	public function flush(): bool {
		$this->store = array();
		return true;
	}

	/**
	 * Get or set a cached value.
	 *
	 * @param string   $key      Cache key.
	 * @param int      $ttl      Time to live in seconds.
	 * @param callable $callback Callback to generate the value.
	 * @return mixed
	 */
	public function remember( string $key, int $ttl, callable $callback ) {
		if ( $this->has( $key ) ) {
			return $this->get( $key );
		}

		$value = $callback();
		$this->put( $key, $value, $ttl );

		return $value;
	}

	/**
	 * Get the entire store (for testing inspection).
	 *
	 * @return array<string, mixed>
	 */
	public function get_store(): array {
		return $this->store;
	}
}
