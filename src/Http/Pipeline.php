<?php
/**
 * Middleware pipeline.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

use Closure;
use WPFlint\Http\Middleware\MiddlewareInterface;

/**
 * Sends a request through a stack of middleware.
 */
class Pipeline {

	/**
	 * The request being sent through the pipeline.
	 *
	 * @var Request
	 */
	protected Request $request;

	/**
	 * The middleware stack.
	 *
	 * @var array<MiddlewareInterface>
	 */
	protected array $pipes = array();

	/**
	 * Set the request to send through the pipeline.
	 *
	 * @param Request $request The request.
	 * @return static
	 */
	public function send( Request $request ): self {
		$this->request = $request;
		return $this;
	}

	/**
	 * Set the middleware stack.
	 *
	 * @param array<MiddlewareInterface> $pipes Middleware instances.
	 * @return static
	 */
	public function through( array $pipes ): self {
		$this->pipes = $pipes;
		return $this;
	}

	/**
	 * Run the pipeline with a final destination callback.
	 *
	 * @param Closure $destination The final handler.
	 * @return mixed
	 */
	public function then( Closure $destination ) {
		$pipeline = array_reduce(
			array_reverse( $this->pipes ),
			function ( Closure $next, MiddlewareInterface $pipe ) {
				return function ( Request $request ) use ( $next, $pipe ) {
					return $pipe->handle( $request, $next );
				};
			},
			$destination
		);

		return $pipeline( $this->request );
	}
}
