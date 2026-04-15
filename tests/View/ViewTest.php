<?php
/**
 * Tests for the View template renderer.
 *
 * @package WPFlint\Tests\View
 */

declare(strict_types=1);

namespace WPFlint\Tests\View;

use WPFlint\View\View;
use WPFlint\View\ViewServiceProvider;
use WPFlint\Application;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\View\View
 * @covers \WPFlint\View\ViewServiceProvider
 */
class ViewTest extends TestCase {

	/** @var string */
	private string $tmp_dir;

	public function setUp(): void {
		WP_Mock::setUp();
		View::set_base_path( '' );
		$this->tmp_dir = sys_get_temp_dir() . '/wpflint_view_test_' . uniqid();
		mkdir( $this->tmp_dir, 0777, true );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		View::set_base_path( '' );
		Application::clear_instance();
		$this->remove_dir( $this->tmp_dir );
	}

	// ---------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			is_dir( $path ) ? $this->remove_dir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	private function write_template( string $relative_path, string $content ): void {
		$full = $this->tmp_dir . DIRECTORY_SEPARATOR . $relative_path;
		$dir  = dirname( $full );
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
		file_put_contents( $full, $content );
	}

	// ---------------------------------------------------------------
	// Construction & factory
	// ---------------------------------------------------------------

	public function test_make_returns_instance(): void {
		$view = View::make( 'admin.settings' );
		$this->assertInstanceOf( View::class, $view );
	}

	public function test_template_stored(): void {
		$view = View::make( 'admin.settings' );
		$this->assertSame( 'admin.settings', $view->get_template() );
	}

	public function test_data_empty_by_default(): void {
		$view = View::make( 'admin.settings' );
		$this->assertEmpty( $view->get_data() );
	}

	// ---------------------------------------------------------------
	// set_base_path / get_base_path
	// ---------------------------------------------------------------

	public function test_set_base_path_stores_path(): void {
		View::set_base_path( '/var/www/plugin/resources/views' );
		$this->assertSame( '/var/www/plugin/resources/views', View::get_base_path() );
	}

	public function test_set_base_path_trims_trailing_slash(): void {
		View::set_base_path( '/var/www/views/' );
		$this->assertSame( '/var/www/views', View::get_base_path() );
	}

	// ---------------------------------------------------------------
	// with()
	// ---------------------------------------------------------------

	public function test_with_array_merges_data(): void {
		$view = View::make( 't' )->with( array( 'a' => 1, 'b' => 2 ) );
		$this->assertSame( array( 'a' => 1, 'b' => 2 ), $view->get_data() );
	}

	public function test_with_key_value_pair(): void {
		$view = View::make( 't' )->with( 'title', 'Hello' );
		$this->assertSame( array( 'title' => 'Hello' ), $view->get_data() );
	}

	public function test_with_chains_data(): void {
		$view = View::make( 't' )
			->with( 'a', 1 )
			->with( 'b', 2 );
		$this->assertSame( array( 'a' => 1, 'b' => 2 ), $view->get_data() );
	}

	public function test_with_returns_self(): void {
		$view = View::make( 't' );
		$this->assertSame( $view, $view->with( array() ) );
	}

	// ---------------------------------------------------------------
	// get_path()
	// ---------------------------------------------------------------

	public function test_get_path_converts_dots_to_directory_separators(): void {
		View::set_base_path( '/views' );
		$view = View::make( 'admin.settings' );

		$expected = '/views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'settings.php';
		$this->assertSame( $expected, $view->get_path() );
	}

	public function test_get_path_uses_per_instance_base_path(): void {
		View::set_base_path( '/default' );
		$view = View::make( 'admin.settings', '/custom' );

		$expected = '/custom' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'settings.php';
		$this->assertSame( $expected, $view->get_path() );
	}

	public function test_from_overrides_base_path_for_this_view(): void {
		View::set_base_path( '/default' );
		$view = View::make( 'admin.settings' )->from( '/override' );

		$this->assertStringStartsWith( '/override', $view->get_path() );
	}

	public function test_get_path_without_base_path_returns_relative(): void {
		View::set_base_path( '' );
		$view = View::make( 'admin.settings' );

		$this->assertSame( 'admin' . DIRECTORY_SEPARATOR . 'settings.php', $view->get_path() );
	}

	// ---------------------------------------------------------------
	// render()
	// ---------------------------------------------------------------

	public function test_render_includes_template_and_returns_output(): void {
		$this->write_template( 'hello.php', '<?php echo "Hello World"; ?>' );

		View::set_base_path( $this->tmp_dir );

		$output = View::make( 'hello' )->render();

		$this->assertSame( 'Hello World', $output );
	}

	public function test_render_exposes_data_as_variables(): void {
		$this->write_template( 'greeting.php', '<?php echo $name; ?>' );

		View::set_base_path( $this->tmp_dir );

		$output = View::make( 'greeting' )->with( 'name', 'Alice' )->render();

		$this->assertSame( 'Alice', $output );
	}

	public function test_render_exposes_multiple_data_variables(): void {
		$this->write_template( 'tpl.php', '<?php echo $first . " " . $last; ?>' );

		View::set_base_path( $this->tmp_dir );

		$output = View::make( 'tpl' )
			->with( array( 'first' => 'John', 'last' => 'Doe' ) )
			->render();

		$this->assertSame( 'John Doe', $output );
	}

	public function test_render_nested_template_path(): void {
		$this->write_template( 'admin/settings.php', '<?php echo "Settings"; ?>' );

		View::set_base_path( $this->tmp_dir );

		$output = View::make( 'admin.settings' )->render();

		$this->assertSame( 'Settings', $output );
	}

	// ---------------------------------------------------------------
	// ViewServiceProvider
	// ---------------------------------------------------------------

	public function test_service_provider_sets_base_path_from_app(): void {
		$app = Application::get_instance( '/my/plugin' );

		$provider = new ViewServiceProvider( $app );
		$provider->register();
		$provider->boot();

		$expected = '/my/plugin' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
		$this->assertSame( $expected, View::get_base_path() );
	}
}
