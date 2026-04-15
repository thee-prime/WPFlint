<?php
/**
 * WordPress Term model — maps to the {prefix}terms table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;

/**
 * Active-Record model for WordPress terms.
 *
 * WordPress terms are the base vocabulary unit. A term's taxonomy context
 * lives in the related TermTaxonomy record.
 *
 * Usage:
 *
 *     $term = Term::find( $term_id );
 *     $taxonomy = $term->taxonomy()->first_model();
 */
class Term extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'terms';

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected static string $primary_key = 'term_id';

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
		'name',
		'slug',
		'term_group',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'term_id'    => 'integer',
		'term_group' => 'integer',
	);

	// ---------------------------------------------------------------
	// Relationships
	// ---------------------------------------------------------------

	/**
	 * The taxonomy record(s) for this term.
	 *
	 * @return \WPFlint\Database\ORM\HasMany
	 */
	public function taxonomies() {
		return $this->has_many( TermTaxonomy::class, 'term_id', 'term_id' );
	}

	/**
	 * The primary taxonomy record for this term.
	 *
	 * @return \WPFlint\Database\ORM\HasOne
	 */
	public function taxonomy() {
		return $this->has_one( TermTaxonomy::class, 'term_id', 'term_id' );
	}
}
