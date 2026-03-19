<?php
/**
 * Event facade — static proxy for the Dispatcher.
 *
 * @package WPFlint\Facades
 */

declare(strict_types=1);

namespace WPFlint\Facades;

/**
 * Static proxy for the Event Dispatcher.
 *
 * @method static void listen(string $event, callable|string $listener)
 * @method static \WPFlint\Events\Event fire(\WPFlint\Events\Event $event)
 * @method static void forget(string $event)
 * @method static bool has_listeners(string $event)
 * @method static array get_listeners(string $event)
 * @method static void listen_wp(string $hook, string $event_class, int $priority = 10, int $args = 1)
 *
 * @see \WPFlint\Events\Dispatcher
 */
class Event extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function get_facade_accessor(): string {
		return 'events';
	}
}
