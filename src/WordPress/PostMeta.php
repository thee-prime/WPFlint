<?php
/**
 * WordPress PostMeta model — maps to the {prefix}postmeta table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;

/**
 * Active-Record model for WordPress post meta.
 *
 * Usage:
 *
 *     $meta = PostMeta::where( 'post_id', $post_id )
 *                     ->where( 'meta_key', '_price' )
 *                     ->first_model();
 */
class PostMeta extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'postmeta';

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected static string $primary_key = 'meta_id';

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
		'post_id',
		'meta_key',
		'meta_value',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'meta_id' => 'integer',
		'post_id' => 'integer',
	);

	// ---------------------------------------------------------------
	// Relationships
	// ---------------------------------------------------------------

	/**
	 * The post this meta belongs to.
	 *
	 * @return \WPFlint\Database\ORM\BelongsTo
	 */
	public function post() {
		return $this->belongs_to( Post::class, 'post_id', 'ID' );
	}
}
