<?php
/**
 * Tests for the Admin module (AdminPage builder).
 *
 * @package WPFlint\Tests\Admin
 */

declare(strict_types=1);

namespace WPFlint\Tests\Admin;

use WPFlint\Admin\AdminPage;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\Admin\AdminPage
 */
class AdminTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	// ---------------------------------------------------------------
	// Construction & factory
	// ---------------------------------------------------------------

	public function test_make_returns_instance(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' );

		$this->assertInstanceOf( AdminPage::class, $page );
	}

	public function test_page_title_and_slug_set_on_construction(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' );

		$this->assertSame( 'My Plugin', $page->get_page_title() );
		$this->assertSame( 'my-plugin', $page->get_menu_slug() );
	}

	public function test_menu_title_defaults_to_page_title(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' );

		$this->assertSame( 'My Plugin', $page->get_menu_title() );
	}

	public function test_default_capability_is_manage_options(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' );

		$this->assertSame( 'manage_options', $page->get_capability() );
	}

	public function test_default_icon_is_empty(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' );

		$this->assertSame( '', $page->get_icon() );
	}

	public function test_default_position_is_null(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' );

		$this->assertNull( $page->get_position() );
	}

	public function test_default_parent_slug_is_null(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' );

		$this->assertNull( $page->get_parent_slug() );
	}

	public function test_no_subpages_by_default(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' );

		$this->assertEmpty( $page->get_subpages() );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	public function test_title_sets_menu_title(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' )->title( 'Plugin' );

		$this->assertSame( 'Plugin', $page->get_menu_title() );
	}

	public function test_capability_setter(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' )->capability( 'edit_posts' );

		$this->assertSame( 'edit_posts', $page->get_capability() );
	}

	public function test_icon_setter(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' )->icon( 'dashicons-admin-tools' );

		$this->assertSame( 'dashicons-admin-tools', $page->get_icon() );
	}

	public function test_position_setter(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' )->position( 80 );

		$this->assertSame( 80, $page->get_position() );
	}

	public function test_render_returns_self(): void {
		$page   = AdminPage::make( 'My Plugin', 'my-plugin' );
		$result = $page->render( static function () {} );

		$this->assertSame( $page, $result );
	}

	public function test_capability_returns_self(): void {
		$page   = AdminPage::make( 'My Plugin', 'my-plugin' );
		$result = $page->capability( 'manage_options' );

		$this->assertSame( $page, $result );
	}

	// ---------------------------------------------------------------
	// Submenu
	// ---------------------------------------------------------------

	public function test_submenu_adds_subpage(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' )
			->submenu( 'Settings', 'my-plugin-settings', static function () {} );

		$subpages = $page->get_subpages();

		$this->assertCount( 1, $subpages );
		$this->assertSame( 'Settings', $subpages[0]->get_page_title() );
		$this->assertSame( 'my-plugin-settings', $subpages[0]->get_menu_slug() );
	}

	public function test_multiple_submenus_accumulate(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' )
			->submenu( 'Settings', 'my-plugin-settings' )
			->submenu( 'Logs', 'my-plugin-logs' );

		$this->assertCount( 2, $page->get_subpages() );
	}

	public function test_submenu_inherits_parent_capability(): void {
		$page = AdminPage::make( 'My Plugin', 'my-plugin' )
			->capability( 'edit_posts' )
			->submenu( 'Settings', 'my-plugin-settings' );

		$this->assertSame( 'edit_posts', $page->get_subpages()[0]->get_capability() );
	}

	public function test_submenu_returns_parent_for_chaining(): void {
		$page   = AdminPage::make( 'My Plugin', 'my-plugin' );
		$result = $page->submenu( 'Settings', 'my-plugin-settings' );

		$this->assertSame( $page, $result );
	}

	// ---------------------------------------------------------------
	// register() — add_menu_page
	// ---------------------------------------------------------------

	public function test_register_calls_add_menu_page(): void {
		$called = array();

		WP_Mock::userFunction( 'add_menu_page' )
			->andReturnUsing(
				static function () use ( &$called ) {
					$called[] = func_get_args();
					return 'hook_suffix';
				}
			);

		AdminPage::make( 'My Plugin', 'my-plugin' )
			->capability( 'manage_options' )
			->icon( 'dashicons-admin-tools' )
			->position( 80 )
			->register();

		$this->assertCount( 1, $called );
		$this->assertSame( 'My Plugin', $called[0][0] );   // page_title
		$this->assertSame( 'My Plugin', $called[0][1] );   // menu_title
		$this->assertSame( 'manage_options', $called[0][2] );
		$this->assertSame( 'my-plugin', $called[0][3] );
		$this->assertSame( 'dashicons-admin-tools', $called[0][5] );
		$this->assertSame( 80, $called[0][6] );
	}

	public function test_register_calls_add_submenu_page_for_each_child(): void {
		$menu_calls    = array();
		$submenu_calls = array();

		WP_Mock::userFunction( 'add_menu_page' )
			->andReturnUsing(
				static function () use ( &$menu_calls ) {
					$menu_calls[] = func_get_args();
					return 'hook_suffix';
				}
			);

		WP_Mock::userFunction( 'add_submenu_page' )
			->andReturnUsing(
				static function () use ( &$submenu_calls ) {
					$submenu_calls[] = func_get_args();
					return 'hook_suffix_sub';
				}
			);

		AdminPage::make( 'My Plugin', 'my-plugin' )
			->submenu( 'Settings', 'my-plugin-settings' )
			->submenu( 'Logs', 'my-plugin-logs' )
			->register();

		$this->assertCount( 1, $menu_calls );
		$this->assertCount( 2, $submenu_calls );

		// Parent slug passed as first arg to add_submenu_page.
		$this->assertSame( 'my-plugin', $submenu_calls[0][0] );
		$this->assertSame( 'my-plugin', $submenu_calls[1][0] );
		$this->assertSame( 'Settings', $submenu_calls[0][1] );
		$this->assertSame( 'Logs', $submenu_calls[1][1] );
	}

	// ---------------------------------------------------------------
	// register_as_submenu()
	// ---------------------------------------------------------------

	public function test_register_as_submenu_calls_add_submenu_page(): void {
		$called = array();

		WP_Mock::userFunction( 'add_submenu_page' )
			->andReturnUsing(
				static function () use ( &$called ) {
					$called[] = func_get_args();
					return 'hook_suffix';
				}
			);

		AdminPage::make( 'Settings', 'my-plugin-settings' )
			->register_as_submenu( 'my-plugin' );

		$this->assertCount( 1, $called );
		$this->assertSame( 'my-plugin', $called[0][0] );
		$this->assertSame( 'Settings', $called[0][1] );
		$this->assertSame( 'my-plugin-settings', $called[0][4] );
	}

	public function test_register_as_submenu_sets_parent_slug(): void {
		WP_Mock::userFunction( 'add_submenu_page' )->andReturn( 'hook' );

		$page = AdminPage::make( 'Settings', 'my-plugin-settings' );
		$page->register_as_submenu( 'my-plugin' );

		$this->assertSame( 'my-plugin', $page->get_parent_slug() );
	}
}
