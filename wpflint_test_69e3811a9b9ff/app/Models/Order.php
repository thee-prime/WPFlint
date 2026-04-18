<?php

declare(strict_types=1);

use WPFlint\Database\ORM\Model;

class Order extends Model {

	protected static string $table = 'orders';

	protected array $fillable = array();

	protected array $casts = array();
}
