<?php
/**
 * Cache driver interface.
 *
 * @package WPFlint\Cache
 */

declare(strict_types=1);

namespace WPFlint\Cache;

/**
 * Contract for all cache drivers.
 */
interface CacheDriverInterface {

	/**
	 * Get a value from cache.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, $default = null );

	/**
	 * Store a value in cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time to live in seconds (0 = no expiration).
	 * @return bool
	 */
	public function put( string $key, $value, int $ttl = 0 ): bool;

	/**
	 * Delete a value from cache.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function forget( string $key ): bool;

	/**
	 * Check if a key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function has( string $key ): bool;

	/**
	 * Clear all cached values.
	 *
	 * @return bool
	 */
	public function flush(): bool;

	/**
	 * Get or set a cached value.
	 *
	 * @param string   $key      Cache key.
	 * @param int      $ttl      Time to live in seconds.
	 * @param callable $callback Callback to generate the value.
	 * @return mixed
	 */
	public function remember( string $key, int $ttl, callable $callback );
}
