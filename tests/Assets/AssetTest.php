<?php
/**
 * Tests for the Assets module.
 *
 * @package WPFlint\Tests\Assets
 */

declare(strict_types=1);

namespace WPFlint\Tests\Assets;

use WPFlint\Assets\Asset;
use WPFlint\Assets\AssetManager;
use WPFlint\Assets\AssetServiceProvider;
use WPFlint\Assets\Script;
use WPFlint\Assets\Style;
use WPFlint\Application;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\Assets\Script
 * @covers \WPFlint\Assets\Style
 * @covers \WPFlint\Assets\Asset
 * @covers \WPFlint\Assets\AssetManager
 * @covers \WPFlint\Assets\AssetServiceProvider
 */
class AssetTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		Application::clear_instance();
	}

	// ---------------------------------------------------------------
	// Script — construction & getters
	// ---------------------------------------------------------------

	public function test_script_make_returns_instance(): void {
		$script = Script::make( 'my-plugin', 'https://example.com/app.js' );
		$this->assertInstanceOf( Script::class, $script );
	}

	public function test_script_getters_return_defaults(): void {
		$script = Script::make( 'my-plugin', 'https://example.com/app.js' );

		$this->assertSame( 'my-plugin', $script->get_handle() );
		$this->assertSame( 'https://example.com/app.js', $script->get_src() );
		$this->assertEmpty( $script->get_deps() );
		$this->assertSame( '', $script->get_version() );
		$this->assertFalse( $script->is_in_footer() );
		$this->assertSame( '', $script->get_localize_object() );
		$this->assertEmpty( $script->get_localize_data() );
	}

	public function test_script_deps_setter(): void {
		$script = Script::make( 'h', 'src' )->deps( array( 'jquery', 'wp-util' ) );
		$this->assertSame( array( 'jquery', 'wp-util' ), $script->get_deps() );
	}

	public function test_script_version_setter(): void {
		$script = Script::make( 'h', 'src' )->version( '2.0.0' );
		$this->assertSame( '2.0.0', $script->get_version() );
	}

	public function test_script_footer_setter(): void {
		$script = Script::make( 'h', 'src' )->footer();
		$this->assertTrue( $script->is_in_footer() );
	}

	public function test_script_footer_can_be_disabled(): void {
		$script = Script::make( 'h', 'src' )->footer( false );
		$this->assertFalse( $script->is_in_footer() );
	}

	public function test_script_localize_setter(): void {
		$data   = array( 'ajax_url' => '/wp-admin/admin-ajax.php' );
		$script = Script::make( 'h', 'src' )->localize( 'MyPlugin', $data );

		$this->assertSame( 'MyPlugin', $script->get_localize_object() );
		$this->assertSame( $data, $script->get_localize_data() );
	}

	public function test_script_setters_return_self(): void {
		$script = Script::make( 'h', 'src' );
		$this->assertSame( $script, $script->deps( array() ) );
		$this->assertSame( $script, $script->version( '1' ) );
		$this->assertSame( $script, $script->footer() );
		$this->assertSame( $script, $script->localize( 'O', array() ) );
		$this->assertSame( $script, $script->only_on( static function () { return true; } ) );
	}

	// ---------------------------------------------------------------
	// Script — enqueue
	// ---------------------------------------------------------------

	public function test_script_enqueue_calls_wp_enqueue_script(): void {
		$calls = array();

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		Script::make( 'my-plugin', 'https://example.com/app.js' )
			->deps( array( 'jquery' ) )
			->version( '1.0' )
			->footer()
			->enqueue();

		$this->assertCount( 1, $calls );
		$this->assertSame( 'my-plugin', $calls[0][0] );
		$this->assertSame( 'https://example.com/app.js', $calls[0][1] );
		$this->assertSame( array( 'jquery' ), $calls[0][2] );
		$this->assertSame( '1.0', $calls[0][3] );
		$this->assertTrue( $calls[0][4] );
	}

	public function test_script_enqueue_passes_false_version_when_none_set(): void {
		$calls = array();

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		Script::make( 'h', 'src' )->enqueue();

		$this->assertFalse( $calls[0][3] );
	}

	public function test_script_enqueue_calls_wp_localize_script_when_set(): void {
		$localize_calls = array();

		WP_Mock::userFunction( 'wp_enqueue_script' )->andReturn( null );
		WP_Mock::userFunction( 'wp_localize_script' )
			->andReturnUsing( static function () use ( &$localize_calls ) {
				$localize_calls[] = func_get_args();
			} );

		$data = array( 'nonce' => 'abc123' );

		Script::make( 'my-plugin', 'src' )
			->localize( 'MyPlugin', $data )
			->enqueue();

		$this->assertCount( 1, $localize_calls );
		$this->assertSame( 'my-plugin', $localize_calls[0][0] );
		$this->assertSame( 'MyPlugin', $localize_calls[0][1] );
		$this->assertSame( $data, $localize_calls[0][2] );
	}

	public function test_script_enqueue_skips_localize_when_not_set(): void {
		WP_Mock::userFunction( 'wp_enqueue_script' )->andReturn( null );
		WP_Mock::userFunction( 'wp_localize_script' )->never();

		Script::make( 'h', 'src' )->enqueue();

		$this->addToAssertionCount( 1 );
	}

	public function test_script_skips_enqueue_when_condition_is_false(): void {
		WP_Mock::userFunction( 'wp_enqueue_script' )->never();

		Script::make( 'h', 'src' )
			->only_on( static function () { return false; } )
			->enqueue();

		$this->addToAssertionCount( 1 );
	}

	public function test_script_enqueues_when_condition_is_true(): void {
		$called = false;

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->andReturnUsing( static function () use ( &$called ) {
				$called = true;
			} );

		Script::make( 'h', 'src' )
			->only_on( static function () { return true; } )
			->enqueue();

		$this->assertTrue( $called );
	}

	// ---------------------------------------------------------------
	// Script — register_asset
	// ---------------------------------------------------------------

	public function test_script_register_asset_calls_wp_register_script(): void {
		$calls = array();

		WP_Mock::userFunction( 'wp_register_script' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		Script::make( 'my-plugin', 'https://example.com/app.js' )
			->deps( array( 'jquery' ) )
			->version( '1.0' )
			->register_asset();

		$this->assertCount( 1, $calls );
		$this->assertSame( 'my-plugin', $calls[0][0] );
		$this->assertSame( '1.0', $calls[0][3] );
	}

	// ---------------------------------------------------------------
	// Style — construction & getters
	// ---------------------------------------------------------------

	public function test_style_make_returns_instance(): void {
		$style = Style::make( 'my-plugin', 'https://example.com/app.css' );
		$this->assertInstanceOf( Style::class, $style );
	}

	public function test_style_getters_return_defaults(): void {
		$style = Style::make( 'my-plugin', 'https://example.com/app.css' );

		$this->assertSame( 'my-plugin', $style->get_handle() );
		$this->assertSame( 'https://example.com/app.css', $style->get_src() );
		$this->assertEmpty( $style->get_deps() );
		$this->assertSame( '', $style->get_version() );
		$this->assertSame( 'all', $style->get_media() );
	}

	public function test_style_media_setter(): void {
		$style = Style::make( 'h', 'src' )->media( 'print' );
		$this->assertSame( 'print', $style->get_media() );
	}

	public function test_style_setters_return_self(): void {
		$style = Style::make( 'h', 'src' );
		$this->assertSame( $style, $style->media( 'screen' ) );
		$this->assertSame( $style, $style->deps( array() ) );
		$this->assertSame( $style, $style->version( '1' ) );
	}

	// ---------------------------------------------------------------
	// Style — enqueue
	// ---------------------------------------------------------------

	public function test_style_enqueue_calls_wp_enqueue_style(): void {
		$calls = array();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		Style::make( 'my-plugin', 'https://example.com/app.css' )
			->deps( array( 'dashicons' ) )
			->version( '1.0' )
			->media( 'screen' )
			->enqueue();

		$this->assertCount( 1, $calls );
		$this->assertSame( 'my-plugin', $calls[0][0] );
		$this->assertSame( 'https://example.com/app.css', $calls[0][1] );
		$this->assertSame( array( 'dashicons' ), $calls[0][2] );
		$this->assertSame( '1.0', $calls[0][3] );
		$this->assertSame( 'screen', $calls[0][4] );
	}

	public function test_style_enqueue_passes_false_version_when_none_set(): void {
		$calls = array();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		Style::make( 'h', 'src' )->enqueue();

		$this->assertFalse( $calls[0][3] );
	}

	public function test_style_skips_enqueue_when_condition_is_false(): void {
		WP_Mock::userFunction( 'wp_enqueue_style' )->never();

		Style::make( 'h', 'src' )
			->only_on( static function () { return false; } )
			->enqueue();

		$this->addToAssertionCount( 1 );
	}

	// ---------------------------------------------------------------
	// Style — register_asset
	// ---------------------------------------------------------------

	public function test_style_register_asset_calls_wp_register_style(): void {
		$calls = array();

		WP_Mock::userFunction( 'wp_register_style' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		Style::make( 'my-plugin', 'https://example.com/app.css' )
			->version( '2.0' )
			->register_asset();

		$this->assertCount( 1, $calls );
		$this->assertSame( 'my-plugin', $calls[0][0] );
		$this->assertSame( '2.0', $calls[0][3] );
	}

	// ---------------------------------------------------------------
	// should_enqueue
	// ---------------------------------------------------------------

	public function test_should_enqueue_true_when_no_condition(): void {
		$script = Script::make( 'h', 'src' );
		$this->assertTrue( $script->should_enqueue() );
	}

	public function test_should_enqueue_false_when_condition_false(): void {
		$script = Script::make( 'h', 'src' )
			->only_on( static function () { return false; } );
		$this->assertFalse( $script->should_enqueue() );
	}

	public function test_should_enqueue_true_when_condition_true(): void {
		$style = Style::make( 'h', 'src' )
			->only_on( static function () { return true; } );
		$this->assertTrue( $style->should_enqueue() );
	}

	// ---------------------------------------------------------------
	// AssetManager
	// ---------------------------------------------------------------

	public function test_asset_manager_starts_empty(): void {
		$manager = new AssetManager();
		$this->assertSame( 0, $manager->count() );
		$this->assertEmpty( $manager->get_assets() );
	}

	public function test_asset_manager_script_creates_and_stores_script(): void {
		$manager = new AssetManager();
		$script  = $manager->script( 'my-plugin', 'https://example.com/app.js' );

		$this->assertInstanceOf( Script::class, $script );
		$this->assertSame( 1, $manager->count() );
		$this->assertSame( $script, $manager->get_assets()[0] );
	}

	public function test_asset_manager_style_creates_and_stores_style(): void {
		$manager = new AssetManager();
		$style   = $manager->style( 'my-plugin', 'https://example.com/app.css' );

		$this->assertInstanceOf( Style::class, $style );
		$this->assertSame( 1, $manager->count() );
	}

	public function test_asset_manager_add_stores_asset(): void {
		$manager = new AssetManager();
		$script  = Script::make( 'h', 'src' );

		$result = $manager->add( $script );

		$this->assertSame( $manager, $result );
		$this->assertSame( 1, $manager->count() );
	}

	public function test_asset_manager_enqueue_calls_each_asset_enqueue(): void {
		$enqueued = array();

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->andReturnUsing( static function () use ( &$enqueued ) {
				$enqueued[] = func_get_args()[0];
			} );

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->andReturnUsing( static function () use ( &$enqueued ) {
				$enqueued[] = func_get_args()[0];
			} );

		$manager = new AssetManager();
		$manager->script( 'script-one', 'a.js' );
		$manager->script( 'script-two', 'b.js' );
		$manager->style( 'style-one', 'a.css' );

		$manager->enqueue();

		$this->assertCount( 3, $enqueued );
		$this->assertContains( 'script-one', $enqueued );
		$this->assertContains( 'script-two', $enqueued );
		$this->assertContains( 'style-one', $enqueued );
	}

	public function test_asset_manager_enqueue_skips_conditional_assets(): void {
		$enqueued = array();

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->andReturnUsing( static function () use ( &$enqueued ) {
				$enqueued[] = func_get_args()[0];
			} );

		$manager = new AssetManager();
		$manager->script( 'always', 'a.js' );
		$manager->add(
			Script::make( 'conditional', 'b.js' )
				->only_on( static function () { return false; } )
		);

		$manager->enqueue();

		$this->assertContains( 'always', $enqueued );
		$this->assertNotContains( 'conditional', $enqueued );
	}

	public function test_asset_manager_register_all_calls_register_asset(): void {
		$registered = array();

		WP_Mock::userFunction( 'wp_register_script' )
			->andReturnUsing( static function () use ( &$registered ) {
				$registered[] = func_get_args()[0];
			} );

		WP_Mock::userFunction( 'wp_register_style' )
			->andReturnUsing( static function () use ( &$registered ) {
				$registered[] = func_get_args()[0];
			} );

		$manager = new AssetManager();
		$manager->script( 'js-handle', 'a.js' );
		$manager->style( 'css-handle', 'a.css' );
		$manager->register_all();

		$this->assertContains( 'js-handle', $registered );
		$this->assertContains( 'css-handle', $registered );
	}

	// ---------------------------------------------------------------
	// AssetServiceProvider
	// ---------------------------------------------------------------

	public function test_service_provider_binds_asset_manager(): void {
		$app = Application::get_instance( __DIR__ );

		WP_Mock::expectActionAdded( 'wp_enqueue_scripts', \WP_Mock\Functions::type( 'callable' ) );
		WP_Mock::expectActionAdded( 'admin_enqueue_scripts', \WP_Mock\Functions::type( 'callable' ) );

		$app->register( AssetServiceProvider::class );
		$app->boot_providers();

		$manager = $app->make( 'assets' );
		$this->assertInstanceOf( AssetManager::class, $manager );
	}

	public function test_service_provider_binds_class_alias(): void {
		$app = Application::get_instance( __DIR__ );

		// Register without boot — no add_action calls expected.
		$app->register( AssetServiceProvider::class );

		$manager1 = $app->make( 'assets' );
		$manager2 = $app->make( AssetManager::class );

		$this->assertSame( $manager1, $manager2 );
	}

	public function test_service_provider_hooks_enqueue_actions(): void {
		Application::clear_instance();
		$app = Application::get_instance( __DIR__ );

		WP_Mock::expectActionAdded( 'wp_enqueue_scripts', \WP_Mock\Functions::type( 'callable' ) );
		WP_Mock::expectActionAdded( 'admin_enqueue_scripts', \WP_Mock\Functions::type( 'callable' ) );

		$provider = new AssetServiceProvider( $app );
		$provider->register();
		$provider->boot();
		// Expectations verified by WP_Mock::tearDown() in tearDown().
		$this->addToAssertionCount( 2 );
	}
}
