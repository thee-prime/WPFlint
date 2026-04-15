<?php
/**
 * WordPress Option model — maps to the {prefix}options table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;

/**
 * Active-Record model for WordPress options.
 *
 * Prefer WordPress' native get_option() / update_option() for most use-cases.
 * Use this model when you need bulk queries, custom sorting, or ORM features.
 *
 * Usage:
 *
 *     $autoloaded = Option::autoloaded()->get_models();
 *     $option     = Option::where( 'option_name', 'siteurl' )->first_model();
 */
class Option extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'options';

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected static string $primary_key = 'option_id';

	/**
	 * Disable automatic timestamps.
	 *
	 * @var bool
	 */
	protected static bool $timestamps = false;

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array
	 */
	protected array $fillable = array(
		'option_name',
		'option_value',
		'autoload',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'option_id' => 'integer',
	);

	// ---------------------------------------------------------------
	// Scopes
	// ---------------------------------------------------------------

	/**
	 * Scope: autoloaded options (autoload = 'yes').
	 *
	 * @param ModelQueryBuilder $q Query builder.
	 * @return ModelQueryBuilder
	 */
	public function scope_autoloaded( ModelQueryBuilder $q ): ModelQueryBuilder {
		return $q->where( 'autoload', '=', 'yes' );
	}

	/**
	 * Scope: non-autoloaded options (autoload = 'no').
	 *
	 * @param ModelQueryBuilder $q Query builder.
	 * @return ModelQueryBuilder
	 */
	public function scope_not_autoloaded( ModelQueryBuilder $q ): ModelQueryBuilder {
		return $q->where( 'autoload', '=', 'no' );
	}
}
