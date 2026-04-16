<?php
/**
 * Individual field within a metabox.
 *
 * @package WPFlint\Admin
 */

declare(strict_types=1);

namespace WPFlint\Admin;

/**
 * Renders and saves a single metabox field.
 *
 * Supported types: text, textarea, checkbox, select, number, email, url.
 * Custom sanitisation can be supplied via sanitize_with().
 *
 * Usage:
 *
 *     $field = new MetaBoxField( '_isbn', 'ISBN' );
 *     $field->type( 'text' )->description( 'Enter the book ISBN.' );
 *
 *     // Inside a render callback:
 *     $field->render( $post->ID );
 *
 *     // Inside a save_post callback (after nonce check):
 *     $field->save( $post_id );
 */
class MetaBoxField {

	/**
	 * Meta key / POST field name.
	 *
	 * @var string
	 */
	protected string $id;

	/**
	 * Human-readable field label.
	 *
	 * @var string
	 */
	protected string $label;

	/**
	 * Input type (text, textarea, checkbox, select, number, email, url).
	 *
	 * @var string
	 */
	protected string $type = 'text';

	/**
	 * Helper description shown below the input.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Default value when no meta is saved yet.
	 *
	 * @var mixed
	 */
	protected $default_val = '';

	/**
	 * Options for select/radio fields — [ value => label ].
	 *
	 * @var array<string|int, string>
	 */
	protected array $options = array();

	/**
	 * Custom sanitise callback; overrides the built-in type-based logic.
	 *
	 * @var callable|null
	 */
	protected $sanitize_callback = null;

	/**
	 * Create a MetaBoxField.
	 *
	 * @param string $id    Meta key and HTML field name/id.
	 * @param string $label Human-readable label.
	 */
	public function __construct( string $id, string $label ) {
		$this->id    = $id;
		$this->label = $label;
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Set the input type.
	 *
	 * @param string $type Input type: text|textarea|checkbox|select|number|email|url.
	 * @return $this
	 */
	public function type( string $type ): self {
		$this->type = $type;
		return $this;
	}

	/**
	 * Set a helper description shown below the field.
	 *
	 * @param string $description Helper text.
	 * @return $this
	 */
	public function description( string $description ): self {
		$this->description = $description;
		return $this;
	}

	/**
	 * Set a default value used when no meta has been saved.
	 *
	 * @param mixed $value Default field value.
	 * @return $this
	 */
	public function default_value( $value ): self {
		$this->default_val = $value;
		return $this;
	}

	/**
	 * Set options for select/radio fields.
	 *
	 * @param array<string|int, string> $options Associative array of value => label pairs.
	 * @return $this
	 */
	public function options( array $options ): self {
		$this->options = $options;
		return $this;
	}

	/**
	 * Provide a custom sanitise callback used in save().
	 *
	 * Receives the raw posted value; must return a sanitised scalar.
	 *
	 * @param callable $callback sanitize( mixed $value ): mixed.
	 * @return $this
	 */
	public function sanitize_with( callable $callback ): self {
		$this->sanitize_callback = $callback;
		return $this;
	}

	// ---------------------------------------------------------------
	// Rendering
	// ---------------------------------------------------------------

	/**
	 * Output the field HTML inside a metabox.
	 *
	 * All dynamic values are escaped before output.
	 *
	 * @param int $post_id Current post ID used to retrieve existing meta.
	 * @return void
	 */
	public function render( int $post_id ): void {
		$raw   = get_post_meta( $post_id, $this->id, true );
		$value = ( '' !== $raw ) ? $raw : $this->default_val;

		$html  = '<p>';
		$html .= '<label for="' . esc_attr( $this->id ) . '"><strong>' . esc_html( $this->label ) . '</strong></label><br>';

		switch ( $this->type ) {
			case 'textarea':
				$html .= '<textarea id="' . esc_attr( $this->id ) . '" name="' . esc_attr( $this->id ) . '" rows="4" style="width:100%">'
					. esc_textarea( (string) $value )
					. '</textarea>';
				break;

			case 'checkbox':
				$html .= '<input type="checkbox" id="' . esc_attr( $this->id ) . '" name="' . esc_attr( $this->id ) . '" value="1" '
					. checked( 1, $value, false ) . '>';
				break;

			case 'select':
				$html .= '<select id="' . esc_attr( $this->id ) . '" name="' . esc_attr( $this->id ) . '">';
				foreach ( $this->options as $opt_val => $opt_label ) {
					$html .= '<option value="' . esc_attr( (string) $opt_val ) . '" '
						. selected( (string) $opt_val, (string) $value, false ) . '>'
						. esc_html( $opt_label )
						. '</option>';
				}
				$html .= '</select>';
				break;

			default:
				$html .= '<input type="' . esc_attr( $this->type ) . '" id="' . esc_attr( $this->id ) . '" '
					. 'name="' . esc_attr( $this->id ) . '" value="' . esc_attr( (string) $value ) . '" style="width:100%">';
		}

		if ( '' !== $this->description ) {
			$html .= '<span class="description">' . esc_html( $this->description ) . '</span>';
		}

		$html .= '</p>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic values are escaped above.
		echo $html;
	}

	// ---------------------------------------------------------------
	// Saving
	// ---------------------------------------------------------------

	/**
	 * Read the posted value and persist it to post meta.
	 *
	 * Must be called AFTER the metabox nonce has been verified.
	 *
	 * @param int $post_id Post ID to save the meta against.
	 * @return void
	 */
	public function save( int $post_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by MetaBox before calling save().
		if ( 'checkbox' === $this->type && ! isset( $_POST[ $this->id ] ) ) {
			delete_post_meta( $post_id, $this->id );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by MetaBox before calling save().
		if ( ! isset( $_POST[ $this->id ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified upstream; sanitised below.
		$raw = wp_unslash( $_POST[ $this->id ] );

		if ( null !== $this->sanitize_callback ) {
			$value = ( $this->sanitize_callback )( $raw );
		} else {
			switch ( $this->type ) {
				case 'textarea':
					$value = sanitize_textarea_field( (string) $raw );
					break;
				case 'checkbox':
					$value = '1';
					break;
				default:
					$value = sanitize_text_field( (string) $raw );
			}
		}

		update_post_meta( $post_id, $this->id, $value );
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the field ID / meta key.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Get the field label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Get the input type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get the helper description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Get the default value.
	 *
	 * @return mixed
	 */
	public function get_default() {
		return $this->default_val;
	}

	/**
	 * Get the options array (for select/radio fields).
	 *
	 * @return array<string|int, string>
	 */
	public function get_options(): array {
		return $this->options;
	}
}
