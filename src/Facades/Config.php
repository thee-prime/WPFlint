<?php
/**
 * Config facade — static proxy for the Config Repository.
 *
 * @package WPFlint\Facades
 */

declare(strict_types=1);

namespace WPFlint\Facades;

/**
 * Static proxy for the Config Repository.
 *
 * @method static mixed get(string $key, $default = null)
 * @method static void set(string $key, $value)
 * @method static bool has(string $key)
 * @method static array all()
 * @method static void push(string $key, $value)
 *
 * @see \WPFlint\Config\Repository
 */
class Config extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function get_facade_accessor(): string {
		return 'config';
	}
}
