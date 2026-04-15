<?php
/**
 * WordPress TermRelationship model — maps to the {prefix}term_relationships table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;

/**
 * Active-Record model for WordPress term relationships.
 *
 * This table uses a composite primary key (object_id + term_taxonomy_id).
 * The model treats object_id as the primary key for query purposes.
 * Use explicit where() clauses when you need to address a specific pivot row.
 *
 * Usage:
 *
 *     $relations = TermRelationship::where( 'object_id', $post_id )->get_models();
 */
class TermRelationship extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'term_relationships';

	/**
	 * Object_id is used as the primary key column for query building.
	 * Note: the actual DB primary key is composite (object_id, term_taxonomy_id).
	 *
	 * @var string
	 */
	protected static string $primary_key = 'object_id';

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
		'object_id',
		'term_taxonomy_id',
		'term_order',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'object_id'        => 'integer',
		'term_taxonomy_id' => 'integer',
		'term_order'       => 'integer',
	);

	// ---------------------------------------------------------------
	// Relationships
	// ---------------------------------------------------------------

	/**
	 * The term taxonomy record for this relationship.
	 *
	 * @return \WPFlint\Database\ORM\BelongsTo
	 */
	public function term_taxonomy() {
		return $this->belongs_to( TermTaxonomy::class, 'term_taxonomy_id', 'term_taxonomy_id' );
	}
}
