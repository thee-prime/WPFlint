<?php
/**
 * Event dispatcher — listen, fire, forget.
 *
 * @package WPFlint\Events
 */

declare(strict_types=1);

namespace WPFlint\Events;

use Closure;
use WPFlint\Container\Container;

/**
 * Central event dispatcher.
 *
 * Listeners can be: class name (resolved from container), Closure, or array callable.
 */
class Dispatcher {

	/**
	 * IoC container for resolving class-based listeners.
	 *
	 * @var Container|null
	 */
	protected ?Container $container;

	/**
	 * Registered listeners keyed by event class name.
	 *
	 * @var array<string, array<int, callable|string>>
	 */
	protected array $listeners = array();

	/**
	 * Constructor.
	 *
	 * @param Container|null $container IoC container.
	 */
	public function __construct( ?Container $container = null ) {
		$this->container = $container;
	}

	/**
	 * Register a listener for an event.
	 *
	 * @param string          $event    Event class name.
	 * @param callable|string $listener Closure, array callable, or class name.
	 * @return void
	 */
	public function listen( string $event, $listener ): void {
		$this->listeners[ $event ][] = $listener;
	}

	/**
	 * Fire an event and call all registered listeners.
	 *
	 * @param Event $event The event instance.
	 * @return Event The event (possibly modified by listeners).
	 */
	public function fire( Event $event ): Event {
		$event_class = get_class( $event );
		$listeners   = $this->get_listeners( $event_class );

		foreach ( $listeners as $listener ) {
			if ( $event->is_propagation_stopped() ) {
				break;
			}

			$this->call_listener( $listener, $event );
		}

		return $event;
	}

	/**
	 * Remove all listeners for an event.
	 *
	 * @param string $event Event class name.
	 * @return void
	 */
	public function forget( string $event ): void {
		unset( $this->listeners[ $event ] );
	}

	/**
	 * Check if an event has any listeners.
	 *
	 * @param string $event Event class name.
	 * @return bool
	 */
	public function has_listeners( string $event ): bool {
		return ! empty( $this->listeners[ $event ] );
	}

	/**
	 * Get all listeners for an event.
	 *
	 * @param string $event Event class name.
	 * @return array<int, callable|string>
	 */
	public function get_listeners( string $event ): array {
		return $this->listeners[ $event ] ?? array();
	}

	/**
	 * Bridge a WordPress hook to a typed event.
	 *
	 * When the WP hook fires, the dispatcher creates an instance of the event class
	 * and dispatches it through all registered listeners.
	 *
	 * @param string $hook        WordPress action/filter name.
	 * @param string $event_class Fully qualified event class name.
	 * @param int    $priority    Hook priority (default 10).
	 * @param int    $args        Number of arguments (default 1).
	 * @return void
	 */
	public function listen_wp( string $hook, string $event_class, int $priority = 10, int $args = 1 ): void {
		$dispatcher = $this;

		add_action(
			$hook,
			function () use ( $dispatcher, $event_class ) {
				$hook_args = func_get_args();
				$event     = new $event_class( ...$hook_args );
				$dispatcher->fire( $event );
			},
			$priority,
			$args
		);
	}

	/**
	 * Call a single listener with the event.
	 *
	 * @param callable|string $listener The listener.
	 * @param Event           $event    The event.
	 * @return void
	 */
	protected function call_listener( $listener, Event $event ): void {
		if ( $listener instanceof Closure ) {
			$listener( $event );
			return;
		}

		if ( is_array( $listener ) && is_callable( $listener ) ) {
			$listener( $event );
			return;
		}

		if ( is_string( $listener ) ) {
			$instance = $this->resolve_listener( $listener );
			$instance->handle( $event );
			return;
		}
	}

	/**
	 * Resolve a class-based listener.
	 *
	 * @param string $listener Listener class name.
	 * @return object
	 */
	protected function resolve_listener( string $listener ) {
		if ( null !== $this->container ) {
			return $this->container->make( $listener );
		}

		return new $listener();
	}
}
