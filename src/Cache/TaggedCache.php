<?php
/**
 * Tagged cache — groups cached keys by tags for bulk invalidation.
 *
 * @package WPFlint\Cache
 */

declare(strict_types=1);

namespace WPFlint\Cache;

/**
 * Wraps a cache driver with tag-based grouping.
 *
 * Tag storage: option {prefix}_cache_tag_{tag} = serialized array of keys.
 */
class TaggedCache {

	/**
	 * Underlying cache driver.
	 *
	 * @var CacheDriverInterface
	 */
	protected CacheDriverInterface $driver;

	/**
	 * Tags associated with this instance.
	 *
	 * @var string[]
	 */
	protected array $tags;

	/**
	 * Option prefix for tag key lists.
	 *
	 * @var string
	 */
	protected string $prefix;

	/**
	 * Constructor.
	 *
	 * @param CacheDriverInterface $driver Cache driver.
	 * @param string[]             $tags   Tags.
	 * @param string               $prefix Option prefix.
	 */
	public function __construct( CacheDriverInterface $driver, array $tags, string $prefix = 'wpflint' ) {
		$this->driver = $driver;
		$this->tags   = $tags;
		$this->prefix = $prefix;
	}

	/**
	 * Get or set a cached value, tracked by tags.
	 *
	 * @param string   $key      Cache key.
	 * @param int      $ttl      Time to live in seconds.
	 * @param callable $callback Callback to generate the value.
	 * @return mixed
	 */
	public function remember( string $key, int $ttl, callable $callback ) {
		$value = $this->driver->get( $key );

		if ( null !== $value ) {
			return $value;
		}

		$value = $callback();
		$this->driver->put( $key, $value, $ttl );

		foreach ( $this->tags as $tag ) {
			$this->track_key( $tag, $key );
		}

		return $value;
	}

	/**
	 * Get a cached value.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		return $this->driver->get( $key, $default );
	}

	/**
	 * Store a value, tracked by tags.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time to live in seconds.
	 * @return bool
	 */
	public function put( string $key, $value, int $ttl = 0 ): bool {
		$result = $this->driver->put( $key, $value, $ttl );

		foreach ( $this->tags as $tag ) {
			$this->track_key( $tag, $key );
		}

		return $result;
	}

	/**
	 * Delete a cached value and untrack it.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function forget( string $key ): bool {
		$result = $this->driver->forget( $key );

		foreach ( $this->tags as $tag ) {
			$this->untrack_key( $tag, $key );
		}

		return $result;
	}

	/**
	 * Flush all cached values associated with these tags.
	 *
	 * @return bool
	 */
	public function flush(): bool {
		foreach ( $this->tags as $tag ) {
			$option_key = $this->tag_option_key( $tag );
			$keys       = get_option( $option_key, array() );

			if ( is_array( $keys ) ) {
				foreach ( $keys as $key ) {
					$this->driver->forget( $key );
				}
			}

			delete_option( $option_key );
		}

		return true;
	}

	/**
	 * Track a cache key under a tag.
	 *
	 * @param string $tag Tag name.
	 * @param string $key Cache key.
	 */
	protected function track_key( string $tag, string $key ): void {
		$option_key = $this->tag_option_key( $tag );
		$keys       = get_option( $option_key, array() );

		if ( ! is_array( $keys ) ) {
			$keys = array();
		}

		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			update_option( $option_key, $keys, false );
		}
	}

	/**
	 * Untrack a cache key from a tag.
	 *
	 * @param string $tag Tag name.
	 * @param string $key Cache key.
	 */
	protected function untrack_key( string $tag, string $key ): void {
		$option_key = $this->tag_option_key( $tag );
		$keys       = get_option( $option_key, array() );

		if ( ! is_array( $keys ) ) {
			return;
		}

		$keys = array_values( array_diff( $keys, array( $key ) ) );
		update_option( $option_key, $keys, false );
	}

	/**
	 * Get the option key for storing a tag's tracked cache keys.
	 *
	 * @param string $tag Tag name.
	 * @return string
	 */
	protected function tag_option_key( string $tag ): string {
		return $this->prefix . '_cache_tag_' . $tag;
	}
}
