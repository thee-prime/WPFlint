<?php
/**
 * WordPress UserMeta model — maps to the {prefix}usermeta table.
 *
 * @package WPFlint\WordPress
 */

declare(strict_types=1);

namespace WPFlint\WordPress;

use WPFlint\Database\ORM\Model;

/**
 * Active-Record model for WordPress user meta.
 *
 * Usage:
 *
 *     $meta = UserMeta::where( 'user_id', $user_id )
 *                     ->where( 'meta_key', 'billing_address' )
 *                     ->first_model();
 */
class UserMeta extends Model {

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected static string $table = 'usermeta';

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected static string $primary_key = 'umeta_id';

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
		'user_id',
		'meta_key',
		'meta_value',
	);

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'umeta_id' => 'integer',
		'user_id'  => 'integer',
	);

	// ---------------------------------------------------------------
	// Relationships
	// ---------------------------------------------------------------

	/**
	 * The user this meta belongs to.
	 *
	 * @return \WPFlint\Database\ORM\BelongsTo
	 */
	public function user() {
		return $this->belongs_to( User::class, 'user_id', 'ID' );
	}
}
