<?php
/**
 * Fluent builder for Gutenberg block registration.
 *
 * @package WPFlint\Blocks
 */

declare(strict_types=1);

namespace WPFlint\Blocks;

/**
 * Wraps register_block_type() behind a fluent interface.
 *
 * Usage:
 *
 *     Block::make( 'my-plugin/pricing-table' )
 *         ->title( 'Pricing Table' )
 *         ->category( 'widgets' )
 *         ->script( 'my-plugin-blocks' )
 *         ->render( function ( array $attrs, string $content ): string {
 *             return '<div class="pricing-table">' . $content . '</div>';
 *         } )
 *         ->register();
 */
class Block {

	/**
	 * Block name in namespace/block-name format.
	 *
	 * @var string
	 */
	protected string $name;

	/**
	 * Human-readable block title.
	 *
	 * @var string
	 */
	protected string $title = '';

	/**
	 * Block category slug.
	 *
	 * @var string
	 */
	protected string $category = '';

	/**
	 * Dashicon slug or SVG for the block icon.
	 *
	 * @var string
	 */
	protected string $icon = '';

	/**
	 * Short description of the block.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Search keywords for the block inserter.
	 *
	 * @var array<int, string>
	 */
	protected array $keywords = array();

	/**
	 * Block attribute definitions.
	 *
	 * @var array<string, mixed>
	 */
	protected array $attributes = array();

	/**
	 * Registered script handle for the block editor.
	 *
	 * @var string
	 */
	protected string $editor_script = '';

	/**
	 * Registered script handle for the block frontend.
	 *
	 * @var string
	 */
	protected string $script_handle = '';

	/**
	 * Registered style handle for the block.
	 *
	 * @var string
	 */
	protected string $style_handle = '';

	/**
	 * Registered style handle for the block editor.
	 *
	 * @var string
	 */
	protected string $editor_style = '';

	/**
	 * PHP render callback — function( array $attrs, string $content ): string.
	 *
	 * @var callable|null
	 */
	protected $render_callback = null;

	/**
	 * Create a Block builder.
	 *
	 * @param string $name Block name in namespace/block-name format.
	 */
	public function __construct( string $name ) {
		$this->name = $name;
	}

	/**
	 * Static factory.
	 *
	 * @param string $name Block name in namespace/block-name format.
	 * @return static
	 */
	public static function make( string $name ): self {
		return new static( $name );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Set the human-readable block title.
	 *
	 * @param string $title Block title.
	 * @return $this
	 */
	public function title( string $title ): self {
		$this->title = $title;
		return $this;
	}

	/**
	 * Set the block category.
	 *
	 * @param string $category Category slug (e.g. 'text', 'media', 'design', 'widgets', 'theme').
	 * @return $this
	 */
	public function category( string $category ): self {
		$this->category = $category;
		return $this;
	}

	/**
	 * Set the block icon (dashicon slug or inline SVG).
	 *
	 * @param string $icon Dashicon slug (e.g. 'dashicons-admin-tools') or SVG string.
	 * @return $this
	 */
	public function icon( string $icon ): self {
		$this->icon = $icon;
		return $this;
	}

	/**
	 * Set a short description shown in the block inserter.
	 *
	 * @param string $description Short block description.
	 * @return $this
	 */
	public function description( string $description ): self {
		$this->description = $description;
		return $this;
	}

	/**
	 * Set search keywords for the block inserter.
	 *
	 * @param array<int, string> $keywords Array of keyword strings.
	 * @return $this
	 */
	public function keywords( array $keywords ): self {
		$this->keywords = $keywords;
		return $this;
	}

	/**
	 * Define block attributes.
	 *
	 * @param array<string, mixed> $attributes Attribute definitions as per block.json schema.
	 * @return $this
	 */
	public function attributes( array $attributes ): self {
		$this->attributes = $attributes;
		return $this;
	}

	/**
	 * Set the registered script handle for the block editor.
	 *
	 * @param string $handle Registered script handle.
	 * @return $this
	 */
	public function editor_script( string $handle ): self {
		$this->editor_script = $handle;
		return $this;
	}

	/**
	 * Set the registered script handle for the block frontend.
	 *
	 * @param string $handle Registered script handle.
	 * @return $this
	 */
	public function script( string $handle ): self {
		$this->script_handle = $handle;
		return $this;
	}

	/**
	 * Set the registered style handle for the block.
	 *
	 * @param string $handle Registered style handle.
	 * @return $this
	 */
	public function style( string $handle ): self {
		$this->style_handle = $handle;
		return $this;
	}

	/**
	 * Set the registered style handle for the block editor.
	 *
	 * @param string $handle Registered style handle.
	 * @return $this
	 */
	public function editor_style( string $handle ): self {
		$this->editor_style = $handle;
		return $this;
	}

	/**
	 * Set the server-side render callback.
	 *
	 * The callback receives:
	 *   - array  $attrs   Block attributes.
	 *   - string $content Inner block content.
	 *
	 * It must return the HTML string.
	 *
	 * @param callable $callback render( array $attrs, string $content ): string.
	 * @return $this
	 */
	public function render( callable $callback ): self {
		$this->render_callback = $callback;
		return $this;
	}

	// ---------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------

	/**
	 * Build the args array passed to register_block_type().
	 *
	 * Only non-empty values are included.
	 *
	 * @return array<string, mixed>
	 */
	public function to_args(): array {
		$args = array();

		if ( '' !== $this->title ) {
			$args['title'] = $this->title;
		}

		if ( '' !== $this->category ) {
			$args['category'] = $this->category;
		}

		if ( '' !== $this->icon ) {
			$args['icon'] = $this->icon;
		}

		if ( '' !== $this->description ) {
			$args['description'] = $this->description;
		}

		if ( ! empty( $this->keywords ) ) {
			$args['keywords'] = $this->keywords;
		}

		if ( ! empty( $this->attributes ) ) {
			$args['attributes'] = $this->attributes;
		}

		if ( '' !== $this->editor_script ) {
			$args['editor_script'] = $this->editor_script;
		}

		if ( '' !== $this->script_handle ) {
			$args['script'] = $this->script_handle;
		}

		if ( '' !== $this->style_handle ) {
			$args['style'] = $this->style_handle;
		}

		if ( '' !== $this->editor_style ) {
			$args['editor_style'] = $this->editor_style;
		}

		if ( null !== $this->render_callback ) {
			$args['render_callback'] = $this->render_callback;
		}

		return $args;
	}

	/**
	 * Register the block with WordPress.
	 *
	 * Safe to call inside the 'init' hook.
	 *
	 * @return void
	 */
	public function register(): void {
		register_block_type( $this->name, $this->to_args() );
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get the block title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Get the block category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return $this->category;
	}

	/**
	 * Get the render callback.
	 *
	 * @return callable|null
	 */
	public function get_render_callback() {
		return $this->render_callback;
	}

	/**
	 * Get the keywords array.
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return $this->keywords;
	}

	/**
	 * Get the attributes definition.
	 *
	 * @return array<string, mixed>
	 */
	public function get_attributes(): array {
		return $this->attributes;
	}
}
