<?php
/**
 * Fluent builder for registering custom taxonomies.
 *
 * @package WPFlint\Registration
 */

declare(strict_types=1);

namespace WPFlint\Registration;

/**
 * Builds and registers a custom taxonomy.
 *
 * Usage:
 *
 *     Taxonomy::make( 'genre' )
 *         ->label( 'Genre', 'Genres' )
 *         ->for( 'book' )
 *         ->hierarchical()
 *         ->show_in_rest()
 *         ->register();
 */
class Taxonomy {

	/**
	 * Taxonomy slug.
	 *
	 * @var string
	 */
	protected string $slug;

	/**
	 * Post types this taxonomy is attached to.
	 *
	 * @var array<int, string>
	 */
	protected array $post_types = array();

	/**
	 * Arguments passed to register_taxonomy().
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
	 * Create a Taxonomy builder.
	 *
	 * @param string $slug Taxonomy slug.
	 */
	public function __construct( string $slug ) {
		$this->slug = $slug;
	}

	/**
	 * Create a new Taxonomy builder (static factory).
	 *
	 * @param string $slug Taxonomy slug.
	 * @return static
	 */
	public static function make( string $slug ): self {
		return new static( $slug );
	}

	// ---------------------------------------------------------------
	// Labels
	// ---------------------------------------------------------------

	/**
	 * Set the singular and plural labels.
	 *
	 * @param string $singular Singular label, e.g. 'Genre'.
	 * @param string $plural   Plural label, e.g. 'Genres'. Defaults to singular + 's'.
	 * @return $this
	 */
	public function label( string $singular, string $plural = '' ): self {
		$this->singular = $singular;
		$this->plural   = '' !== $plural ? $plural : $singular . 's';
		return $this;
	}

	// ---------------------------------------------------------------
	// Post type binding
	// ---------------------------------------------------------------

	/**
	 * Attach this taxonomy to one or more post types.
	 *
	 * @param string|array<int, string> $post_types Post type slug(s).
	 * @return $this
	 */
	public function for( $post_types ): self {
		$this->post_types = array_merge(
			$this->post_types,
			(array) $post_types
		);
		return $this;
	}

	// ---------------------------------------------------------------
	// Visibility
	// ---------------------------------------------------------------

	/**
	 * Make the taxonomy public.
	 *
	 * @param bool $public Whether the taxonomy is public.
	 * @return $this
	 */
	public function public( bool $public = true ): self {
		$this->args['public'] = $public;
		return $this;
	}

	/**
	 * Show the taxonomy in the WordPress REST API.
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

	/**
	 * Show taxonomy counts in the admin list column.
	 *
	 * @param bool $show Whether to show the admin column.
	 * @return $this
	 */
	public function show_admin_column( bool $show = true ): self {
		$this->args['show_admin_column'] = $show;
		return $this;
	}

	/**
	 * Show this taxonomy in the tag cloud widget.
	 *
	 * @param bool $show Whether to show in tag cloud.
	 * @return $this
	 */
	public function show_tagcloud( bool $show = true ): self {
		$this->args['show_tagcloud'] = $show;
		return $this;
	}

	// ---------------------------------------------------------------
	// Structure
	// ---------------------------------------------------------------

	/**
	 * Make the taxonomy hierarchical (like categories).
	 *
	 * @param bool $hierarchical Whether terms can have parent/child relationships.
	 * @return $this
	 */
	public function hierarchical( bool $hierarchical = true ): self {
		$this->args['hierarchical'] = $hierarchical;
		return $this;
	}

	/**
	 * Configure the permalink rewrite rules.
	 *
	 * @param array<string, mixed>|bool $rewrite Rewrite options or false to disable.
	 * @return $this
	 */
	public function rewrite( $rewrite ): self {
		$this->args['rewrite'] = $rewrite;
		return $this;
	}

	/**
	 * Merge arbitrary extra arguments.
	 *
	 * @param array<string, mixed> $args Extra args for register_taxonomy().
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
	 * Build the final args array and call register_taxonomy().
	 *
	 * Safe to call inside 'init' action hook.
	 *
	 * @return void
	 */
	public function register(): void {
		register_taxonomy( $this->slug, $this->post_types, $this->get_args() ); // phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral
	}

	/**
	 * Unregister this taxonomy.
	 *
	 * @return void
	 */
	public function unregister(): void {
		unregister_taxonomy( $this->slug );
	}

	/**
	 * Whether this taxonomy is currently registered.
	 *
	 * @return bool
	 */
	public function registered(): bool {
		return taxonomy_exists( $this->slug );
	}

	/**
	 * Get the taxonomy slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Get the post types this taxonomy is attached to.
	 *
	 * @return array<int, string>
	 */
	public function get_post_types(): array {
		return $this->post_types;
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
			'name'              => $p,
			'singular_name'     => $s,
			/* translators: %s: taxonomy singular name */
			'search_items'      => sprintf( __( 'Search %s', 'wpflint' ), $p ),
			/* translators: %s: taxonomy plural name */
			'all_items'         => sprintf( __( 'All %s', 'wpflint' ), $p ),
			/* translators: %s: taxonomy singular name */
			'parent_item'       => sprintf( __( 'Parent %s', 'wpflint' ), $s ),
			/* translators: %s: taxonomy singular name */
			'parent_item_colon' => sprintf( __( 'Parent %s:', 'wpflint' ), $s ),
			/* translators: %s: taxonomy singular name */
			'edit_item'         => sprintf( __( 'Edit %s', 'wpflint' ), $s ),
			/* translators: %s: taxonomy singular name */
			'update_item'       => sprintf( __( 'Update %s', 'wpflint' ), $s ),
			/* translators: %s: taxonomy singular name */
			'add_new_item'      => sprintf( __( 'Add New %s', 'wpflint' ), $s ),
			/* translators: %s: taxonomy singular name */
			'new_item_name'     => sprintf( __( 'New %s Name', 'wpflint' ), $s ),
			'menu_name'         => $p,
		);
	}
}
