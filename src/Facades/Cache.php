<?php
/**
 * Cache facade — static proxy for the CacheManager.
 *
 * @package WPFlint\Facades
 */

declare(strict_types=1);

namespace WPFlint\Facades;

/**
 * Static proxy for the CacheManager.
 *
 * @method static mixed get(string $key, $default = null)
 * @method static bool put(string $key, $value, int $ttl = 0)
 * @method static bool forget(string $key)
 * @method static bool has(string $key)
 * @method static bool flush()
 * @method static mixed remember(string $key, int $ttl, callable $callback)
 * @method static \WPFlint\Cache\TaggedCache tags(string|string[] $tags)
 * @method static \WPFlint\Cache\CacheManager fresh()
 *
 * @see \WPFlint\Cache\CacheManager
 */
class Cache extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function get_facade_accessor(): string {
		return 'cache';
	}
}
