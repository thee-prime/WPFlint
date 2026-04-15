<?php
/**
 * Fluent builder for a CSS stylesheet asset.
 *
 * @package WPFlint\Assets
 */

declare(strict_types=1);

namespace WPFlint\Assets;

/**
 * Builds and enqueues a CSS file via wp_enqueue_style().
 *
 * Usage:
 *
 *     Style::make( 'my-plugin', plugin_dir_url( __FILE__ ) . 'assets/css/app.css' )
 *         ->deps( [ 'wp-components' ] )
 *         ->version( '1.0' )
 *         ->media( 'screen' )
 *         ->only_on( 'is_admin' )
 *         ->enqueue();
 */
class Style extends Asset {

	/**
	 * CSS media attribute.
	 *
	 * @var string
	 */
	protected string $media = 'all';

	/**
	 * Static factory.
	 *
	 * @param string $handle Unique style handle.
	 * @param string $src    Full URL to the CSS file.
	 * @return static
	 */
	public static function make( string $handle, string $src ): self {
		return new static( $handle, $src );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Set the CSS media attribute.
	 *
	 * @param string $media CSS media query, e.g. 'screen', 'print', 'all'.
	 * @return $this
	 */
	public function media( string $media ): self {
		$this->media = $media;
		return $this;
	}

	// ---------------------------------------------------------------
	// Registration & enqueue
	// ---------------------------------------------------------------

	/**
	 * Enqueue the stylesheet (skipped if should_enqueue() returns false).
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		wp_enqueue_style(
			$this->handle,
			$this->src,
			$this->deps,
			$this->wp_version(),
			$this->media
		);
	}

	/**
	 * Register the stylesheet without enqueuing it.
	 *
	 * @return void
	 */
	public function register_asset(): void {
		wp_register_style(
			$this->handle,
			$this->src,
			$this->deps,
			$this->wp_version(),
			$this->media
		);
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the CSS media attribute.
	 *
	 * @return string
	 */
	public function get_media(): string {
		return $this->media;
	}
}
