<?php

declare(strict_types=1);

use WPFlint\Database\ORM\Model;

class Product extends Model {

	protected static string $table = 'products';

	protected array $fillable = array();

	protected array $casts = array();
}
