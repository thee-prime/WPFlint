<?php

declare(strict_types=1);

use Closure;
use WPFlint\Http\Request;
use WPFlint\Http\Middleware\MiddlewareInterface;

class EnsureStoreIsOpen implements MiddlewareInterface {

	public function handle( Request $request, Closure $next ) {
		return $next( $request );
	}
}
