<?php
/**
 * WordPress Post model — maps to the {prefix}posts table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;

/**
 * Active-Record model for WordPress posts.
 *
 * Extend this class to add custom behaviour for a post type:
 *
 *     class Product extends Post {
 *         public function scope_active( ModelQueryBuilder $q ): ModelQueryBuilder {
 *             return $q->where( 'post_status', 'publish' )
 *                      ->where( 'post_type', 'product' );
 *         }
 *     }
 */
class Post extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'posts';

	/**
	 * WordPress uses 'ID' (uppercase) as the primary key.
	 *
	 * @var string
	 */
	protected static string $primary_key = 'ID';

	/**
	 * WordPress manages its own date columns; disable automatic timestamps.
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
		'post_author',
		'post_date',
		'post_date_gmt',
		'post_content',
		'post_title',
		'post_excerpt',
		'post_status',
		'comment_status',
		'ping_status',
		'post_password',
		'post_name',
		'post_parent',
		'menu_order',
		'post_type',
		'post_mime_type',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'ID'            => 'integer',
		'post_author'   => 'integer',
		'post_parent'   => 'integer',
		'menu_order'    => 'integer',
		'comment_count' => 'integer',
	);

	// ---------------------------------------------------------------
	// Scopes
	// ---------------------------------------------------------------

	/**
	 * Scope: published posts (post_status = 'publish').
	 *
	 * @param ModelQueryBuilder $q Query builder.
	 * @return ModelQueryBuilder
	 */
	public function scope_published( ModelQueryBuilder $q ): ModelQueryBuilder {
		return $q->where( 'post_status', '=', 'publish' );
	}

	/**
	 * Scope: draft posts (post_status = 'draft').
	 *
	 * @param ModelQueryBuilder $q Query builder.
	 * @return ModelQueryBuilder
	 */
	public function scope_draft( ModelQueryBuilder $q ): ModelQueryBuilder {
		return $q->where( 'post_status', '=', 'draft' );
	}

	/**
	 * Scope: posts of a given post_type.
	 *
	 * @param ModelQueryBuilder $q    Query builder.
	 * @param string            $type Post type slug.
	 * @return ModelQueryBuilder
	 */
	public function scope_type( ModelQueryBuilder $q, string $type ): ModelQueryBuilder {
		return $q->where( 'post_type', '=', $type );
	}

	/**
	 * Scope: posts with a given post_status.
	 *
	 * @param ModelQueryBuilder $q      Query builder.
	 * @param string            $status Post status.
	 * @return ModelQueryBuilder
	 */
	public function scope_status( ModelQueryBuilder $q, string $status ): ModelQueryBuilder {
		return $q->where( 'post_status', '=', $status );
	}

	// ---------------------------------------------------------------
	// Relationships
	// ---------------------------------------------------------------

	/**
	 * The author of this post.
	 *
	 * @return \WPFlint\Database\ORM\BelongsTo
	 */
	public function author() {
		return $this->belongs_to( User::class, 'post_author', 'ID' );
	}

	/**
	 * Meta entries for this post.
	 *
	 * @return \WPFlint\Database\ORM\HasMany
	 */
	public function meta() {
		return $this->has_many( PostMeta::class, 'post_id', 'ID' );
	}

	/**
	 * Approved comments on this post.
	 *
	 * @return \WPFlint\Database\ORM\HasMany
	 */
	public function comments() {
		return $this->has_many( Comment::class, 'comment_post_ID', 'ID' );
	}

	/**
	 * Parent post (if any).
	 *
	 * @return \WPFlint\Database\ORM\BelongsTo
	 */
	public function parent_post() {
		return $this->belongs_to( static::class, 'post_parent', 'ID' );
	}
}
