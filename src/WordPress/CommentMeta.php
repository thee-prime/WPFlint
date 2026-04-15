<?php
/**
 * WordPress CommentMeta model — maps to the {prefix}commentmeta table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;

/**
 * Active-Record model for WordPress comment meta.
 */
class CommentMeta extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'commentmeta';

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
		'comment_id',
		'meta_key',
		'meta_value',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'meta_id'    => 'integer',
		'comment_id' => 'integer',
	);

	// ---------------------------------------------------------------
	// Relationships
	// ---------------------------------------------------------------

	/**
	 * The comment this meta belongs to.
	 *
	 * @return \WPFlint\Database\ORM\BelongsTo
	 */
	public function comment() {
		return $this->belongs_to( Comment::class, 'comment_id', 'comment_ID' );
	}
}
