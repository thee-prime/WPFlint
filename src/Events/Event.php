<?php
/**
 * Abstract base event class.
 *
 * @package WPFlint\Events
 */

declare(strict_types=1);

namespace WPFlint\Events;

/**
 * Base class for all application events.
 *
 * Subclass to define typed event payloads.
 */
abstract class Event {

	/**
	 * Whether propagation of this event has been stopped.
	 *
	 * @var bool
	 */
	protected bool $propagation_stopped = false;

	/**
	 * Stop further listeners from being called.
	 *
	 * @return void
	 */
	public function stop_propagation(): void {
		$this->propagation_stopped = true;
	}

	/**
	 * Check if propagation has been stopped.
	 *
	 * @return bool
	 */
	public function is_propagation_stopped(): bool {
		return $this->propagation_stopped;
	}
}
