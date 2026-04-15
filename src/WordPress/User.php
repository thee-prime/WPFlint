<?php
/**
 * WordPress User model — maps to the {prefix}users table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;

/**
 * Active-Record model for WordPress users.
 *
 * Usage:
 *
 *     $user = User::find( get_current_user_id() );
 *     $posts = $user->posts()->get_models();
 */
class User extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'users';

	/**
	 * WordPress uses 'ID' (uppercase) as the primary key.
	 *
	 * @var string
	 */
	protected static string $primary_key = 'ID';

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
		'user_login',
		'user_pass',
		'user_nicename',
		'user_email',
		'user_url',
		'user_registered',
		'user_status',
		'display_name',
	);

	/**
	 * Hidden attributes (never returned in to_array / to_json).
	 *
	 * @var array
	 */
	protected array $hidden = array(
		'user_pass',
		'user_activation_key',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'ID'          => 'integer',
		'user_status' => 'integer',
	);

	// ---------------------------------------------------------------
	// Scopes
	// ---------------------------------------------------------------

	/**
	 * Scope: users with a specific role (via usermeta).
	 *
	 * @param ModelQueryBuilder $q    Query builder.
	 * @param string            $role Role slug.
	 * @return ModelQueryBuilder
	 */
	public function scope_role( ModelQueryBuilder $q, string $role ): ModelQueryBuilder {
		global $wpdb;

		return $q->join(
			$wpdb->usermeta,
			$wpdb->users . '.ID',
			'=',
			$wpdb->usermeta . '.user_id'
		)->where( $wpdb->usermeta . '.meta_key', '=', $wpdb->prefix . 'capabilities' )
		->where( $wpdb->usermeta . '.meta_value', 'LIKE', '%' . $role . '%' );
	}

	// ---------------------------------------------------------------
	// Relationships
	// ---------------------------------------------------------------

	/**
	 * Posts authored by this user.
	 *
	 * @return \WPFlint\Database\ORM\HasMany
	 */
	public function posts() {
		return $this->has_many( Post::class, 'post_author', 'ID' );
	}

	/**
	 * Meta entries for this user.
	 *
	 * @return \WPFlint\Database\ORM\HasMany
	 */
	public function meta() {
		return $this->has_many( UserMeta::class, 'user_id', 'ID' );
	}

	/**
	 * Comments left by this user.
	 *
	 * @return \WPFlint\Database\ORM\HasMany
	 */
	public function comments() {
		return $this->has_many( Comment::class, 'user_id', 'ID' );
	}
}
