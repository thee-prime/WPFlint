<?php
/**
 * Nonce verification middleware.
 *
 * @package WPFlint\Http\Middleware
 */

declare(strict_types=1);

namespace WPFlint\Http\Middleware;

use Closure;
use WPFlint\Http\Request;
use WPFlint\Http\Response;

/**
 * Verifies WordPress nonce for AJAX requests.
 *
 * Usage: 'nonce:my_action'
 */
class VerifyNonce implements MiddlewareInterface {

	/**
	 * The nonce action to verify.
	 *
	 * @var string
	 */
	protected string $action;

	/**
	 * Constructor.
	 *
	 * @param string $action Nonce action name.
	 */
	public function __construct( string $action ) {
		$this->action = $action;
	}

	/**
	 * Handle the request.
	 *
	 * @param Request $request The incoming request.
	 * @param Closure $next    The next middleware.
	 * @return mixed
	 */
	public function handle( Request $request, Closure $next ) {
		if ( ! check_ajax_referer( $this->action, '_wpnonce', false ) ) {
			return Response::error(
				__( 'Invalid or expired nonce.', 'wpflint' ),
				403
			);
		}

		return $next( $request );
	}
}
