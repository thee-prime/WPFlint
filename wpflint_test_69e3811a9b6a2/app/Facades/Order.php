<?php

declare(strict_types=1);

use WPFlint\Facades\Facade;

class Order extends Facade {

	protected static function get_facade_accessor(): string {
		return '';
	}
}
