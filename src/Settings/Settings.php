<?php
/**
 * Fluent builder for the WordPress Settings API.
 *
 * @package WPFlint\Settings
 */

declare(strict_types=1);

namespace WPFlint\Settings;

/**
 * Wraps register_setting(), add_settings_section(), and add_settings_field()
 * behind a single fluent interface.
 *
 * Usage:
 *
 *     Settings::make( 'my_plugin_group', 'my_plugin_options' )
 *         ->page( 'my-plugin-settings' )
 *         ->section( 'general', 'General Settings', function( Section $s ) {
 *             $s->field( 'api_key', 'API Key' )->type( 'text' )->required();
 *             $s->field( 'debug',   'Debug Mode' )->type( 'checkbox' );
 *         } )
 *         ->register();
 *
 * Render the form on your settings page:
 *
 *     <form method="post" action="options.php">
 *         <?php settings_fields( 'my_plugin_group' ); ?>
 *         <?php do_settings_sections( 'my-plugin-settings' ); ?>
 *         <?php submit_button(); ?>
 *     </form>
 */
class Settings {

	/**
	 * Option group name (passed to settings_fields()).
	 *
	 * @var string
	 */
	protected string $option_group;

	/**
	 * Option name stored in wp_options.
	 *
	 * @var string
	 */
	protected string $option_name;

	/**
	 * Admin page slug where sections/fields are rendered.
	 *
	 * @var string
	 */
	protected string $page = '';

	/**
	 * Registered sections.
	 *
	 * @var array<int, Section>
	 */
	protected array $sections = array();

	/**
	 * Optional sanitization callback for register_setting().
	 *
	 * @var callable|null
	 */
	protected $sanitize_callback = null;

	/**
	 * Create a Settings builder.
	 *
	 * @param string $option_group Option group (used in settings_fields()).
	 * @param string $option_name  Option key in wp_options.
	 */
	public function __construct( string $option_group, string $option_name ) {
		$this->option_group = $option_group;
		$this->option_name  = $option_name;
	}

	/**
	 * Static factory.
	 *
	 * @param string $option_group Option group.
	 * @param string $option_name  Option name.
	 * @return static
	 */
	public static function make( string $option_group, string $option_name ): self {
		return new static( $option_group, $option_name );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Set the admin page slug where these settings are rendered.
	 *
	 * This is the $page argument passed to add_settings_section() and
	 * add_settings_field(), and matches the slug you used in AdminPage::make().
	 *
	 * @param string $page Admin page slug.
	 * @return $this
	 */
	public function page( string $page ): self {
		$this->page = $page;
		return $this;
	}

	/**
	 * Set a sanitization callback for the option value.
	 *
	 * @param callable $callback Sanitization function; receives the raw input, returns sanitized value.
	 * @return $this
	 */
	public function sanitize( callable $callback ): self {
		$this->sanitize_callback = $callback;
		return $this;
	}

	/**
	 * Add a settings section.
	 *
	 * The callable receives a Section instance; call $section->field() inside it
	 * to attach fields.
	 *
	 *     ->section( 'general', 'General', function( Section $s ) {
	 *         $s->field( 'name', 'Name' )->type( 'text' );
	 *     } )
	 *
	 * @param string        $id       Section ID.
	 * @param string        $title    Section heading.
	 * @param callable|null $callback Builder callback; receives the Section as its only argument.
	 * @return $this
	 */
	public function section( string $id, string $title, callable $callback = null ): self {
		$section = new Section( $id, $title );

		if ( null !== $callback ) {
			$callback( $section );
		}

		$this->sections[] = $section;

		return $this;
	}

	// ---------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------

	/**
	 * Register the option and all sections/fields with WordPress.
	 *
	 * Call this inside or after the 'admin_init' hook.
	 *
	 * @return void
	 */
	public function register(): void {
		$args = array();

		if ( null !== $this->sanitize_callback ) {
			$args['sanitize_callback'] = $this->sanitize_callback;
		}

		register_setting( $this->option_group, $this->option_name, $args );

		foreach ( $this->sections as $section ) {
			$section_desc = $section->get_description();

			add_settings_section(
				$section->get_id(),
				$section->get_title(),
				static function () use ( $section_desc ) {
					if ( '' !== $section_desc ) {
						printf( '<p>%s</p>', esc_html( $section_desc ) );
					}
				},
				$this->page
			);

			foreach ( $section->get_fields() as $field ) {
				$option_name = $this->option_name;

				add_settings_field(
					$field->get_id(),
					$field->get_label(),
					static function () use ( $field, $option_name ) {
						$field->render( $option_name );
					},
					$this->page,
					$section->get_id()
				);
			}
		}
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the option group.
	 *
	 * @return string
	 */
	public function get_option_group(): string {
		return $this->option_group;
	}

	/**
	 * Get the option name.
	 *
	 * @return string
	 */
	public function get_option_name(): string {
		return $this->option_name;
	}

	/**
	 * Get the admin page slug.
	 *
	 * @return string
	 */
	public function get_page(): string {
		return $this->page;
	}

	/**
	 * Get registered sections.
	 *
	 * @return array<int, Section>
	 */
	public function get_sections(): array {
		return $this->sections;
	}
}
