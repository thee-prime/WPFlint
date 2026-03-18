<?php
/**
 * WordPress object cache driver.
 *
 * @package WPFlint\Cache\Drivers
 */

declare(strict_types=1);

namespace WPFlint\Cache\Drivers;

use WPFlint\Cache\CacheDriverInterface;

/**
 * Cache driver backed by wp_cache_* functions.
 *
 * Falls back to transients if no persistent object cache is available.
 */
class ObjectCacheDriver implements CacheDriverInterface {

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	protected string $group;

	/**
	 * Fallback transient driver.
	 *
	 * @var TransientDriver|null
	 */
	protected ?TransientDriver $fallback = null;

	/**
	 * Constructor.
	 *
	 * @param string $group Cache group.
	 */
	public function __construct( string $group = 'wpflint' ) {
		$this->group = $group;

		if ( ! wp_using_ext_object_cache() ) {
			$this->fallback = new TransientDriver();
		}
	}

	/**
	 * Get a value from cache.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		if ( null !== $this->fallback ) {
			return $this->fallback->get( $key, $default );
		}

		$found = false;
		$value = wp_cache_get( $key, $this->group, false, $found );

		if ( ! $found ) {
			return $default;
		}

		return $value;
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
		if ( null !== $this->fallback ) {
			return $this->fallback->put( $key, $value, $ttl );
		}

		return wp_cache_set( $key, $value, $this->group, $ttl );
	}

	/**
	 * Delete a value from cache.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function forget( string $key ): bool {
		if ( null !== $this->fallback ) {
			return $this->fallback->forget( $key );
		}

		return wp_cache_delete( $key, $this->group );
	}

	/**
	 * Check if a key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		if ( null !== $this->fallback ) {
			return $this->fallback->has( $key );
		}

		$found = false;
		wp_cache_get( $key, $this->group, false, $found );

		return $found;
	}

	/**
	 * Clear all cached values.
	 *
	 * @return bool
	 */
	public function flush(): bool {
		if ( null !== $this->fallback ) {
			return $this->fallback->flush();
		}

		return wp_cache_flush();
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
		$value = $this->get( $key );

		if ( null !== $value ) {
			return $value;
		}

		$value = $callback();
		$this->put( $key, $value, $ttl );

		return $value;
	}
}
