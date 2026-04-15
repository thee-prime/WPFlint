<?php
/**
 * WordPress Comment model — maps to the {prefix}comments table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;

/**
 * Active-Record model for WordPress comments.
 *
 * Usage:
 *
 *     $comments = Comment::approved()->where( 'comment_post_ID', $post_id )->get_models();
 */
class Comment extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'comments';

	/**
	 * WordPress uses 'comment_ID' (uppercase ID) as the primary key.
	 *
	 * @var string
	 */
	protected static string $primary_key = 'comment_ID';

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
		'comment_post_ID',
		'comment_author',
		'comment_author_email',
		'comment_author_url',
		'comment_author_IP',
		'comment_date',
		'comment_date_gmt',
		'comment_content',
		'comment_karma',
		'comment_approved',
		'comment_agent',
		'comment_type',
		'comment_parent',
		'user_id',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'comment_ID'      => 'integer',
		'comment_post_ID' => 'integer',
		'comment_karma'   => 'integer',
		'comment_parent'  => 'integer',
		'user_id'         => 'integer',
	);

	// ---------------------------------------------------------------
	// Scopes
	// ---------------------------------------------------------------

	/**
	 * Scope: approved comments (comment_approved = '1').
	 *
	 * @param ModelQueryBuilder $q Query builder.
	 * @return ModelQueryBuilder
	 */
	public function scope_approved( ModelQueryBuilder $q ): ModelQueryBuilder {
		return $q->where( 'comment_approved', '=', '1' );
	}

	/**
	 * Scope: pending comments (comment_approved = '0').
	 *
	 * @param ModelQueryBuilder $q Query builder.
	 * @return ModelQueryBuilder
	 */
	public function scope_pending( ModelQueryBuilder $q ): ModelQueryBuilder {
		return $q->where( 'comment_approved', '=', '0' );
	}

	/**
	 * Scope: spam comments.
	 *
	 * @param ModelQueryBuilder $q Query builder.
	 * @return ModelQueryBuilder
	 */
	public function scope_spam( ModelQueryBuilder $q ): ModelQueryBuilder {
		return $q->where( 'comment_approved', '=', 'spam' );
	}

	/**
	 * Scope: comments of a specific type.
	 *
	 * @param ModelQueryBuilder $q    Query builder.
	 * @param string            $type Comment type (e.g. 'comment', 'pingback').
	 * @return ModelQueryBuilder
	 */
	public function scope_type( ModelQueryBuilder $q, string $type ): ModelQueryBuilder {
		return $q->where( 'comment_type', '=', $type );
	}

	// ---------------------------------------------------------------
	// Relationships
	// ---------------------------------------------------------------

	/**
	 * The post this comment belongs to.
	 *
	 * @return \WPFlint\Database\ORM\BelongsTo
	 */
	public function post() {
		return $this->belongs_to( Post::class, 'comment_post_ID', 'ID' );
	}

	/**
	 * The registered user who left the comment (if any).
	 *
	 * @return \WPFlint\Database\ORM\BelongsTo
	 */
	public function user() {
		return $this->belongs_to( User::class, 'user_id', 'ID' );
	}

	/**
	 * Meta entries for this comment.
	 *
	 * @return \WPFlint\Database\ORM\HasMany
	 */
	public function meta() {
		return $this->has_many( CommentMeta::class, 'comment_id', 'comment_ID' );
	}
}
