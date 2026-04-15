<?php
/**
 * A single settings field within a Settings section.
 *
 * @package WPFlint\Settings
 */

declare(strict_types=1);

namespace WPFlint\Settings;

/**
 * Represents one settings field registered via add_settings_field().
 *
 * @internal Created via Section::field(), not directly.
 */
class Field {

	/**
	 * Field ID (used as key in the option array and as the HTML element id).
	 *
	 * @var string
	 */
	protected string $id;

	/**
	 * Human-readable label rendered next to the field.
	 *
	 * @var string
	 */
	protected string $label;

	/**
	 * HTML input type: text, email, url, number, password, checkbox, textarea, select.
	 *
	 * @var string
	 */
	protected string $type = 'text';

	/**
	 * Default value when the option has not been saved yet.
	 *
	 * @var mixed
	 */
	protected $default = '';

	/**
	 * Optional helper text shown below the field.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Whether the field is required.
	 *
	 * @var bool
	 */
	protected bool $required = false;

	/**
	 * Options for select/radio fields: ['value' => 'Label', ...].
	 *
	 * @var array<string, string>
	 */
	protected array $options = array();

	/**
	 * Create a Field builder.
	 *
	 * @param string $id    Field ID.
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
	 * Set the HTML input type.
	 *
	 * @param string $type text|email|url|number|password|checkbox|textarea|select.
	 * @return $this
	 */
	public function type( string $type ): self {
		$this->type = $type;
		return $this;
	}

	/**
	 * Set the default value when the option has not yet been saved.
	 *
	 * @param mixed $value Default value.
	 * @return $this
	 */
	public function default( $value ): self {
		$this->default = $value;
		return $this;
	}

	/**
	 * Set the helper description shown below the field.
	 *
	 * @param string $description Help text.
	 * @return $this
	 */
	public function description( string $description ): self {
		$this->description = $description;
		return $this;
	}

	/**
	 * Mark this field as required.
	 *
	 * @param bool $required Whether the field is required.
	 * @return $this
	 */
	public function required( bool $required = true ): self {
		$this->required = $required;
		return $this;
	}

	/**
	 * Set allowed options for select/radio fields.
	 *
	 * @param array<string, string> $options ['value' => 'Label'] pairs.
	 * @return $this
	 */
	public function options( array $options ): self {
		$this->options = $options;
		return $this;
	}

	// ---------------------------------------------------------------
	// Rendering
	// ---------------------------------------------------------------

	/**
	 * Output the field HTML.
	 *
	 * Called by the add_settings_field() callback registered in Settings::register().
	 *
	 * @param string $option_name The option name used to retrieve the stored value.
	 * @return void
	 */
	public function render( string $option_name ): void {
		$saved       = get_option( $option_name );
		$field_value = is_array( $saved ) ? ( $saved[ $this->id ] ?? $this->default ) : $this->default;
		$name        = $option_name . '[' . $this->id . ']';
		$html_id     = $option_name . '_' . $this->id;
		$required    = $this->required ? ' required' : '';

		switch ( $this->type ) {
			case 'checkbox':
				printf(
					'<input type="checkbox" id="%s" name="%s" value="1" %s%s />',
					esc_attr( $html_id ),
					esc_attr( $name ),
					checked( '1', (string) $field_value, false ),
					esc_attr( $required )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="5" cols="50"%s>%s</textarea>',
					esc_attr( $html_id ),
					esc_attr( $name ),
					esc_attr( $required ),
					esc_textarea( (string) $field_value )
				);
				break;

			case 'select':
				printf(
					'<select id="%s" name="%s"%s>',
					esc_attr( $html_id ),
					esc_attr( $name ),
					esc_attr( $required )
				);
				foreach ( $this->options as $val => $option_label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( (string) $val ),
						selected( (string) $val, (string) $field_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;

			default:
				printf(
					'<input type="%s" id="%s" name="%s" value="%s" class="regular-text"%s />',
					esc_attr( $this->type ),
					esc_attr( $html_id ),
					esc_attr( $name ),
					esc_attr( (string) $field_value ),
					esc_attr( $required )
				);
		}

		if ( '' !== $this->description ) {
			printf( '<p class="description">%s</p>', esc_html( $this->description ) );
		}
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the field ID.
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
	 * Get the default value.
	 *
	 * @return mixed
	 */
	public function get_default() {
		return $this->default;
	}

	/**
	 * Get the description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Whether the field is required.
	 *
	 * @return bool
	 */
	public function is_required(): bool {
		return $this->required;
	}

	/**
	 * Get select/radio options.
	 *
	 * @return array<string, string>
	 */
	public function get_options(): array {
		return $this->options;
	}
}
