<?php
/**
 * Lightweight PHP template renderer.
 *
 * @package WPFlint\View
 */

declare(strict_types=1);

namespace WPFlint\View;

/**
 * Renders PHP template files with dot-notation paths and scoped data.
 *
 * Usage:
 *
 *     // Set the base path once (e.g. in your plugin's bootstrap or service provider):
 *     View::set_base_path( plugin_dir_path( __FILE__ ) . 'resources/views' );
 *
 *     // Render a template:
 *     echo View::make( 'admin.settings' )
 *         ->with( [ 'title' => 'Settings', 'options' => $options ] )
 *         ->render();
 *
 *     // Or output directly:
 *     View::make( 'partials.notice' )->with( 'message', 'Saved!' )->output();
 *
 * Template path resolution:
 *     'admin.settings'  →  {base_path}/admin/settings.php
 *     'emails.confirm'  →  {base_path}/emails/confirm.php
 */
class View {

	/**
	 * Default base path shared across all View instances.
	 *
	 * @var string
	 */
	protected static string $default_base_path = '';

	/**
	 * Dot-notation template identifier.
	 *
	 * @var string
	 */
	protected string $template;

	/**
	 * Data exposed to the template.
	 *
	 * @var array<string, mixed>
	 */
	protected array $data = array();

	/**
	 * Per-instance base path override.
	 *
	 * @var string
	 */
	protected string $base_path = '';

	/**
	 * Create a View instance.
	 *
	 * @param string $template Dot-notation template name.
	 */
	public function __construct( string $template ) {
		$this->template = $template;
	}

	// ---------------------------------------------------------------
	// Global configuration
	// ---------------------------------------------------------------

	/**
	 * Set the default base directory for all templates.
	 *
	 * @param string $path Absolute path to the views directory.
	 * @return void
	 */
	public static function set_base_path( string $path ): void {
		static::$default_base_path = rtrim( $path, '/\\' );
	}

	/**
	 * Get the current default base path.
	 *
	 * @return string
	 */
	public static function get_base_path(): string {
		return static::$default_base_path;
	}

	// ---------------------------------------------------------------
	// Factory
	// ---------------------------------------------------------------

	/**
	 * Create a View for the given template.
	 *
	 * @param string $template  Dot-notation template name, e.g. 'admin.settings'.
	 * @param string $base_path Optional base path override for this view only.
	 * @return static
	 */
	public static function make( string $template, string $base_path = '' ): self {
		$view = new static( $template );

		if ( '' !== $base_path ) {
			$view->base_path = rtrim( $base_path, '/\\' );
		}

		return $view;
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Set the base path for this view only (overrides the static default).
	 *
	 * @param string $path Absolute path to the views directory.
	 * @return $this
	 */
	public function from( string $path ): self {
		$this->base_path = rtrim( $path, '/\\' );
		return $this;
	}

	/**
	 * Pass data to the template.
	 *
	 * Accepts either an associative array or a key/value pair:
	 *
	 *     ->with( [ 'title' => 'Settings', 'items' => $items ] )
	 *     ->with( 'title', 'Settings' )
	 *
	 * @param array<string, mixed>|string $key   Associative array of data, or a single key name.
	 * @param mixed                       $value Value when $key is a string.
	 * @return $this
	 */
	public function with( $key, $value = null ): self {
		if ( is_array( $key ) ) {
			$this->data = array_merge( $this->data, $key );
		} else {
			$this->data[ $key ] = $value;
		}

		return $this;
	}

	// ---------------------------------------------------------------
	// Rendering
	// ---------------------------------------------------------------

	/**
	 * Resolve the dot-notation template name to an absolute file path.
	 *
	 * @return string
	 */
	public function get_path(): string {
		$base     = '' !== $this->base_path ? $this->base_path : static::$default_base_path;
		$relative = str_replace( '.', DIRECTORY_SEPARATOR, $this->template ) . '.php';

		if ( '' !== $base ) {
			return $base . DIRECTORY_SEPARATOR . $relative;
		}

		return $relative;
	}

	/**
	 * Render the template and return the output as a string.
	 *
	 * Template data is extracted into local scope. Variable names are taken
	 * from the array keys; existing variables are not overwritten (EXTR_SKIP).
	 *
	 * @return string Rendered template output.
	 */
	public function render(): string {
		$__path = $this->get_path();
		$__data = $this->data;

		ob_start();

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template rendering requires variable extraction into local scope.
		extract( $__data, EXTR_SKIP );

		include $__path;

		$output = ob_get_clean();
		return false !== $output ? $output : '';
	}

	/**
	 * Render and immediately output the template.
	 *
	 * @return void
	 */
	public function output(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is the responsibility of each template file.
		echo $this->render();
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the template identifier.
	 *
	 * @return string
	 */
	public function get_template(): string {
		return $this->template;
	}

	/**
	 * Get the data passed to this view.
	 *
	 * @return array<string, mixed>
	 */
	public function get_data(): array {
		return $this->data;
	}
}
