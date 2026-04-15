<?php
/**
 * WordPress Link model — maps to the {prefix}links table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;

/**
 * Active-Record model for WordPress blogroll links.
 *
 * The links table is created when the Links Manager is enabled.
 *
 * Usage:
 *
 *     $links = Link::visible()->get_models();
 */
class Link extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'links';

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected static string $primary_key = 'link_id';

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
		'link_url',
		'link_name',
		'link_image',
		'link_target',
		'link_description',
		'link_visible',
		'link_owner',
		'link_rating',
		'link_rel',
		'link_notes',
		'link_rss',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'link_id'     => 'integer',
		'link_owner'  => 'integer',
		'link_rating' => 'integer',
	);

	// ---------------------------------------------------------------
	// Scopes
	// ---------------------------------------------------------------

	/**
	 * Scope: visible links (link_visible = 'Y').
	 *
	 * @param ModelQueryBuilder $q Query builder.
	 * @return ModelQueryBuilder
	 */
	public function scope_visible( ModelQueryBuilder $q ): ModelQueryBuilder {
		return $q->where( 'link_visible', '=', 'Y' );
	}
}
