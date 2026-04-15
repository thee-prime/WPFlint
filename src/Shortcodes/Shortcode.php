<?php
/**
 * Fluent builder for WordPress shortcodes.
 *
 * @package WPFlint\Shortcodes
 */

declare(strict_types=1);

namespace WPFlint\Shortcodes;

/**
 * Wraps add_shortcode() behind a fluent interface.
 *
 * Usage:
 *
 *     Shortcode::make( 'my_button' )
 *         ->defaults( [ 'color' => 'blue', 'size' => 'medium' ] )
 *         ->render( function( array $atts, string $content ): string {
 *             return '<button class="' . esc_attr( $atts['color'] ) . '">'
 *                  . esc_html( $content ) . '</button>';
 *         } )
 *         ->register();
 */
class Shortcode {

	/**
	 * Shortcode tag name.
	 *
	 * @var string
	 */
	protected string $tag;

	/**
	 * Default attribute values merged via shortcode_atts().
	 *
	 * @var array<string, mixed>
	 */
	protected array $defaults = array();

	/**
	 * Render callback: function( array $atts, string $content ): string.
	 *
	 * @var callable|null
	 */
	protected $render_callback = null;

	/**
	 * Create a Shortcode builder.
	 *
	 * @param string $tag Shortcode tag name.
	 */
	public function __construct( string $tag ) {
		$this->tag = $tag;
	}

	/**
	 * Static factory.
	 *
	 * @param string $tag Shortcode tag name.
	 * @return static
	 */
	public static function make( string $tag ): self {
		return new static( $tag );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Set default attribute values.
	 *
	 * These are merged with any attributes the user provides, via
	 * shortcode_atts(). Keys not present in $defaults are stripped
	 * from user-supplied attributes.
	 *
	 * @param array<string, mixed> $defaults Default key/value pairs.
	 * @return $this
	 */
	public function defaults( array $defaults ): self {
		$this->defaults = $defaults;
		return $this;
	}

	/**
	 * Set the render callback.
	 *
	 * The callback receives:
	 *   - array  $atts    Merged attributes (defaults + user-supplied).
	 *   - string $content Inner content between opening and closing tags.
	 *
	 * It must return a string (the shortcode output).
	 *
	 * @param callable $callback render( array $atts, string $content ): string.
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
	 * Register the shortcode with WordPress.
	 *
	 * Safe to call inside or after the 'init' hook.
	 *
	 * @return void
	 */
	public function register(): void {
		$tag      = $this->tag;
		$defaults = $this->defaults;
		$callback = $this->render_callback;

		add_shortcode(
			$tag,
			static function ( $atts, $content = null ) use ( $tag, $defaults, $callback ) {
				$merged = shortcode_atts( $defaults, (array) $atts, $tag );

				if ( null === $callback ) {
					return '';
				}

				return $callback( $merged, (string) $content );
			}
		);
	}

	/**
	 * Unregister the shortcode.
	 *
	 * @return void
	 */
	public function unregister(): void {
		remove_shortcode( $this->tag );
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the shortcode tag.
	 *
	 * @return string
	 */
	public function get_tag(): string {
		return $this->tag;
	}

	/**
	 * Get the default attributes.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		return $this->defaults;
	}
}
