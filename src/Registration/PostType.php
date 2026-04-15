<?php
/**
 * Fluent builder for registering custom post types.
 *
 * @package WPFlint\Registration
 */

declare(strict_types=1);

namespace WPFlint\Registration;

/**
 * Builds and registers a custom post type.
 *
 * Usage:
 *
 *     PostType::make( 'book' )
 *         ->label( 'Book', 'Books' )
 *         ->public()
 *         ->supports( [ 'title', 'editor', 'thumbnail' ] )
 *         ->icon( 'dashicons-book-alt' )
 *         ->show_in_rest()
 *         ->register();
 */
class PostType {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	protected string $slug;

	/**
	 * Arguments passed to register_post_type().
	 *
	 * @var array<string, mixed>
	 */
	protected array $args = array();

	/**
	 * Singular label.
	 *
	 * @var string
	 */
	protected string $singular = '';

	/**
	 * Plural label.
	 *
	 * @var string
	 */
	protected string $plural = '';

	/**
	 * Create a PostType builder.
	 *
	 * @param string $slug Post type slug (max 20 chars, no capitals or spaces).
	 */
	public function __construct( string $slug ) {
		$this->slug = $slug;
	}

	/**
	 * Create a new PostType builder (static factory).
	 *
	 * @param string $slug Post type slug.
	 * @return static
	 */
	public static function make( string $slug ): self {
		return new static( $slug );
	}

	// ---------------------------------------------------------------
	// Labels
	// ---------------------------------------------------------------

	/**
	 * Set the singular and plural labels for this post type.
	 *
	 * WordPress will derive all other labels (Add New, Edit, etc.) from these.
	 *
	 * @param string $singular Singular label, e.g. 'Book'.
	 * @param string $plural   Plural label, e.g. 'Books'. Defaults to singular + 's'.
	 * @return $this
	 */
	public function label( string $singular, string $plural = '' ): self {
		$this->singular = $singular;
		$this->plural   = '' !== $plural ? $plural : $singular . 's';
		return $this;
	}

	// ---------------------------------------------------------------
	// Visibility
	// ---------------------------------------------------------------

	/**
	 * Make the post type public (visible in admin and front-end).
	 *
	 * @param bool $public Whether to make the post type public.
	 * @return $this
	 */
	public function public( bool $public = true ): self {
		$this->args['public'] = $public;
		return $this;
	}

	/**
	 * Exclude this post type from search results.
	 *
	 * @param bool $exclude Whether to exclude from search.
	 * @return $this
	 */
	public function exclude_from_search( bool $exclude = true ): self {
		$this->args['exclude_from_search'] = $exclude;
		return $this;
	}

	/**
	 * Control whether the post type is publicly queryable.
	 *
	 * @param bool $queryable Whether front-end queries are allowed.
	 * @return $this
	 */
	public function publicly_queryable( bool $queryable = true ): self {
		$this->args['publicly_queryable'] = $queryable;
		return $this;
	}

	/**
	 * Show or hide this post type in the admin menu.
	 *
	 * @param bool $show Whether to show in admin menu.
	 * @return $this
	 */
	public function show_in_menu( bool $show = true ): self {
		$this->args['show_in_menu'] = $show;
		return $this;
	}

	/**
	 * Show this post type in the WordPress REST API.
	 *
	 * @param bool $show Whether to expose via REST API.
	 * @return $this
	 */
	public function show_in_rest( bool $show = true ): self {
		$this->args['show_in_rest'] = $show;
		return $this;
	}

	/**
	 * Set the REST API base slug.
	 *
	 * @param string $base REST base slug.
	 * @return $this
	 */
	public function rest_base( string $base ): self {
		$this->args['rest_base'] = $base;
		return $this;
	}

	// ---------------------------------------------------------------
	// Structure
	// ---------------------------------------------------------------

	/**
	 * Make this post type hierarchical (like pages).
	 *
	 * @param bool $hierarchical Whether to enable parent/child relationships.
	 * @return $this
	 */
	public function hierarchical( bool $hierarchical = true ): self {
		$this->args['hierarchical'] = $hierarchical;
		return $this;
	}

	/**
	 * Set the post type features (title, editor, thumbnail, etc.).
	 *
	 * @param array<int, string> $features Array of feature strings.
	 * @return $this
	 */
	public function supports( array $features ): self {
		$this->args['supports'] = $features;
		return $this;
	}

	/**
	 * Set the dashicon or URL for the admin menu icon.
	 *
	 * @param string $icon Dashicon class (e.g. 'dashicons-book-alt') or image URL.
	 * @return $this
	 */
	public function icon( string $icon ): self {
		$this->args['menu_icon'] = $icon;
		return $this;
	}

	/**
	 * Set the admin menu position.
	 *
	 * @param int $position Menu position integer.
	 * @return $this
	 */
	public function menu_position( int $position ): self {
		$this->args['menu_position'] = $position;
		return $this;
	}

	/**
	 * Enable or set the archive slug for this post type.
	 *
	 * @param bool|string $archive True to enable, string for custom slug.
	 * @return $this
	 */
	public function has_archive( $archive = true ): self {
		$this->args['has_archive'] = $archive;
		return $this;
	}

	/**
	 * Configure the permalink rewrite rules.
	 *
	 * @param array<string, mixed>|bool $rewrite Rewrite options array, or false to disable.
	 * @return $this
	 */
	public function rewrite( $rewrite ): self {
		$this->args['rewrite'] = $rewrite;
		return $this;
	}

	/**
	 * Set the capability type (used to build capability names).
	 *
	 * @param string $type    Base capability type, e.g. 'post', 'page', 'book'.
	 * @param bool   $map_meta Whether to map meta capabilities.
	 * @return $this
	 */
	public function capability_type( string $type, bool $map_meta = true ): self {
		$this->args['capability_type'] = $type;
		$this->args['map_meta_cap']    = $map_meta;
		return $this;
	}

	/**
	 * Attach taxonomies to this post type at registration time.
	 *
	 * @param array<int, string> $taxonomies Taxonomy slugs.
	 * @return $this
	 */
	public function taxonomies( array $taxonomies ): self {
		$this->args['taxonomies'] = $taxonomies;
		return $this;
	}

	/**
	 * Merge arbitrary extra arguments (takes precedence over fluent setters).
	 *
	 * @param array<string, mixed> $args Extra args for register_post_type().
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
	 * Build the final args array and call register_post_type().
	 *
	 * Safe to call inside 'init' action hook.
	 *
	 * @return void
	 */
	public function register(): void {
		register_post_type( $this->slug, $this->get_args() ); // phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral
	}

	/**
	 * Unregister this post type.
	 *
	 * @return void
	 */
	public function unregister(): void {
		unregister_post_type( $this->slug );
	}

	/**
	 * Whether this post type is currently registered.
	 *
	 * @return bool
	 */
	public function registered(): bool {
		return post_type_exists( $this->slug );
	}

	/**
	 * Get the slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Build and return the resolved args array (without registering).
	 *
	 * @return array<string, mixed>
	 */
	public function get_args(): array {
		$args = $this->args;

		if ( '' !== $this->singular ) {
			$args['labels'] = $this->build_labels();
		}

		return $args;
	}

	// ---------------------------------------------------------------
	// Internals
	// ---------------------------------------------------------------

	/**
	 * Build the labels array from singular/plural.
	 *
	 * @return array<string, string>
	 */
	protected function build_labels(): array {
		$s = $this->singular;
		$p = $this->plural;

		return array(
			'name'               => $p,
			'singular_name'      => $s,
			/* translators: %s: post type singular name */
			'add_new_item'       => sprintf( __( 'Add New %s', 'wpflint' ), $s ),
			/* translators: %s: post type singular name */
			'edit_item'          => sprintf( __( 'Edit %s', 'wpflint' ), $s ),
			/* translators: %s: post type singular name */
			'new_item'           => sprintf( __( 'New %s', 'wpflint' ), $s ),
			/* translators: %s: post type plural name */
			'view_items'         => sprintf( __( 'View %s', 'wpflint' ), $p ),
			/* translators: %s: post type singular name */
			'view_item'          => sprintf( __( 'View %s', 'wpflint' ), $s ),
			/* translators: %s: post type plural name */
			'search_items'       => sprintf( __( 'Search %s', 'wpflint' ), $p ),
			/* translators: %s: post type plural name */
			'not_found'          => sprintf( __( 'No %s found.', 'wpflint' ), strtolower( $p ) ),
			/* translators: %s: post type plural name */
			'not_found_in_trash' => sprintf( __( 'No %s found in Trash.', 'wpflint' ), strtolower( $p ) ),
			/* translators: %s: post type plural name */
			'all_items'          => sprintf( __( 'All %s', 'wpflint' ), $p ),
			'menu_name'          => $p,
		);
	}
}
