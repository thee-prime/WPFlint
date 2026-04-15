<?php
/**
 * Fluent builder for a JavaScript asset.
 *
 * @package WPFlint\Assets
 */

declare(strict_types=1);

namespace WPFlint\Assets;

/**
 * Builds and enqueues a JavaScript file via wp_enqueue_script().
 *
 * Usage:
 *
 *     Script::make( 'my-plugin', plugin_dir_url( __FILE__ ) . 'assets/js/app.js' )
 *         ->deps( [ 'jquery' ] )
 *         ->version( '1.2.0' )
 *         ->footer()
 *         ->localize( 'MyPlugin', [ 'ajax_url' => admin_url( 'admin-ajax.php' ) ] )
 *         ->only_on( 'is_admin' )
 *         ->enqueue();
 */
class Script extends Asset {

	/**
	 * Whether to enqueue the script in the footer.
	 *
	 * @var bool
	 */
	protected bool $in_footer = false;

	/**
	 * Object name for wp_localize_script().
	 *
	 * @var string
	 */
	protected string $localize_object = '';

	/**
	 * Data array for wp_localize_script().
	 *
	 * @var array<string, mixed>
	 */
	protected array $localize_data = array();

	/**
	 * Static factory.
	 *
	 * @param string $handle Unique script handle.
	 * @param string $src    Full URL to the JS file.
	 * @return static
	 */
	public static function make( string $handle, string $src ): self {
		return new static( $handle, $src );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Load the script in the footer instead of the <head>.
	 *
	 * @param bool $in_footer True to load in footer (default true).
	 * @return $this
	 */
	public function footer( bool $in_footer = true ): self {
		$this->in_footer = $in_footer;
		return $this;
	}

	/**
	 * Pass data to the script via wp_localize_script().
	 *
	 * @param string               $object_name JS variable name exposed to the page.
	 * @param array<string, mixed> $data         Data to expose.
	 * @return $this
	 */
	public function localize( string $object_name, array $data ): self {
		$this->localize_object = $object_name;
		$this->localize_data   = $data;
		return $this;
	}

	// ---------------------------------------------------------------
	// Registration & enqueue
	// ---------------------------------------------------------------

	/**
	 * Enqueue the script (skipped if should_enqueue() returns false).
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		wp_enqueue_script(
			$this->handle,
			$this->src,
			$this->deps,
			$this->wp_version(),
			$this->in_footer
		);

		if ( '' !== $this->localize_object ) {
			wp_localize_script( $this->handle, $this->localize_object, $this->localize_data );
		}
	}

	/**
	 * Register the script without enqueuing it.
	 *
	 * @return void
	 */
	public function register_asset(): void {
		wp_register_script(
			$this->handle,
			$this->src,
			$this->deps,
			$this->wp_version(),
			$this->in_footer
		);
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Whether the script loads in the footer.
	 *
	 * @return bool
	 */
	public function is_in_footer(): bool {
		return $this->in_footer;
	}

	/**
	 * Get the localize object name.
	 *
	 * @return string
	 */
	public function get_localize_object(): string {
		return $this->localize_object;
	}

	/**
	 * Get the localize data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_localize_data(): array {
		return $this->localize_data;
	}
}
