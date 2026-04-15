<?php
/**
 * Fluent builder for registering meta fields (post, term, user, comment).
 *
 * @package WPFlint\Registration
 */

declare(strict_types=1);

namespace WPFlint\Registration;

/**
 * Builds and registers a meta field via register_*_meta().
 *
 * Usage:
 *
 *     // Post meta
 *     MetaField::post( 'book', '_price' )
 *         ->type( 'number' )
 *         ->single()
 *         ->sanitize( 'floatval' )
 *         ->show_in_rest()
 *         ->register();
 *
 *     // Term meta
 *     MetaField::term( 'genre', '_color' )
 *         ->type( 'string' )
 *         ->single()
 *         ->register();
 *
 *     // User meta
 *     MetaField::user( '_bio_extra' )
 *         ->type( 'string' )
 *         ->single()
 *         ->show_in_rest()
 *         ->register();
 *
 *     // Comment meta
 *     MetaField::comment( '_rating' )
 *         ->type( 'integer' )
 *         ->single()
 *         ->register();
 */
class MetaField {

	/**
	 * Object type: 'post', 'term', 'user', or 'comment'.
	 *
	 * @var string
	 */
	protected string $object_type;

	/**
	 * Object subtype: post type slug, taxonomy slug, or '' for user/comment.
	 *
	 * @var string
	 */
	protected string $subtype;

	/**
	 * Meta key.
	 *
	 * @var string
	 */
	protected string $key;

	/**
	 * Arguments for register_*_meta().
	 *
	 * @var array<string, mixed>
	 */
	protected array $args = array();

	/**
	 * Create a MetaField builder.
	 *
	 * @param string $object_type Object type: 'post', 'term', 'user', 'comment'.
	 * @param string $subtype     Object subtype (post type or taxonomy slug; empty for user/comment).
	 * @param string $key         Meta key.
	 */
	public function __construct( string $object_type, string $subtype, string $key ) {
		$this->object_type = $object_type;
		$this->subtype     = $subtype;
		$this->key         = $key;
	}

	// ---------------------------------------------------------------
	// Static factories
	// ---------------------------------------------------------------

	/**
	 * Create a post meta field builder.
	 *
	 * @param string $post_type Post type slug, or '' for all post types.
	 * @param string $key       Meta key.
	 * @return static
	 */
	public static function post( string $post_type, string $key ): self {
		return new static( 'post', $post_type, $key );
	}

	/**
	 * Create a term meta field builder.
	 *
	 * @param string $taxonomy Taxonomy slug, or '' for all taxonomies.
	 * @param string $key      Meta key.
	 * @return static
	 */
	public static function term( string $taxonomy, string $key ): self {
		return new static( 'term', $taxonomy, $key );
	}

	/**
	 * Create a user meta field builder.
	 *
	 * @param string $key Meta key.
	 * @return static
	 */
	public static function user( string $key ): self {
		return new static( 'user', '', $key );
	}

	/**
	 * Create a comment meta field builder.
	 *
	 * @param string $key Meta key.
	 * @return static
	 */
	public static function comment( string $key ): self {
		return new static( 'comment', '', $key );
	}

	// ---------------------------------------------------------------
	// Type and value
	// ---------------------------------------------------------------

	/**
	 * Set the meta value type.
	 *
	 * @param string $type One of: string, boolean, integer, number, array, object.
	 * @return $this
	 */
	public function type( string $type ): self {
		$this->args['type'] = $type;
		return $this;
	}

	/**
	 * Mark the meta field as single-value (not an array of values).
	 *
	 * @param bool $single Whether this is a single-value field.
	 * @return $this
	 */
	public function single( bool $single = true ): self {
		$this->args['single'] = $single;
		return $this;
	}

	/**
	 * Set the default value for this meta field.
	 *
	 * @param mixed $value Default value.
	 * @return $this
	 */
	public function default( $value ): self {
		$this->args['default'] = $value;
		return $this;
	}

	/**
	 * Set a human-readable description for this meta field.
	 *
	 * @param string $description Description text.
	 * @return $this
	 */
	public function description( string $description ): self {
		$this->args['description'] = $description;
		return $this;
	}

	// ---------------------------------------------------------------
	// Sanitization and authorization
	// ---------------------------------------------------------------

	/**
	 * Set a sanitization callback for the meta value.
	 *
	 * @param callable $callback Sanitization function.
	 * @return $this
	 */
	public function sanitize( callable $callback ): self {
		$this->args['sanitize_callback'] = $callback;
		return $this;
	}

	/**
	 * Set an authorization callback for reading/writing this meta.
	 *
	 * @param callable $callback Returns true if the current user can access this meta.
	 * @return $this
	 */
	public function auth_callback( callable $callback ): self {
		$this->args['auth_callback'] = $callback;
		return $this;
	}

	// ---------------------------------------------------------------
	// REST API
	// ---------------------------------------------------------------

	/**
	 * Expose this meta field in the REST API.
	 *
	 * Pass an array to configure the schema (type, properties, etc.).
	 *
	 * @param bool|array<string, mixed> $schema True to expose, or a JSON Schema array.
	 * @return $this
	 */
	public function show_in_rest( $schema = true ): self {
		$this->args['show_in_rest'] = $schema;
		return $this;
	}

	// ---------------------------------------------------------------
	// Arbitrary args
	// ---------------------------------------------------------------

	/**
	 * Merge arbitrary extra arguments.
	 *
	 * @param array<string, mixed> $args Extra args for register_*_meta().
	 * @return $this
	 */
	public function args( array $args ): self {
		$this->args = array_merge( $this->args, $args );
		return $this;
	}

	// ---------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------

	/**
	 * Register this meta field.
	 *
	 * Routes to the correct WP function based on object_type:
	 *   post   → register_post_meta( $subtype, $key, $args )
	 *   term   → register_term_meta( $subtype, $key, $args )
	 *   user   → register_meta( 'user', $key, $args )
	 *   comment → register_meta( 'comment', $key, $args )
	 *
	 * @return void
	 */
	public function register(): void {
		switch ( $this->object_type ) {
			case 'post':
				register_post_meta( $this->subtype, $this->key, $this->get_args() );
				break;

			case 'term':
				register_term_meta( $this->subtype, $this->key, $this->get_args() );
				break;

			default:
				register_meta( $this->object_type, $this->key, $this->get_args() );
				break;
		}
	}

	/**
	 * Unregister this meta field.
	 *
	 * @return void
	 */
	public function unregister(): void {
		unregister_meta_key( $this->object_type, $this->key, $this->subtype );
	}

	/**
	 * Get the meta key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return $this->key;
	}

	/**
	 * Get the object type ('post', 'term', 'user', 'comment').
	 *
	 * @return string
	 */
	public function get_object_type(): string {
		return $this->object_type;
	}

	/**
	 * Get the object subtype (post type or taxonomy slug).
	 *
	 * @return string
	 */
	public function get_subtype(): string {
		return $this->subtype;
	}

	/**
	 * Build and return the resolved args array (without registering).
	 *
	 * @return array<string, mixed>
	 */
	public function get_args(): array {
		return $this->args;
	}
}
