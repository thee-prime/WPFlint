<?php
/**
 * REST API sub-router for group registration.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

/**
 * Collects REST route definitions within a namespace group.
 */
class RestRouter {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected string $namespace;

	/**
	 * Collected route definitions.
	 *
	 * @var array<array{route: string, method: string, callback: callable, permission_callback: callable|string|null}>
	 */
	protected array $routes = array();

	/**
	 * Constructor.
	 *
	 * @param string $namespace REST namespace.
	 */
	public function __construct( string $namespace ) {
		$this->namespace = $namespace;
	}

	/**
	 * Register a GET route.
	 *
	 * @param string   $route    Route pattern.
	 * @param callable $callback Handler.
	 * @param callable $permission_callback Permission callback.
	 * @return static
	 */
	public function get( string $route, $callback, $permission_callback = null ): self {
		return $this->add_route( 'GET', $route, $callback, $permission_callback );
	}

	/**
	 * Register a POST route.
	 *
	 * @param string   $route    Route pattern.
	 * @param callable $callback Handler.
	 * @param callable $permission_callback Permission callback.
	 * @return static
	 */
	public function post( string $route, $callback, $permission_callback = null ): self {
		return $this->add_route( 'POST', $route, $callback, $permission_callback );
	}

	/**
	 * Register a PUT route.
	 *
	 * @param string   $route    Route pattern.
	 * @param callable $callback Handler.
	 * @param callable $permission_callback Permission callback.
	 * @return static
	 */
	public function put( string $route, $callback, $permission_callback = null ): self {
		return $this->add_route( 'PUT', $route, $callback, $permission_callback );
	}

	/**
	 * Register a DELETE route.
	 *
	 * @param string   $route    Route pattern.
	 * @param callable $callback Handler.
	 * @param callable $permission_callback Permission callback.
	 * @return static
	 */
	public function delete( string $route, $callback, $permission_callback = null ): self {
		return $this->add_route( 'DELETE', $route, $callback, $permission_callback );
	}

	/**
	 * Register a PATCH route.
	 *
	 * @param string   $route    Route pattern.
	 * @param callable $callback Handler.
	 * @param callable $permission_callback Permission callback.
	 * @return static
	 */
	public function patch( string $route, $callback, $permission_callback = null ): self {
		return $this->add_route( 'PATCH', $route, $callback, $permission_callback );
	}

	/**
	 * Add a route definition.
	 *
	 * @param string        $method              HTTP method.
	 * @param string        $route               Route pattern.
	 * @param callable      $callback            Handler.
	 * @param callable|null $permission_callback Permission callback.
	 * @return static
	 */
	protected function add_route( string $method, string $route, $callback, $permission_callback = null ): self {
		$this->routes[] = array(
			'route'               => $route,
			'method'              => $method,
			'callback'            => $callback,
			'permission_callback' => $permission_callback,
		);
		return $this;
	}

	/**
	 * Get all collected routes.
	 *
	 * @return array
	 */
	public function get_routes(): array {
		return $this->routes;
	}

	/**
	 * Get the namespace.
	 *
	 * @return string
	 */
	public function get_namespace(): string {
		return $this->namespace;
	}
}
