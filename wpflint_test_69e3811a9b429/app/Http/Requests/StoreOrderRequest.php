<?php

declare(strict_types=1);

use WPFlint\Http\Request;

class StoreOrderRequest extends Request {

	public function authorize(): bool {
		return false;
	}

	public function rules(): array {
		return array();
	}

	public function sanitize(): array {
		return array();
	}
}
