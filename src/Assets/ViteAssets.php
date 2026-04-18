<?php
/**
 * Enqueues Vite-built assets in WordPress.
 *
 * Detects dev vs. production automatically:
 *   - Production: reads dist/.vite/manifest.json for hashed filenames.
 *   - Dev:        loads from the Vite dev server (localhost:5173) with HMR.
 *
 * @package WPFlint\Assets
 */

declare(strict_types=1);

namespace WPFlint\Assets;

/**
 * Vite asset integration for WordPress plugins.
 *
 * Usage (in a ServiceProvider boot()):
 *
 *     $vite = $this->app->make( ViteAssets::class );
 *     add_action( 'admin_enqueue_scripts', function () use ( $vite ) {
 *         $vite->enqueue( 'my-plugin-app', 'resources/js/app.jsx' );
 *         $vite->register_css_dir( 'my-plugin-' );
 *         wp_localize_script( 'my-plugin-app', 'wpData', apply_filters( 'my_plugin_script_data', [] ) );
 *     } );
 */
class ViteAssets {

	/**
	 * Absolute path to the plugin root directory.
	 *
	 * @var string
	 */
	protected string $plugin_dir;

	/**
	 * Public URL to the plugin root directory (trailing slash).
	 *
	 * @var string
	 */
	protected string $plugin_url;

	/**
	 * Subdirectory for Vite build output (relative to plugin root).
	 *
	 * @var string
	 */
	protected string $dist_dir = 'dist';

	/**
	 * Subdirectory for source files (relative to plugin root).
	 *
	 * @var string
	 */
	protected string $resources_dir = 'resources';

	/**
	 * Vite dev server port.
	 *
	 * @var int
	 */
	protected int $dev_port = 5173;

	/**
	 * Cached manifest data; null until first read.
	 *
	 * @var array<string,mixed>|null
	 */
	protected ?array $manifest = null;

	/**
	 * Whether the Vite client script has been injected for this request.
	 *
	 * @var bool
	 */
	protected bool $dev_client_injected = false;

	/**
	 * Handles that must be rendered as type="module".
	 *
	 * Static so the single script_loader_tag filter covers all instances
	 * (and, after Strauss, each plugin namespace has its own copy).
	 *
	 * @var array<string>
	 */
	protected static array $module_handles = array();

	/**
	 * @param string $plugin_dir Absolute path to the plugin root.
	 * @param string $plugin_url Public URL to the plugin root (trailing slash).
	 * @param int    $dev_port   Vite dev server port (default 5173).
	 */
	public function __construct( string $plugin_dir, string $plugin_url, int $dev_port = 5173 ) {
		$this->plugin_dir = rtrim( $plugin_dir, '/\\' );
		$this->plugin_url = rtrim( $plugin_url, '/' );
		$this->dev_port   = $dev_port;
	}

	// ---------------------------------------------------------------
	// Public API
	// ---------------------------------------------------------------

	/**
	 * Enqueue a Vite entry point (JS + any extracted CSS).
	 *
	 * @param string   $handle      WordPress script handle.
	 * @param string   $entry       Entry path relative to plugin root, e.g. 'resources/js/app.jsx'.
	 * @param string[] $deps        Script dependencies.
	 * @param string[] $style_deps  Style dependencies (prod only, for extracted CSS).
	 * @return void
	 */
	public function enqueue( string $handle, string $entry, array $deps = array(), array $style_deps = array() ): void {
		if ( $this->is_dev() ) {
			$this->inject_dev_client();
			$this->register_module( $handle, $this->dev_url( $entry ), array_merge( array( 'wpflint-vite-client' ), $deps ) );
			wp_enqueue_script( $handle );
		} else {
			$this->enqueue_from_manifest( $handle, $entry, $deps, $style_deps );
		}
	}

	/**
	 * Register (not enqueue) every *.css file found in resources/css/.
	 *
	 * Each file is registered with handle: "{$handle_prefix}{filename-without-ext}".
	 * Call wp_enqueue_style() in your page callback to load specific styles.
	 *
	 * @param string $handle_prefix Prefix for generated handles, e.g. 'my-plugin-'.
	 * @return void
	 */
	public function register_css_dir( string $handle_prefix = '' ): void {
		$dir = $this->plugin_dir . '/' . $this->resources_dir . '/css';

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( $dir . '/*.css' );
		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			$name   = basename( $file, '.css' );
			$entry  = $this->resources_dir . '/css/' . $name . '.css';
			$handle = $handle_prefix . $name;

			$this->register_style( $handle, $entry );
		}
	}

	/**
	 * True when the Vite manifest is absent (dev server mode).
	 *
	 * @return bool
	 */
	public function is_dev(): bool {
		return ! file_exists( $this->manifest_path() );
	}

	// ---------------------------------------------------------------
	// Internal helpers
	// ---------------------------------------------------------------

	/**
	 * Register a single CSS file — reads manifest in prod, uses dev server in dev.
	 *
	 * @param string $handle WordPress style handle.
	 * @param string $entry  Entry path relative to plugin root.
	 * @return void
	 */
	protected function register_style( string $handle, string $entry ): void {
		if ( $this->is_dev() ) {
			wp_register_style( $handle, $this->dev_url( $entry ), array(), null );
			return;
		}

		$manifest = $this->get_manifest();

		if ( isset( $manifest[ $entry ]['file'] ) ) {
			wp_register_style(
				$handle,
				$this->plugin_url . '/' . $this->dist_dir . '/' . $manifest[ $entry ]['file'],
				array(),
				null
			);
		}
	}

	/**
	 * Inject the Vite HMR client script (only once per request).
	 *
	 * @return void
	 */
	protected function inject_dev_client(): void {
		if ( $this->dev_client_injected ) {
			return;
		}
		$this->dev_client_injected = true;

		$this->register_module( 'wpflint-vite-client', $this->dev_url( '@vite/client' ), array() );
		wp_enqueue_script( 'wpflint-vite-client' );
	}

	/**
	 * Register a script that must be delivered as type="module".
	 *
	 * @param string   $handle Script handle.
	 * @param string   $src    Script URL.
	 * @param string[] $deps   Dependencies.
	 * @return void
	 */
	protected function register_module( string $handle, string $src, array $deps ): void {
		wp_register_script( $handle, $src, $deps, null, true );

		if ( in_array( $handle, self::$module_handles, true ) ) {
			return;
		}

		self::$module_handles[] = $handle;

		// Add the script_loader_tag filter exactly once per class (namespace).
		static $filter_added = false;

		if ( ! $filter_added ) {
			$filter_added = true;
			$class        = static::class;

			add_filter(
				'script_loader_tag',
				// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing -- closure body
				static function ( string $tag, string $handle ) use ( $class ): string {
					if ( in_array( $handle, $class::$module_handles, true ) ) {
						return str_replace( ' src=', ' type="module" crossorigin src=', $tag );
					}
					return $tag;
				},
				10,
				2
			);
		}
	}

	/**
	 * Enqueue a manifest entry (JS + any CSS references it extracted).
	 *
	 * @param string   $handle     Script handle.
	 * @param string   $entry      Entry path relative to plugin root.
	 * @param string[] $deps       Script dependencies.
	 * @param string[] $style_deps Style dependencies for extracted CSS.
	 * @return void
	 */
	protected function enqueue_from_manifest( string $handle, string $entry, array $deps, array $style_deps ): void {
		$manifest = $this->get_manifest();

		if ( ! isset( $manifest[ $entry ] ) ) {
			return;
		}

		$asset = $manifest[ $entry ];

		$this->register_module(
			$handle,
			$this->plugin_url . '/' . $this->dist_dir . '/' . $asset['file'],
			$deps
		);
		wp_enqueue_script( $handle );

		// Enqueue CSS that Vite extracted from this entry (e.g. from CSS imports).
		foreach ( $asset['css'] ?? array() as $css_file ) {
			$css_handle = $handle . '-' . basename( (string) $css_file, '.css' );
			wp_enqueue_style(
				$css_handle,
				$this->plugin_url . '/' . $this->dist_dir . '/' . $css_file,
				$style_deps,
				null
			);
		}
	}

	/**
	 * Return the parsed manifest, caching after first read.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_manifest(): array {
		if ( null !== $this->manifest ) {
			return $this->manifest;
		}

		$path = $this->manifest_path();

		if ( ! file_exists( $path ) ) {
			return $this->manifest = array();
		}

		$json           = (string) file_get_contents( $path );
		$this->manifest = (array) json_decode( $json, true );

		return $this->manifest;
	}

	/**
	 * Absolute path to the Vite manifest file.
	 *
	 * @return string
	 */
	protected function manifest_path(): string {
		return $this->plugin_dir . '/' . $this->dist_dir . '/.vite/manifest.json';
	}

	/**
	 * Build a URL pointing at the Vite dev server.
	 *
	 * @param string $path Path on the dev server (e.g. 'resources/js/app.jsx').
	 * @return string
	 */
	protected function dev_url( string $path = '' ): string {
		return 'http://localhost:' . $this->dev_port . '/' . ltrim( $path, '/' );
	}
}
