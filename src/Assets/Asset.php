<?php
/**
 * Abstract base for script and style assets.
 *
 * @package WPFlint\Assets
 */

declare(strict_types=1);

namespace WPFlint\Assets;

/**
 * Base class for Script and Style builders.
 *
 * Provides shared fluent setters for handle, src, deps, version, and
 * conditional enqueue logic.
 */
abstract class Asset {

	/**
	 * Registered handle (unique identifier for this asset).
	 *
	 * @var string
	 */
	protected string $handle;

	/**
	 * Full URL to the asset file.
	 *
	 * @var string
	 */
	protected string $src;

	/**
	 * Handles this asset depends on.
	 *
	 * @var array<int, string>
	 */
	protected array $deps = array();

	/**
	 * Asset version string. Empty string means no version query-string.
	 *
	 * @var string
	 */
	protected string $version = '';

	/**
	 * Optional condition; when set, the asset is only enqueued if this returns true.
	 *
	 * @var callable|null
	 */
	protected $condition = null;

	/**
	 * Create an Asset.
	 *
	 * @param string $handle Unique asset handle.
	 * @param string $src    Full URL to the asset file.
	 */
	public function __construct( string $handle, string $src ) {
		$this->handle = $handle;
		$this->src    = $src;
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Set the asset's dependencies.
	 *
	 * @param array<int, string> $deps Array of dependency handles.
	 * @return $this
	 */
	public function deps( array $deps ): self {
		$this->deps = $deps;
		return $this;
	}

	/**
	 * Set the asset version string.
	 *
	 * @param string $version Version string appended as ?ver= to the URL.
	 * @return $this
	 */
	public function version( string $version ): self {
		$this->version = $version;
		return $this;
	}

	/**
	 * Set a condition that must return true for this asset to be enqueued.
	 *
	 * Example:
	 *     ->only_on( 'is_admin' )          // enqueue in admin only
	 *     ->only_on( fn() => is_singular() )
	 *
	 * @param callable $condition Callable; receives no args, returns bool.
	 * @return $this
	 */
	public function only_on( callable $condition ): self {
		$this->condition = $condition;
		return $this;
	}

	// ---------------------------------------------------------------
	// Internals
	// ---------------------------------------------------------------

	/**
	 * Whether this asset should be enqueued based on the condition.
	 *
	 * @return bool
	 */
	public function should_enqueue(): bool {
		if ( null === $this->condition ) {
			return true;
		}

		return (bool) ( $this->condition )();
	}

	/**
	 * Return the version argument for WP enqueue functions.
	 *
	 * Returns false when no version is set (WP then omits the ?ver= string).
	 *
	 * @return string|false
	 */
	protected function wp_version() {
		return '' !== $this->version ? $this->version : false;
	}

	// ---------------------------------------------------------------
	// Abstract interface
	// ---------------------------------------------------------------

	/**
	 * Enqueue the asset via the appropriate WP function.
	 *
	 * Should check should_enqueue() before calling wp_enqueue_*.
	 *
	 * @return void
	 */
	abstract public function enqueue(): void;

	/**
	 * Register the asset without enqueuing it.
	 *
	 * @return void
	 */
	abstract public function register_asset(): void;

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the asset handle.
	 *
	 * @return string
	 */
	public function get_handle(): string {
		return $this->handle;
	}

	/**
	 * Get the asset src URL.
	 *
	 * @return string
	 */
	public function get_src(): string {
		return $this->src;
	}

	/**
	 * Get the dependency handles.
	 *
	 * @return array<int, string>
	 */
	public function get_deps(): array {
		return $this->deps;
	}

	/**
	 * Get the version string (empty string means none set).
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->version;
	}
}
