<?php
/**
 * Plugin lifecycle hook registration.
 *
 * @package WPFlint\Lifecycle
 */

declare(strict_types=1);

namespace WPFlint\Lifecycle;

/**
 * Fluent wrapper around WordPress activation, deactivation, and uninstall hooks.
 *
 * Usage:
 *
 *     Lifecycle::for( __FILE__ )
 *         ->on_activate( function() { Migrator::run(); } )
 *         ->on_deactivate( function() { wp_clear_scheduled_hook( 'my_cron' ); } )
 *         ->on_uninstall( MyUninstaller::class )
 *         ->register();
 *
 * The uninstall class must expose a public static uninstall() method, because
 * register_uninstall_hook() runs in a separate PHP process where closures and
 * anonymous classes are unavailable.
 */
class Lifecycle {

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	protected string $plugin_file;

	/**
	 * Activation callbacks.
	 *
	 * @var array<int, callable>
	 */
	protected array $activate = array();

	/**
	 * Deactivation callbacks.
	 *
	 * @var array<int, callable>
	 */
	protected array $deactivate = array();

	/**
	 * Fully-qualified class names to call ::uninstall() on.
	 *
	 * @var array<int, string>
	 */
	protected array $uninstall_classes = array();

	/**
	 * Create a Lifecycle instance.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Static factory.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @return static
	 */
	public static function for( string $plugin_file ): self {
		return new static( $plugin_file );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Register a callback to run on plugin activation.
	 *
	 * Multiple calls accumulate callbacks (all are run in order).
	 *
	 * @param callable $callback Activation callback.
	 * @return $this
	 */
	public function on_activate( callable $callback ): self {
		$this->activate[] = $callback;
		return $this;
	}

	/**
	 * Register a callback to run on plugin deactivation.
	 *
	 * Multiple calls accumulate callbacks (all are run in order).
	 *
	 * @param callable $callback Deactivation callback.
	 * @return $this
	 */
	public function on_deactivate( callable $callback ): self {
		$this->deactivate[] = $callback;
		return $this;
	}

	/**
	 * Register a class whose static uninstall() method runs on plugin deletion.
	 *
	 * The class must define: public static function uninstall(): void
	 *
	 * Because uninstall hooks run in a fresh PHP process, only named classes
	 * (not closures or anonymous classes) are supported.
	 *
	 * @param string $class_name Fully-qualified class name.
	 * @return $this
	 */
	public function on_uninstall( string $class_name ): self {
		$this->uninstall_classes[] = $class_name;
		return $this;
	}

	// ---------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------

	/**
	 * Wire up all registered hooks.
	 *
	 * Call this once from your main plugin file, outside of any hook callback.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! empty( $this->activate ) ) {
			$activate = $this->activate;

			register_activation_hook(
				$this->plugin_file,
				static function () use ( $activate ) {
					foreach ( $activate as $cb ) {
						$cb();
					}
				}
			);
		}

		if ( ! empty( $this->deactivate ) ) {
			$deactivate = $this->deactivate;

			register_deactivation_hook(
				$this->plugin_file,
				static function () use ( $deactivate ) {
					foreach ( $deactivate as $cb ) {
						$cb();
					}
				}
			);
		}

		foreach ( $this->uninstall_classes as $class ) {
			register_uninstall_hook( $this->plugin_file, array( $class, 'uninstall' ) );
		}
	}

	// ---------------------------------------------------------------
	// Introspection
	// ---------------------------------------------------------------

	/**
	 * Get the plugin file path.
	 *
	 * @return string
	 */
	public function get_plugin_file(): string {
		return $this->plugin_file;
	}

	/**
	 * Get registered activation callbacks.
	 *
	 * @return array<int, callable>
	 */
	public function get_activate_callbacks(): array {
		return $this->activate;
	}

	/**
	 * Get registered deactivation callbacks.
	 *
	 * @return array<int, callable>
	 */
	public function get_deactivate_callbacks(): array {
		return $this->deactivate;
	}

	/**
	 * Get registered uninstall class names.
	 *
	 * @return array<int, string>
	 */
	public function get_uninstall_classes(): array {
		return $this->uninstall_classes;
	}
}
