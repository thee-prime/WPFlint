<?php
/**
 * Cache manager — resolves drivers, provides tags() and fresh() API.
 *
 * @package WPFlint\Cache
 */

declare(strict_types=1);

namespace WPFlint\Cache;

use WPFlint\Cache\Drivers\ArrayDriver;
use WPFlint\Cache\Drivers\ObjectCacheDriver;
use WPFlint\Cache\Drivers\TransientDriver;

/**
 * Central cache manager.
 *
 * Resolves the configured driver and provides tags() / fresh() entry points.
 */
class CacheManager {

	/**
	 * The resolved cache driver.
	 *
	 * @var CacheDriverInterface
	 */
	protected CacheDriverInterface $driver;

	/**
	 * Whether to bypass cache reads (fresh mode).
	 *
	 * @var bool
	 */
	protected bool $bypass = false;

	/**
	 * Option prefix for tag tracking.
	 *
	 * @var string
	 */
	protected string $prefix;

	/**
	 * Constructor.
	 *
	 * @param string $driver_name Driver name: 'transient', 'object', or 'array'.
	 * @param string $prefix      Prefix for tag option keys.
	 */
	public function __construct( string $driver_name = 'transient', string $prefix = 'wpflint' ) {
		$this->driver = $this->resolve_driver( $driver_name );
		$this->prefix = $prefix;
	}

	/**
	 * Resolve a driver by name.
	 *
	 * @param string $name Driver name.
	 * @return CacheDriverInterface
	 */
	protected function resolve_driver( string $name ): CacheDriverInterface {
		switch ( $name ) {
			case 'object':
				return new ObjectCacheDriver();

			case 'array':
				return new ArrayDriver();

			case 'transient':
			default:
				return new TransientDriver();
		}
	}

	/**
	 * Get a tagged cache instance.
	 *
	 * @param string|string[] $tags One or more tags.
	 * @return TaggedCache
	 */
	public function tags( $tags ): TaggedCache {
		if ( is_string( $tags ) ) {
			$tags = array( $tags );
		}

		return new TaggedCache( $this->driver, $tags, $this->prefix );
	}

	/**
	 * Enable fresh mode — bypasses cache reads for the next operation.
	 *
	 * @return static
	 */
	public function fresh(): self {
		$this->bypass = true;
		return $this;
	}

	/**
	 * Get a cached value.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		if ( $this->bypass ) {
			$this->bypass = false;
			return $default;
		}

		return $this->driver->get( $key, $default );
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
		return $this->driver->put( $key, $value, $ttl );
	}

	/**
	 * Delete a cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function forget( string $key ): bool {
		return $this->driver->forget( $key );
	}

	/**
	 * Check if a key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		if ( $this->bypass ) {
			$this->bypass = false;
			return false;
		}

		return $this->driver->has( $key );
	}

	/**
	 * Clear all cached values.
	 *
	 * @return bool
	 */
	public function flush(): bool {
		return $this->driver->flush();
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
		if ( $this->bypass ) {
			$this->bypass = false;
			$value        = $callback();
			$this->driver->put( $key, $value, $ttl );
			return $value;
		}

		return $this->driver->remember( $key, $ttl, $callback );
	}

	/**
	 * Get the underlying driver.
	 *
	 * @return CacheDriverInterface
	 */
	public function get_driver(): CacheDriverInterface {
		return $this->driver;
	}
}
