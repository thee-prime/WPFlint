<?php
/**
 * Route definition.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

/**
 * Represents a single registered route.
 */
class Route {

	/**
	 * AJAX action name.
	 *
	 * @var string
	 */
	protected string $action;

	/**
	 * Controller and method pair.
	 *
	 * @var array{0: string, 1: string}
	 */
	protected array $handler;

	/**
	 * Middleware stack (string aliases).
	 *
	 * @var string[]
	 */
	protected array $middleware = array();

	/**
	 * Whether non-logged-in users can access.
	 *
	 * @var bool
	 */
	protected bool $is_nopriv = false;

	/**
	 * Constructor.
	 *
	 * @param string $action  AJAX action name.
	 * @param array  $handler Controller and method pair.
	 */
	public function __construct( string $action, array $handler ) {
		$this->action  = $action;
		$this->handler = $handler;
	}

	/**
	 * Add middleware to this route.
	 *
	 * @param array<string> $middleware Middleware aliases.
	 * @return static
	 */
	public function middleware( array $middleware ): self {
		$this->middleware = array_merge( $this->middleware, $middleware );
		return $this;
	}

	/**
	 * Allow non-logged-in access.
	 *
	 * @return static
	 */
	public function nopriv(): self {
		$this->is_nopriv = true;
		return $this;
	}

	/**
	 * Get the action name.
	 *
	 * @return string
	 */
	public function get_action(): string {
		return $this->action;
	}

	/**
	 * Get the handler.
	 *
	 * @return array{0: string, 1: string}
	 */
	public function get_handler(): array {
		return $this->handler;
	}

	/**
	 * Get middleware aliases.
	 *
	 * @return string[]
	 */
	public function get_middleware(): array {
		return $this->middleware;
	}

	/**
	 * Check if route allows nopriv access.
	 *
	 * @return bool
	 */
	public function is_nopriv(): bool {
		return $this->is_nopriv;
	}
}
