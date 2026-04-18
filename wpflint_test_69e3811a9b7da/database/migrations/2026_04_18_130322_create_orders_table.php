<?php

declare(strict_types=1);

use WPFlint\Database\Migrations\Migration;
use WPFlint\Database\Schema\Blueprint;

class CreateOrdersTable extends Migration {

	public function up(): void {
		$this->schema()->create( 'orders', function ( Blueprint $table ) {
			$table->big_increments( 'id' );
			$table->timestamps();
		} );
	}

	public function down(): void {
		$this->schema()->drop( 'orders' );
	}
}
