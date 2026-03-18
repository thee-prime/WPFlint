<?php
/**
 * Capability check middleware.
 *
 * @package WPFlint\Http\Middleware
 */

declare(strict_types=1);

namespace WPFlint\Http\Middleware;

use Closure;
use WPFlint\Http\Request;
use WPFlint\Http\Response;

/**
 * Checks that the current user has the required capability.
 *
 * Usage: 'can:edit_posts'
 */
class CheckCapability implements MiddlewareInterface {

	/**
	 * The required capability.
	 *
	 * @var string
	 */
	protected string $capability;

	/**
	 * Constructor.
	 *
	 * @param string $capability WordPress capability string.
	 */
	public function __construct( string $capability ) {
		$this->capability = $capability;
	}

	/**
	 * Handle the request.
	 *
	 * @param Request $request The incoming request.
	 * @param Closure $next    The next middleware.
	 * @return mixed
	 */
	public function handle( Request $request, Closure $next ) {
		if ( ! current_user_can( $this->capability ) ) {
			return Response::error(
				__( 'You do not have permission to perform this action.', 'wpflint' ),
				403
			);
		}

		return $next( $request );
	}
}
