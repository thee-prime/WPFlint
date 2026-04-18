<?php

declare(strict_types=1);

use WPFlint\Http\RestController;

class OrderController extends RestController {

	protected string $namespace = 'my-plugin/v1';

	protected string $rest_base = 'order';

	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->respond( array() );
	}

	public function store( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->respond( array(), 201 );
	}
}
