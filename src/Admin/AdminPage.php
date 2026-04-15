<?php
/**
 * Fluent builder for WordPress admin menu pages and subpages.
 *
 * @package WPFlint\Admin
 */

declare(strict_types=1);

namespace WPFlint\Admin;

/**
 * Builds and registers a top-level admin menu page (and optional subpages).
 *
 * Usage:
 *
 *     AdminPage::make( 'My Plugin', 'my-plugin' )
 *         ->capability( 'manage_options' )
 *         ->icon( 'dashicons-admin-tools' )
 *         ->position( 80 )
 *         ->render( function() { include plugin_dir_path( __FILE__ ) . 'views/main.php'; } )
 *         ->submenu( 'Settings', 'my-plugin-settings', function() {
 *             include plugin_dir_path( __FILE__ ) . 'views/settings.php';
 *         } )
 *         ->register();
 */
class AdminPage {

	/**
	 * The text shown in the browser title bar.
	 *
	 * @var string
	 */
	protected string $page_title;

	/**
	 * The text shown in the admin menu.
	 *
	 * @var string
	 */
	protected string $menu_title;

	/**
	 * Required capability to access this page.
	 *
	 * @var string
	 */
	protected string $capability = 'manage_options';

	/**
	 * Unique menu slug for this page.
	 *
	 * @var string
	 */
	protected string $menu_slug;

	/**
	 * Callback that renders the page content.
	 *
	 * @var callable|null
	 */
	protected $render_callback = null;

	/**
	 * Dashicon class or image URL for the menu icon.
	 *
	 * @var string
	 */
	protected string $icon = '';

	/**
	 * Position in the admin menu.
	 *
	 * @var int|null
	 */
	protected ?int $position = null;

	/**
	 * Parent menu slug (set when this is a submenu item).
	 *
	 * @var string|null
	 */
	protected ?string $parent_slug = null;

	/**
	 * Child submenu pages.
	 *
	 * @var array<int, AdminPage>
	 */
	protected array $subpages = array();

	/**
	 * Create an AdminPage builder.
	 *
	 * @param string $page_title Page title shown in <title> and at the top of the page.
	 * @param string $menu_slug  Unique slug used in the menu URL.
	 */
	public function __construct( string $page_title, string $menu_slug ) {
		$this->page_title = $page_title;
		$this->menu_title = $page_title;
		$this->menu_slug  = $menu_slug;
	}

	/**
	 * Static factory.
	 *
	 * @param string $page_title Page title.
	 * @param string $menu_slug  Menu slug.
	 * @return static
	 */
	public static function make( string $page_title, string $menu_slug ): self {
		return new static( $page_title, $menu_slug );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Override the label shown in the admin menu (defaults to page_title).
	 *
	 * @param string $menu_title Short label for the menu item.
	 * @return $this
	 */
	public function title( string $menu_title ): self {
		$this->menu_title = $menu_title;
		return $this;
	}

	/**
	 * Set the capability required to view this page.
	 *
	 * @param string $capability WordPress capability slug.
	 * @return $this
	 */
	public function capability( string $capability ): self {
		$this->capability = $capability;
		return $this;
	}

	/**
	 * Set the menu icon (dashicon class or image URL).
	 *
	 * @param string $icon Dashicon class (e.g. 'dashicons-admin-tools') or full image URL.
	 * @return $this
	 */
	public function icon( string $icon ): self {
		$this->icon = $icon;
		return $this;
	}

	/**
	 * Set the admin menu position.
	 *
	 * @param int $position Integer menu position; lower numbers appear higher.
	 * @return $this
	 */
	public function position( int $position ): self {
		$this->position = $position;
		return $this;
	}

	/**
	 * Set the callable that renders the page body.
	 *
	 * @param callable $callback Render callback; receives no arguments.
	 * @return $this
	 */
	public function render( callable $callback ): self {
		$this->render_callback = $callback;
		return $this;
	}

	/**
	 * Add a submenu page under this top-level page.
	 *
	 * @param string        $page_title Page title for the subpage.
	 * @param string        $menu_slug  Unique menu slug for the subpage.
	 * @param callable|null $render     Optional render callback.
	 * @return $this
	 */
	public function submenu( string $page_title, string $menu_slug, callable $render = null ): self {
		$sub = new static( $page_title, $menu_slug );

		$sub->capability  = $this->capability;
		$sub->parent_slug = $this->menu_slug;

		if ( null !== $render ) {
			$sub->render_callback = $render;
		}

		$this->subpages[] = $sub;

		return $this;
	}

	// ---------------------------------------------------------------
	// Registration
	// ---------------------------------------------------------------

	/**
	 * Register this page as a top-level admin menu item, then register any subpages.
	 *
	 * Must be called inside or after the 'admin_menu' hook.
	 *
	 * @return void
	 */
	public function register(): void {
		$callback = $this->render_callback;

		if ( null === $callback ) {
			$callback = static function () {};
		}

		add_menu_page(
			$this->page_title,
			$this->menu_title,
			$this->capability,
			$this->menu_slug,
			$callback,
			$this->icon,
			$this->position
		);

		foreach ( $this->subpages as $sub ) {
			$sub->register_as_submenu( $this->menu_slug );
		}
	}

	/**
	 * Register this page as a submenu of the given parent slug.
	 *
	 * @param string $parent_slug The parent menu slug.
	 * @return void
	 */
	public function register_as_submenu( string $parent_slug ): void {
		$this->parent_slug = $parent_slug;

		$callback = $this->render_callback;

		if ( null === $callback ) {
			$callback = static function () {};
		}

		add_submenu_page(
			$parent_slug,
			$this->page_title,
			$this->menu_title,
			$this->capability,
			$this->menu_slug,
			$callback
		);
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the page title.
	 *
	 * @return string
	 */
	public function get_page_title(): string {
		return $this->page_title;
	}

	/**
	 * Get the menu label.
	 *
	 * @return string
	 */
	public function get_menu_title(): string {
		return $this->menu_title;
	}

	/**
	 * Get the menu slug.
	 *
	 * @return string
	 */
	public function get_menu_slug(): string {
		return $this->menu_slug;
	}

	/**
	 * Get the required capability.
	 *
	 * @return string
	 */
	public function get_capability(): string {
		return $this->capability;
	}

	/**
	 * Get the menu icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return $this->icon;
	}

	/**
	 * Get the menu position.
	 *
	 * @return int|null
	 */
	public function get_position(): ?int {
		return $this->position;
	}

	/**
	 * Get the parent slug (null for top-level pages).
	 *
	 * @return string|null
	 */
	public function get_parent_slug(): ?string {
		return $this->parent_slug;
	}

	/**
	 * Get registered submenu pages.
	 *
	 * @return array<int, AdminPage>
	 */
	public function get_subpages(): array {
		return $this->subpages;
	}
}
