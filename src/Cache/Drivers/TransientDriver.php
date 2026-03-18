<?php
/**
 * WordPress transients cache driver.
 *
 * @package WPFlint\Cache\Drivers
 */

declare(strict_types=1);

namespace WPFlint\Cache\Drivers;

use WPFlint\Cache\CacheDriverInterface;

/**
 * Cache driver backed by WordPress transients.
 */
class TransientDriver implements CacheDriverInterface {

	/**
	 * Key prefix for namespacing.
	 *
	 * @var string
	 */
	protected string $prefix;

	/**
	 * Constructor.
	 *
	 * @param string $prefix Key prefix.
	 */
	public function __construct( string $prefix = 'wpflint_' ) {
		$this->prefix = $prefix;
	}

	/**
	 * Get a value from cache.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$value = get_transient( $this->prefix . $key );

		if ( false === $value ) {
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
		return set_transient( $this->prefix . $key, $value, $ttl );
	}

	/**
	 * Delete a value from cache.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function forget( string $key ): bool {
		return delete_transient( $this->prefix . $key );
	}

	/**
	 * Check if a key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return false !== get_transient( $this->prefix . $key );
	}

	/**
	 * Clear all cached values.
	 *
	 * @return bool
	 */
	public function flush(): bool {
		// Transients have no bulk-delete. Return true as a no-op.
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
		$value = $this->get( $key );

		if ( null !== $value ) {
			return $value;
		}

		$value = $callback();
		$this->put( $key, $value, $ttl );

		return $value;
	}
}
