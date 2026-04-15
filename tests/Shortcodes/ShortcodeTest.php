<?php
/**
 * Tests for the Shortcode builder.
 *
 * @package WPFlint\Tests\Shortcodes
 */

declare(strict_types=1);

namespace WPFlint\Tests\Shortcodes;

use WPFlint\Shortcodes\Shortcode;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\Shortcodes\Shortcode
 */
class ShortcodeTest extends TestCase {

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
		$sc = Shortcode::make( 'my_button' );
		$this->assertInstanceOf( Shortcode::class, $sc );
	}

	public function test_tag_is_stored(): void {
		$sc = Shortcode::make( 'my_button' );
		$this->assertSame( 'my_button', $sc->get_tag() );
	}

	public function test_defaults_empty_by_default(): void {
		$sc = Shortcode::make( 'my_button' );
		$this->assertEmpty( $sc->get_defaults() );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	public function test_defaults_setter(): void {
		$sc = Shortcode::make( 'my_button' )
			->defaults( array( 'color' => 'blue', 'size' => 'medium' ) );

		$this->assertSame( array( 'color' => 'blue', 'size' => 'medium' ), $sc->get_defaults() );
	}

	public function test_defaults_returns_self(): void {
		$sc     = Shortcode::make( 'my_button' );
		$result = $sc->defaults( array() );
		$this->assertSame( $sc, $result );
	}

	public function test_render_returns_self(): void {
		$sc     = Shortcode::make( 'my_button' );
		$result = $sc->render( static function () { return ''; } );
		$this->assertSame( $sc, $result );
	}

	// ---------------------------------------------------------------
	// register() — add_shortcode
	// ---------------------------------------------------------------

	public function test_register_calls_add_shortcode(): void {
		$calls = array();

		WP_Mock::userFunction( 'add_shortcode' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		Shortcode::make( 'my_button' )->register();

		$this->assertCount( 1, $calls );
		$this->assertSame( 'my_button', $calls[0][0] );
		$this->assertIsCallable( $calls[0][1] );
	}

	public function test_register_callback_merges_attributes_via_shortcode_atts(): void {
		$registered_callback = null;

		WP_Mock::userFunction( 'add_shortcode' )
			->andReturnUsing( static function ( $tag, $cb ) use ( &$registered_callback ) {
				$registered_callback = $cb;
			} );

		WP_Mock::userFunction( 'shortcode_atts' )
			->andReturnUsing( static function ( $defaults, $atts ) {
				return array_merge( $defaults, (array) $atts );
			} );

		$received = array();

		Shortcode::make( 'my_button' )
			->defaults( array( 'color' => 'blue' ) )
			->render( static function ( array $atts, string $content ) use ( &$received ) {
				$received = $atts;
				return '';
			} )
			->register();

		// Simulate WP calling the registered callback.
		$registered_callback( array( 'color' => 'red' ), null );

		$this->assertSame( 'red', $received['color'] );
	}

	public function test_register_callback_passes_content_as_string(): void {
		$registered_callback = null;

		WP_Mock::userFunction( 'add_shortcode' )
			->andReturnUsing( static function ( $tag, $cb ) use ( &$registered_callback ) {
				$registered_callback = $cb;
			} );

		WP_Mock::userFunction( 'shortcode_atts' )
			->andReturnUsing( static function ( $defaults ) {
				return $defaults;
			} );

		$content_received = null;

		Shortcode::make( 'my_button' )
			->render( static function ( array $atts, string $content ) use ( &$content_received ) {
				$content_received = $content;
				return '';
			} )
			->register();

		$registered_callback( array(), 'Click me' );

		$this->assertSame( 'Click me', $content_received );
	}

	public function test_register_callback_converts_null_content_to_empty_string(): void {
		$registered_callback = null;

		WP_Mock::userFunction( 'add_shortcode' )
			->andReturnUsing( static function ( $tag, $cb ) use ( &$registered_callback ) {
				$registered_callback = $cb;
			} );

		WP_Mock::userFunction( 'shortcode_atts' )
			->andReturnUsing( static function ( $defaults ) {
				return $defaults;
			} );

		$content_received = 'not-set';

		Shortcode::make( 'my_button' )
			->render( static function ( array $atts, string $content ) use ( &$content_received ) {
				$content_received = $content;
				return '';
			} )
			->register();

		$registered_callback( array(), null );

		$this->assertSame( '', $content_received );
	}

	public function test_register_callback_returns_empty_string_when_no_render_callback(): void {
		$registered_callback = null;

		WP_Mock::userFunction( 'add_shortcode' )
			->andReturnUsing( static function ( $tag, $cb ) use ( &$registered_callback ) {
				$registered_callback = $cb;
			} );

		WP_Mock::userFunction( 'shortcode_atts' )
			->andReturnUsing( static function ( $defaults ) {
				return $defaults;
			} );

		Shortcode::make( 'my_button' )->register();

		$result = $registered_callback( array(), null );

		$this->assertSame( '', $result );
	}

	public function test_register_callback_returns_render_output(): void {
		$registered_callback = null;

		WP_Mock::userFunction( 'add_shortcode' )
			->andReturnUsing( static function ( $tag, $cb ) use ( &$registered_callback ) {
				$registered_callback = $cb;
			} );

		WP_Mock::userFunction( 'shortcode_atts' )
			->andReturnUsing( static function ( $defaults ) {
				return $defaults;
			} );

		Shortcode::make( 'my_button' )
			->render( static function ( array $atts, string $content ): string {
				return '<button>' . esc_html( $content ) . '</button>';
			} )
			->register();

		WP_Mock::userFunction( 'esc_html' )
			->andReturnUsing( static function ( $text ) { return $text; } );

		$output = $registered_callback( array(), 'Click' );

		$this->assertSame( '<button>Click</button>', $output );
	}

	// ---------------------------------------------------------------
	// unregister()
	// ---------------------------------------------------------------

	public function test_unregister_calls_remove_shortcode(): void {
		$calls = array();

		WP_Mock::userFunction( 'remove_shortcode' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		Shortcode::make( 'my_button' )->unregister();

		$this->assertCount( 1, $calls );
		$this->assertSame( 'my_button', $calls[0][0] );
	}
}
