<?php
/**
 * AJAX and REST route registration.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

use Closure;
use WPFlint\Container\Container;
use WPFlint\Http\Middleware\CheckCapability;
use WPFlint\Http\Middleware\MiddlewareInterface;
use WPFlint\Http\Middleware\ThrottleRequests;
use WPFlint\Http\Middleware\VerifyNonce;

/**
 * Registers AJAX actions and REST API routes.
 */
class Router {

	/**
	 * IoC container.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Registered AJAX routes.
	 *
	 * @var Route[]
	 */
	protected array $routes = array();

	/**
	 * Named middleware aliases.
	 *
	 * @var array<string, string>
	 */
	protected array $middleware_aliases = array();

	/**
	 * REST route groups.
	 *
	 * @var array<array{namespace: string, callback: Closure}>
	 */
	protected array $rest_groups = array();

	/**
	 * Constructor.
	 *
	 * @param Container $container IoC container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register an AJAX route.
	 *
	 * @param string $action  AJAX action name.
	 * @param array  $handler Controller and method pair [Controller::class, 'method'].
	 * @return Route
	 */
	public function ajax( string $action, array $handler ): Route {
		$route                   = new Route( $action, $handler );
		$this->routes[ $action ] = $route;
		return $route;
	}

	/**
	 * Register a REST API route group.
	 *
	 * @param string  $namespace REST namespace (e.g. 'my-plugin/v1').
	 * @param Closure $callback  Receives a RestRouter instance.
	 */
	public function rest( string $namespace, Closure $callback ): void {
		$this->rest_groups[] = array(
			'namespace' => $namespace,
			'callback'  => $callback,
		);
	}

	/**
	 * Register a middleware alias.
	 *
	 * @param string $alias Alias name.
	 * @param string $class Middleware class name.
	 */
	public function alias_middleware( string $alias, string $class ): void {
		$this->middleware_aliases[ $alias ] = $class;
	}

	/**
	 * Boot all registered routes (hook into WordPress).
	 */
	public function boot(): void {
		foreach ( $this->routes as $route ) {
			$this->register_ajax_route( $route );
		}

		if ( ! empty( $this->rest_groups ) ) {
			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		}
	}

	/**
	 * Register a single AJAX route with WordPress hooks.
	 *
	 * @param Route $route The route to register.
	 */
	protected function register_ajax_route( Route $route ): void {
		$callback = $this->build_ajax_callback( $route );

		add_action( 'wp_ajax_' . $route->get_action(), $callback );

		if ( $route->is_nopriv() ) {
			add_action( 'wp_ajax_nopriv_' . $route->get_action(), $callback );
		}
	}

	/**
	 * Build the AJAX callback that runs middleware + controller.
	 *
	 * @param Route $route The route.
	 * @return Closure
	 */
	protected function build_ajax_callback( Route $route ): Closure {
		return function () use ( $route ) {
			$request    = Request::capture();
			$middleware = $this->resolve_middleware( $route->get_middleware() );
			$handler    = $route->get_handler();

			$pipeline = new Pipeline();
			$response = $pipeline
				->send( $request )
				->through( $middleware )
				->then(
					function ( Request $request ) use ( $handler ) {
						return $this->call_handler( $handler, $request );
					}
				);

			if ( $response instanceof Response ) {
				$response->send_ajax();
			}
		};
	}

	/**
	 * Register all REST route groups.
	 */
	public function register_rest_routes(): void {
		foreach ( $this->rest_groups as $group ) {
			$rest_router = new RestRouter( $group['namespace'] );
			$group['callback']( $rest_router );

			foreach ( $rest_router->get_routes() as $rest_route ) {
				register_rest_route(
					$group['namespace'],
					$rest_route['route'],
					array(
						'methods'             => $rest_route['method'],
						'callback'            => $rest_route['callback'],
						'permission_callback' => $rest_route['permission_callback'] ?? '__return_true',
					)
				);
			}
		}
	}

	/**
	 * Resolve middleware aliases into instances.
	 *
	 * @param string[] $aliases Middleware alias strings (e.g. 'nonce:save_order').
	 * @return MiddlewareInterface[]
	 */
	protected function resolve_middleware( array $aliases ): array {
		$instances = array();

		foreach ( $aliases as $alias ) {
			$parts = explode( ':', $alias, 2 );
			$name  = $parts[0];
			$param = $parts[1] ?? null;

			$instance = $this->resolve_middleware_instance( $name, $param );
			if ( null !== $instance ) {
				$instances[] = $instance;
			}
		}

		return $instances;
	}

	/**
	 * Resolve a single middleware by name.
	 *
	 * @param string      $name  Middleware name/alias.
	 * @param string|null $param Optional parameter.
	 * @return MiddlewareInterface|null
	 */
	protected function resolve_middleware_instance( string $name, ?string $param ): ?MiddlewareInterface {
		switch ( $name ) {
			case 'nonce':
				return new VerifyNonce( $param ?? '' );

			case 'can':
				return new CheckCapability( $param ?? '' );

			case 'throttle':
				$throttle_parts = explode( ',', $param ?? '60,1' );
				return new ThrottleRequests(
					(int) ( $throttle_parts[0] ?? 60 ),
					(int) ( $throttle_parts[1] ?? 1 )
				);

			default:
				if ( isset( $this->middleware_aliases[ $name ] ) ) {
					return $this->container->make( $this->middleware_aliases[ $name ] );
				}
				return null;
		}
	}

	/**
	 * Call a controller handler.
	 *
	 * @param array   $handler Controller and method pair.
	 * @param Request $request The request.
	 * @return mixed
	 */
	protected function call_handler( array $handler, Request $request ) {
		$controller = $this->container->make( $handler[0] );
		$method     = $handler[1];

		return $this->resolve_and_call( $controller, $method, $request );
	}

	/**
	 * Resolve method parameters and call the controller method.
	 *
	 * If the method type-hints a Request subclass, it is resolved, validated,
	 * and authorized before the method is called.
	 *
	 * @param object  $controller Controller instance.
	 * @param string  $method     Method name.
	 * @param Request $request    The base request.
	 * @return mixed
	 */
	protected function resolve_and_call( $controller, string $method, Request $request ) {
		$reflection = new \ReflectionMethod( $controller, $method );
		$parameters = $reflection->getParameters();
		$args       = array();

		foreach ( $parameters as $param ) {
			$type = $param->getType();

			if ( $type instanceof \ReflectionNamedType && ! $type->isBuiltin() ) {
				$class_name = $type->getName();

				if ( is_subclass_of( $class_name, Request::class ) ) {
					$form_request = new $class_name( $request->all(), array() );

					if ( ! $form_request->authorize() ) {
						return Response::error(
							__( 'Unauthorized.', 'wpflint' ),
							403
						);
					}

					if ( ! $form_request->validate() ) {
						return Response::error(
							wp_json_encode( $form_request->errors() ),
							422
						);
					}

					$args[] = $form_request;
					continue;
				}

				if ( Request::class === $class_name ) {
					$args[] = $request;
					continue;
				}

				$args[] = $this->container->make( $class_name );
				continue;
			}

			if ( $param->isDefaultValueAvailable() ) {
				$args[] = $param->getDefaultValue();
			} else {
				$args[] = null;
			}
		}

		return $reflection->invokeArgs( $controller, $args );
	}

	/**
	 * Get all registered AJAX routes.
	 *
	 * @return Route[]
	 */
	public function get_routes(): array {
		return $this->routes;
	}

	/**
	 * Get all registered REST groups.
	 *
	 * @return array
	 */
	public function get_rest_groups(): array {
		return $this->rest_groups;
	}
}
