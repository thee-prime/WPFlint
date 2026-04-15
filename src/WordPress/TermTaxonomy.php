<?php
/**
 * WordPress TermTaxonomy model — maps to the {prefix}term_taxonomy table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;

/**
 * Active-Record model for WordPress term taxonomy records.
 *
 * Usage:
 *
 *     $categories = TermTaxonomy::in_taxonomy( 'category' )->get_models();
 */
class TermTaxonomy extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'term_taxonomy';

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected static string $primary_key = 'term_taxonomy_id';

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
		'term_id',
		'taxonomy',
		'description',
		'parent',
		'count',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'term_taxonomy_id' => 'integer',
		'term_id'          => 'integer',
		'parent'           => 'integer',
		'count'            => 'integer',
	);

	// ---------------------------------------------------------------
	// Scopes
	// ---------------------------------------------------------------

	/**
	 * Scope: term taxonomy records for a given taxonomy.
	 *
	 * @param ModelQueryBuilder $q        Query builder.
	 * @param string            $taxonomy Taxonomy slug (e.g. 'category', 'post_tag').
	 * @return ModelQueryBuilder
	 */
	public function scope_in_taxonomy( ModelQueryBuilder $q, string $taxonomy ): ModelQueryBuilder {
		return $q->where( 'taxonomy', '=', $taxonomy );
	}

	// ---------------------------------------------------------------
	// Relationships
	// ---------------------------------------------------------------

	/**
	 * The base term record.
	 *
	 * @return \WPFlint\Database\ORM\BelongsTo
	 */
	public function term() {
		return $this->belongs_to( Term::class, 'term_id', 'term_id' );
	}

	/**
	 * Term relationships (posts/objects attached to this term).
	 *
	 * @return \WPFlint\Database\ORM\HasMany
	 */
	public function relationships() {
		return $this->has_many( TermRelationship::class, 'term_taxonomy_id', 'term_taxonomy_id' );
	}
}
