<?php
/**
 * Admin notice builder with flash and persistent storage.
 *
 * @package WPFlint\Admin
 */

declare(strict_types=1);

namespace WPFlint\Admin;

/**
 * Builds and displays WordPress admin notices.
 *
 * Supports:
 *   - Inline display (render a notice now)
 *   - Flash notices (stored in a transient, shown once on the next page load)
 *   - Persistent notices (stored in an option, shown until dismissed)
 *
 * Usage:
 *
 *     // Flash — shown once on next admin page load:
 *     Notice::success( 'Settings saved.' )->dismissible()->flash();
 *
 *     // Persistent — shown until Notice::dismiss() is called:
 *     Notice::error( 'API key is invalid.' )->persistent( 'my_plugin_api_error' );
 *
 *     // Inline — render directly inside your own hook:
 *     add_action( 'admin_notices', function() {
 *         echo Notice::warning( 'Please configure the plugin.' )->render();
 *     } );
 *
 *     // Dismiss a persistent notice:
 *     Notice::dismiss( 'my_plugin_api_error' );
 *
 * Display hooks must be wired up:
 *     add_action( 'admin_notices', [ Notice::class, 'display_flash' ] );
 *     // persistent notices register their own hook via persistent().
 */
class Notice {

	/**
	 * Notice type constants.
	 */
	const SUCCESS = 'success';
	const ERROR   = 'error';
	const WARNING = 'warning';
	const INFO    = 'info';

	/**
	 * Transient key prefix for flash notices (suffixed with user ID).
	 */
	const FLASH_TRANSIENT_PREFIX = 'wpflint_flash_';

	/**
	 * Option key prefix for persistent notices.
	 */
	const PERSISTENT_OPTION_PREFIX = 'wpflint_notice_';

	/**
	 * Notice type (success|error|warning|info).
	 *
	 * @var string
	 */
	protected string $type;

	/**
	 * Notice message (may contain safe HTML).
	 *
	 * @var string
	 */
	protected string $message;

	/**
	 * Whether to render the is-dismissible CSS class.
	 *
	 * @var bool
	 */
	protected bool $dismissible = false;

	/**
	 * Create a Notice.
	 *
	 * @param string $type    One of the TYPE_* constants.
	 * @param string $message Notice message.
	 */
	public function __construct( string $type, string $message ) {
		$this->type    = $type;
		$this->message = $message;
	}

	// ---------------------------------------------------------------
	// Static factories
	// ---------------------------------------------------------------

	/**
	 * Create a success notice.
	 *
	 * @param string $message Notice message.
	 * @return static
	 */
	public static function success( string $message ): self {
		return new static( self::SUCCESS, $message );
	}

	/**
	 * Create an error notice.
	 *
	 * @param string $message Notice message.
	 * @return static
	 */
	public static function error( string $message ): self {
		return new static( self::ERROR, $message );
	}

	/**
	 * Create a warning notice.
	 *
	 * @param string $message Notice message.
	 * @return static
	 */
	public static function warning( string $message ): self {
		return new static( self::WARNING, $message );
	}

	/**
	 * Create an info notice.
	 *
	 * @param string $message Notice message.
	 * @return static
	 */
	public static function info( string $message ): self {
		return new static( self::INFO, $message );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Mark the notice as dismissible (adds the is-dismissible CSS class).
	 *
	 * @param bool $dismissible True to make dismissible.
	 * @return $this
	 */
	public function dismissible( bool $dismissible = true ): self {
		$this->dismissible = $dismissible;
		return $this;
	}

	// ---------------------------------------------------------------
	// Display
	// ---------------------------------------------------------------

	/**
	 * Build and return the notice HTML string.
	 *
	 * @return string
	 */
	public function render(): string {
		$class = 'notice notice-' . $this->type;

		if ( $this->dismissible ) {
			$class .= ' is-dismissible';
		}

		return sprintf(
			'<div class="%s"><p>%s</p></div>',
			esc_attr( $class ),
			wp_kses_post( $this->message )
		);
	}

	// ---------------------------------------------------------------
	// Flash notices
	// ---------------------------------------------------------------

	/**
	 * Store this notice as a flash message and register the display hook.
	 *
	 * The notice is stored in a short-lived transient keyed to the current user.
	 * It is displayed once on the next admin page load and then deleted.
	 *
	 * @return void
	 */
	public function flash(): void {
		$user_id = get_current_user_id();
		$key     = self::FLASH_TRANSIENT_PREFIX . $user_id;
		$notices = get_transient( $key );

		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$notices[] = array(
			'type'        => $this->type,
			'message'     => $this->message,
			'dismissible' => $this->dismissible,
		);

		set_transient( $key, $notices, 5 * MINUTE_IN_SECONDS );

		add_action( 'admin_notices', array( static::class, 'display_flash' ) );
	}

	/**
	 * Display and clear all flash notices for the current user.
	 *
	 * Hook this to 'admin_notices', or call flash() which does it automatically.
	 *
	 * @return void
	 */
	public static function display_flash(): void {
		$user_id = get_current_user_id();
		$key     = self::FLASH_TRANSIENT_PREFIX . $user_id;
		$notices = get_transient( $key );

		if ( ! is_array( $notices ) ) {
			return;
		}

		foreach ( $notices as $data ) {
			$notice = new static( $data['type'], $data['message'] );

			if ( ! empty( $data['dismissible'] ) ) {
				$notice->dismissible();
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() uses esc_attr/wp_kses_post internally.
			echo $notice->render();
		}

		delete_transient( $key );
	}

	// ---------------------------------------------------------------
	// Persistent notices
	// ---------------------------------------------------------------

	/**
	 * Store this notice persistently and register the display hook.
	 *
	 * The notice is stored in an option and displayed on every admin page load
	 * until Notice::dismiss( $key ) is called.
	 *
	 * @param string $key Unique key for this notice (used to dismiss it later).
	 * @return void
	 */
	public function persistent( string $key ): void {
		update_option(
			self::PERSISTENT_OPTION_PREFIX . $key,
			array(
				'type'        => $this->type,
				'message'     => $this->message,
				'dismissible' => $this->dismissible,
			)
		);

		add_action(
			'admin_notices',
			static function () use ( $key ) {
				static::display_persistent( $key );
			}
		);
	}

	/**
	 * Display a persistent notice by key.
	 *
	 * @param string $key Notice key.
	 * @return void
	 */
	public static function display_persistent( string $key ): void {
		$data = get_option( self::PERSISTENT_OPTION_PREFIX . $key );

		if ( ! is_array( $data ) ) {
			return;
		}

		$notice = new static( $data['type'], $data['message'] );

		if ( ! empty( $data['dismissible'] ) ) {
			$notice->dismissible();
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() uses esc_attr/wp_kses_post internally.
		echo $notice->render();
	}

	/**
	 * Delete a persistent notice so it no longer displays.
	 *
	 * @param string $key The key used when persistent() was called.
	 * @return void
	 */
	public static function dismiss( string $key ): void {
		delete_option( self::PERSISTENT_OPTION_PREFIX . $key );
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the notice type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get the message.
	 *
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Whether the notice is dismissible.
	 *
	 * @return bool
	 */
	public function is_dismissible(): bool {
		return $this->dismissible;
	}
}
