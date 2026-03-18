<?php
/**
 * Middleware interface.
 *
 * @package WPFlint\Http\Middleware
 */

declare(strict_types=1);

namespace WPFlint\Http\Middleware;

use Closure;
use WPFlint\Http\Request;

/**
 * Contract for HTTP middleware.
 */
interface MiddlewareInterface {

	/**
	 * Handle the request.
	 *
	 * @param Request $request The incoming request.
	 * @param Closure $next    The next middleware in the pipeline.
	 * @return mixed
	 */
	public function handle( Request $request, Closure $next );
}
