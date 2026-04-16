<?php
/**
 * Abstract base for WordPress widgets.
 *
 * @package WPFlint\Widgets
 */

declare(strict_types=1);

namespace WPFlint\Widgets;

/**
 * Reduces WP_Widget boilerplate to three methods: output(), fields(), sanitize().
 *
 * Usage:
 *
 *     class RecentPostsWidget extends AbstractWidget {
 *         protected string $widget_title = 'Recent Posts';
 *         protected string $description  = 'Displays a list of recent posts.';
 *
 *         protected function output( array $args, array $instance ): void {
 *             // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
 *             echo $args['before_widget'];
 *             // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
 *             echo $args['before_title'] . esc_html( $instance['title'] ?? '' ) . $args['after_title'];
 *             // ... render widget content
 *             // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
 *             echo $args['after_widget'];
 *         }
 *
 *         protected function fields( array $instance ): void {
 *             $title = $instance['title'] ?? '';
 *             echo '<p><label for="' . esc_attr( $this->get_field_id( 'title' ) ) . '">Title</label>';
 *             echo '<input type="text" id="' . esc_attr( $this->get_field_id( 'title' ) ) . '"
 *                   name="' . esc_attr( $this->get_field_name( 'title' ) ) . '"
 *                   value="' . esc_attr( $title ) . '"></p>';
 *         }
 *
 *         protected function sanitize( array $new_instance, array $old_instance ): array {
 *             return [ 'title' => sanitize_text_field( $new_instance['title'] ?? '' ) ];
 *         }
 *     }
 *
 *     // In your plugin or service provider boot():
 *     RecentPostsWidget::register();
 */
abstract class AbstractWidget extends \WP_Widget {

	/**
	 * Human-readable widget title shown in the admin widget list.
	 * Override in your subclass.
	 *
	 * @var string
	 */
	protected string $widget_title = '';

	/**
	 * Short description shown in the widget admin.
	 * Override in your subclass.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Initialise the widget by registering it with WP_Widget.
	 *
	 * The id_base is derived from the concrete class's short name
	 * converted to snake_case (e.g. RecentPostsWidget → recent_posts_widget).
	 */
	public function __construct() {
		$parts      = explode( '\\', static::class );
		$short_name = (string) end( $parts );
		$id_base    = strtolower( (string) preg_replace( '/[A-Z]/', '_$0', lcfirst( $short_name ) ) );
		$id_base    = ltrim( $id_base, '_' );

		parent::__construct(
			$id_base,
			$this->widget_title,
			array( 'description' => $this->description )
		);
	}

	// ---------------------------------------------------------------
	// WP_Widget bridge — seal these so subclasses use our API
	// ---------------------------------------------------------------

	/**
	 * Render the widget on the frontend.
	 *
	 * Delegates to output(). Do not override — implement output() instead.
	 *
	 * @param array<string, mixed> $args     Display arguments including before/after widget and title.
	 * @param array<string, mixed> $instance Saved widget settings.
	 * @return void
	 */
	final public function widget( $args, $instance ): void {
		$this->output( (array) $args, (array) $instance );
	}

	/**
	 * Render the widget settings form in the admin.
	 *
	 * Delegates to fields(). Do not override — implement fields() instead.
	 *
	 * @param array<string, mixed> $instance Current saved settings.
	 * @return void
	 */
	final public function form( $instance ): void {
		$this->fields( (array) $instance );
	}

	/**
	 * Sanitise and return updated widget settings.
	 *
	 * Delegates to sanitize(). Override sanitize() for custom logic.
	 *
	 * @param array<string, mixed> $new_instance Settings just submitted via the form.
	 * @param array<string, mixed> $old_instance Previously saved settings.
	 * @return array<string, mixed>
	 */
	public function update( $new_instance, $old_instance ): array {
		return $this->sanitize( (array) $new_instance, (array) $old_instance );
	}

	// ---------------------------------------------------------------
	// Template methods — implement these in your subclass
	// ---------------------------------------------------------------

	/**
	 * Render the widget's visible output on the frontend.
	 *
	 * @param array<string, mixed> $args     Theme-supplied wrapper tags and classes.
	 * @param array<string, mixed> $instance Saved widget settings.
	 * @return void
	 */
	abstract protected function output( array $args, array $instance ): void;

	/**
	 * Render the widget's admin settings form fields.
	 *
	 * Use $this->get_field_id() and $this->get_field_name() for correct
	 * HTML id/name attributes.
	 *
	 * @param array<string, mixed> $instance Current saved settings.
	 * @return void
	 */
	abstract protected function fields( array $instance ): void;

	/**
	 * Sanitise and return new widget settings before they are saved.
	 *
	 * The default implementation applies sanitize_text_field() to every
	 * string value. Override for custom sanitisation logic.
	 *
	 * @param array<string, mixed> $new_instance Settings submitted via the form.
	 * @param array<string, mixed> $old_instance Previously saved settings.
	 * @return array<string, mixed>
	 */
	protected function sanitize( array $new_instance, array $old_instance ): array {
		$sanitized = array();

		foreach ( $new_instance as $key => $value ) {
			$sanitized[ $key ] = is_string( $value )
				? sanitize_text_field( $value )
				: $value;
		}

		return $sanitized;
	}

	// ---------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------

	/**
	 * Register this widget class with WordPress.
	 *
	 * Hooks register_widget() onto 'widgets_init'. Call from your plugin
	 * bootstrap or service provider boot():
	 *
	 *     MyWidget::register();
	 *
	 * @return void
	 */
	public static function register(): void {
		$class = static::class;

		add_action(
			'widgets_init',
			static function () use ( $class ) {
				register_widget( $class );
			}
		);
	}
}
