<?php

declare(strict_types=1);

use WPFlint\Database\Migrations\Migration;
use WPFlint\Database\Schema\Blueprint;

class CreateProductsTable extends Migration {

	public function up(): void {
		$this->schema()->create( 'products', function ( Blueprint $table ) {
			$table->big_increments( 'id' );
			$table->timestamps();
		} );
	}

	public function down(): void {
		$this->schema()->drop( 'products' );
	}
}
