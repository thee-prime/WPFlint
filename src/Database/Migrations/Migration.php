<?php
/**
 * Abstract migration base class.
 *
 * @package WPFlint\Database\Migrations
 */

declare(strict_types=1);

namespace WPFlint\Database\Migrations;

use WPFlint\Database\Schema\Schema;

/**
 * Base class for all database migrations.
 *
 * Subclasses implement up() and down() to define schema changes.
 * The schema() helper provides access to the Schema builder.
 */
abstract class Migration {

	/**
	 * Run the migration (create tables, add columns, etc.).
	 *
	 * @return void
	 */
	abstract public function up(): void;

	/**
	 * Reverse the migration.
	 *
	 * @return void
	 */
	abstract public function down(): void;

	/**
	 * Get a Schema instance for building tables.
	 *
	 * @return Schema
	 */
	public function schema(): Schema {
		return new Schema();
	}
}
