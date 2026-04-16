<?php
/**
 * Fluent metabox builder.
 *
 * @package WPFlint\Admin
 */

declare(strict_types=1);

namespace WPFlint\Admin;

/**
 * Wraps add_meta_box() and save_post behind a fluent interface.
 *
 * Usage:
 *
 *     $box = MetaBox::make( 'book_details', 'Book Details' )
 *         ->screen( 'book' )
 *         ->context( 'normal' )
 *         ->priority( 'high' );
 *
 *     $box->field( '_isbn',  'ISBN' )->type( 'text' );
 *     $box->field( '_pages', 'Page count' )->type( 'number' );
 *
 *     $box->register();
 *
 * Fields are added via field(), which returns the MetaBoxField instance so
 * you can chain field-specific setters (type, description, options, etc.) on
 * it directly. Call register() on the MetaBox after all fields are configured.
 */
class MetaBox {

	/**
	 * HTML/CSS id for the metabox.
	 *
	 * @var string
	 */
	protected string $id;

	/**
	 * Visible metabox title.
	 *
	 * @var string
	 */
	protected string $title;

	/**
	 * Post type screen(s) where the metabox appears.
	 *
	 * @var string|array<int, string>
	 */
	protected $screen = 'post';

	/**
	 * Metabox placement context: normal|side|advanced.
	 *
	 * @var string
	 */
	protected string $context = 'normal';

	/**
	 * Metabox priority: high|core|default|low.
	 *
	 * @var string
	 */
	protected string $priority = 'default';

	/**
	 * Nonce action derived from the metabox id.
	 *
	 * @var string
	 */
	protected string $nonce_action;

	/**
	 * MetaBoxField instances belonging to this box.
	 *
	 * @var MetaBoxField[]
	 */
	protected array $fields = array();

	/**
	 * Create a MetaBox builder.
	 *
	 * @param string $id    CSS/HTML id for the metabox.
	 * @param string $title Visible metabox title.
	 */
	public function __construct( string $id, string $title ) {
		$this->id           = $id;
		$this->title        = $title;
		$this->nonce_action = $id . '_nonce';
	}

	/**
	 * Static factory.
	 *
	 * @param string $id    CSS/HTML id for the metabox.
	 * @param string $title Visible metabox title.
	 * @return static
	 */
	public static function make( string $id, string $title ): self {
		return new static( $id, $title );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Set the post-type screen(s) where this metabox should appear.
	 *
	 * @param string|array<int, string> $screen Post type slug(s), e.g. 'post', 'book', or ['post','page'].
	 * @return $this
	 */
	public function screen( $screen ): self {
		$this->screen = $screen;
		return $this;
	}

	/**
	 * Set the display context (normal|side|advanced).
	 *
	 * @param string $context Metabox context.
	 * @return $this
	 */
	public function context( string $context ): self {
		$this->context = $context;
		return $this;
	}

	/**
	 * Set the display priority (high|core|default|low).
	 *
	 * @param string $priority Metabox priority.
	 * @return $this
	 */
	public function priority( string $priority ): self {
		$this->priority = $priority;
		return $this;
	}

	/**
	 * Add a field to this metabox and return it for further configuration.
	 *
	 * The returned MetaBoxField supports chaining for type, description,
	 * options, default_value, and sanitize_with.
	 *
	 * @param string $id    Meta key and HTML field name/id.
	 * @param string $label Human-readable field label.
	 * @return MetaBoxField
	 */
	public function field( string $id, string $label ): MetaBoxField {
		$field          = new MetaBoxField( $id, $label );
		$this->fields[] = $field;
		return $field;
	}

	// ---------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------

	/**
	 * Register the metabox and wire up the save_post hook.
	 *
	 * Safe to call inside 'add_meta_boxes' or 'init'.
	 *
	 * @return void
	 */
	public function register(): void {
		$id           = $this->id;
		$title        = $this->title;
		$screen       = $this->screen;
		$context      = $this->context;
		$priority     = $this->priority;
		$nonce_action = $this->nonce_action;
		$fields       = $this->fields;

		add_meta_box(
			$id,
			$title,
			static function ( $post ) use ( $nonce_action, $fields ) {
				wp_nonce_field( $nonce_action, $nonce_action );

				foreach ( $fields as $field ) {
					$field->render( (int) $post->ID );
				}
			},
			$screen,
			$context,
			$priority
		);

		add_action(
			'save_post',
			static function ( $post_id ) use ( $nonce_action, $fields ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				if ( ! isset( $_POST[ $nonce_action ] ) || ! wp_verify_nonce( $_POST[ $nonce_action ], $nonce_action ) ) {
					return;
				}

				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					return;
				}

				if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
					return;
				}

				foreach ( $fields as $field ) {
					$field->save( (int) $post_id );
				}
			}
		);
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the metabox id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Get the metabox title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Get the screen(s) set for this metabox.
	 *
	 * @return string|array<int, string>
	 */
	public function get_screen() {
		return $this->screen;
	}

	/**
	 * Get the context.
	 *
	 * @return string
	 */
	public function get_context(): string {
		return $this->context;
	}

	/**
	 * Get the priority.
	 *
	 * @return string
	 */
	public function get_priority(): string {
		return $this->priority;
	}

	/**
	 * Get the nonce action string.
	 *
	 * @return string
	 */
	public function get_nonce_action(): string {
		return $this->nonce_action;
	}

	/**
	 * Get all registered MetaBoxField instances.
	 *
	 * @return MetaBoxField[]
	 */
	public function get_fields(): array {
		return $this->fields;
	}
}
