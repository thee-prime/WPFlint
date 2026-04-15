<?php
/**
 * Manages a collection of Script and Style assets.
 *
 * @package WPFlint\Assets
 */

declare(strict_types=1);

namespace WPFlint\Assets;

/**
 * Collects Script and Style instances and enqueues or registers them in bulk.
 *
 * Usage:
 *
 *     $assets = new AssetManager();
 *
 *     $assets->script( 'my-plugin', plugin_dir_url( __FILE__ ) . 'assets/js/app.js' )
 *            ->deps( [ 'jquery' ] )
 *            ->footer();
 *
 *     $assets->style( 'my-plugin', plugin_dir_url( __FILE__ ) . 'assets/css/app.css' )
 *            ->version( '1.0' );
 *
 *     add_action( 'wp_enqueue_scripts', [ $assets, 'enqueue' ] );
 *
 * Or add pre-built instances:
 *
 *     $assets->add(
 *         Script::make( 'my-admin', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js' )
 *             ->only_on( 'is_admin' )
 *     );
 */
class AssetManager {

	/**
	 * All registered assets.
	 *
	 * @var array<int, Asset>
	 */
	protected array $assets = array();

	// ---------------------------------------------------------------
	// Fluent builders
	// ---------------------------------------------------------------

	/**
	 * Add a pre-built Asset instance to the manager.
	 *
	 * @param Asset $asset Any Script or Style instance.
	 * @return $this
	 */
	public function add( Asset $asset ): self {
		$this->assets[] = $asset;
		return $this;
	}

	/**
	 * Create and register a new Script.
	 *
	 * Returns the Script so you can chain further setters on it.
	 *
	 * @param string $handle Unique script handle.
	 * @param string $src    Full URL to the JS file.
	 * @return Script
	 */
	public function script( string $handle, string $src ): Script {
		$script         = Script::make( $handle, $src );
		$this->assets[] = $script;
		return $script;
	}

	/**
	 * Create and register a new Style.
	 *
	 * Returns the Style so you can chain further setters on it.
	 *
	 * @param string $handle Unique style handle.
	 * @param string $src    Full URL to the CSS file.
	 * @return Style
	 */
	public function style( string $handle, string $src ): Style {
		$style          = Style::make( $handle, $src );
		$this->assets[] = $style;
		return $style;
	}

	// ---------------------------------------------------------------
	// Bulk operations
	// ---------------------------------------------------------------

	/**
	 * Enqueue all assets whose condition is met.
	 *
	 * Hook this to 'wp_enqueue_scripts' or 'admin_enqueue_scripts'.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		foreach ( $this->assets as $asset ) {
			$asset->enqueue();
		}
	}

	/**
	 * Register all assets without enqueuing them.
	 *
	 * Hook this to 'wp_enqueue_scripts' / 'admin_enqueue_scripts' if you want
	 * to register early and enqueue selectively later.
	 *
	 * @return void
	 */
	public function register_all(): void {
		foreach ( $this->assets as $asset ) {
			$asset->register_asset();
		}
	}

	// ---------------------------------------------------------------
	// Introspection
	// ---------------------------------------------------------------

	/**
	 * Get all registered asset instances.
	 *
	 * @return array<int, Asset>
	 */
	public function get_assets(): array {
		return $this->assets;
	}

	/**
	 * Count of all registered assets.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->assets );
	}
}
