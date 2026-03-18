<?php
/**
 * Rate-limiting middleware using WordPress transients.
 *
 * @package WPFlint\Http\Middleware
 */

declare(strict_types=1);

namespace WPFlint\Http\Middleware;

use Closure;
use WPFlint\Http\Request;
use WPFlint\Http\Response;

/**
 * Throttles requests per user using transients.
 *
 * Usage: 'throttle:60,1' (60 requests per 1 minute).
 */
class ThrottleRequests implements MiddlewareInterface {

	/**
	 * Maximum number of requests.
	 *
	 * @var int
	 */
	protected int $max_attempts;

	/**
	 * Time window in minutes.
	 *
	 * @var int
	 */
	protected int $decay_minutes;

	/**
	 * Constructor.
	 *
	 * @param int $max_attempts  Maximum requests allowed.
	 * @param int $decay_minutes Time window in minutes.
	 */
	public function __construct( int $max_attempts = 60, int $decay_minutes = 1 ) {
		$this->max_attempts  = $max_attempts;
		$this->decay_minutes = $decay_minutes;
	}

	/**
	 * Handle the request.
	 *
	 * @param Request $request The incoming request.
	 * @param Closure $next    The next middleware.
	 * @return mixed
	 */
	public function handle( Request $request, Closure $next ) {
		$key      = $this->resolve_key();
		$attempts = (int) get_transient( $key );

		if ( $attempts >= $this->max_attempts ) {
			return Response::error(
				__( 'Too many requests. Please try again later.', 'wpflint' ),
				429
			);
		}

		set_transient( $key, $attempts + 1, $this->decay_minutes * MINUTE_IN_SECONDS );

		return $next( $request );
	}

	/**
	 * Build a unique throttle key for the current user/IP.
	 *
	 * @return string
	 */
	protected function resolve_key(): string {
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			return 'wpflint_throttle_' . $user_id;
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'wpflint_throttle_' . md5( $ip );
	}
}
