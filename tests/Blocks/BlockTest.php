<?php
/**
 * Tests for the Block builder.
 *
 * @package WPFlint\Tests\Blocks
 */

declare(strict_types=1);

namespace WPFlint\Tests\Blocks;

use WPFlint\Blocks\Block;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\Blocks\Block
 */
class BlockTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	// ---------------------------------------------------------------
	// Factory
	// ---------------------------------------------------------------

	public function test_make_returns_instance(): void {
		$block = Block::make( 'my-plugin/card' );
		$this->assertInstanceOf( Block::class, $block );
	}

	public function test_make_sets_name(): void {
		$block = Block::make( 'my-plugin/card' );
		$this->assertSame( 'my-plugin/card', $block->get_name() );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	public function test_title_setter(): void {
		$block = Block::make( 'n/a' )->title( 'My Card' );
		$this->assertSame( 'My Card', $block->get_title() );
	}

	public function test_title_returns_self(): void {
		$block = Block::make( 'n/a' );
		$this->assertSame( $block, $block->title( 'X' ) );
	}

	public function test_category_setter(): void {
		$block = Block::make( 'n/a' )->category( 'widgets' );
		$this->assertSame( 'widgets', $block->get_category() );
	}

	public function test_keywords_setter(): void {
		$block = Block::make( 'n/a' )->keywords( array( 'card', 'box' ) );
		$this->assertSame( array( 'card', 'box' ), $block->get_keywords() );
	}

	public function test_attributes_setter(): void {
		$attrs = array( 'color' => array( 'type' => 'string', 'default' => 'blue' ) );
		$block = Block::make( 'n/a' )->attributes( $attrs );
		$this->assertSame( $attrs, $block->get_attributes() );
	}

	public function test_render_setter(): void {
		$cb    = static function (): string { return ''; };
		$block = Block::make( 'n/a' )->render( $cb );
		$this->assertSame( $cb, $block->get_render_callback() );
	}

	// ---------------------------------------------------------------
	// to_args()
	// ---------------------------------------------------------------

	public function test_to_args_empty_by_default(): void {
		$args = Block::make( 'n/a' )->to_args();
		$this->assertSame( array(), $args );
	}

	public function test_to_args_includes_title_when_set(): void {
		$args = Block::make( 'n/a' )->title( 'Card' )->to_args();
		$this->assertArrayHasKey( 'title', $args );
		$this->assertSame( 'Card', $args['title'] );
	}

	public function test_to_args_includes_category_when_set(): void {
		$args = Block::make( 'n/a' )->category( 'text' )->to_args();
		$this->assertArrayHasKey( 'category', $args );
	}

	public function test_to_args_includes_render_callback_when_set(): void {
		$cb   = static function (): string { return ''; };
		$args = Block::make( 'n/a' )->render( $cb )->to_args();
		$this->assertArrayHasKey( 'render_callback', $args );
	}

	public function test_to_args_includes_editor_script(): void {
		$args = Block::make( 'n/a' )->editor_script( 'my-block-editor' )->to_args();
		$this->assertSame( 'my-block-editor', $args['editor_script'] );
	}

	public function test_to_args_maps_script_to_script_key(): void {
		$args = Block::make( 'n/a' )->script( 'my-block' )->to_args();
		$this->assertArrayHasKey( 'script', $args );
		$this->assertSame( 'my-block', $args['script'] );
	}

	public function test_to_args_maps_style_to_style_key(): void {
		$args = Block::make( 'n/a' )->style( 'my-block-style' )->to_args();
		$this->assertArrayHasKey( 'style', $args );
	}

	public function test_to_args_excludes_empty_keywords(): void {
		$args = Block::make( 'n/a' )->to_args();
		$this->assertArrayNotHasKey( 'keywords', $args );
	}

	public function test_to_args_includes_keywords_when_set(): void {
		$args = Block::make( 'n/a' )->keywords( array( 'pricing' ) )->to_args();
		$this->assertArrayHasKey( 'keywords', $args );
		$this->assertSame( array( 'pricing' ), $args['keywords'] );
	}

	// ---------------------------------------------------------------
	// register()
	// ---------------------------------------------------------------

	public function test_register_calls_register_block_type(): void {
		$called = array();

		WP_Mock::userFunction( 'register_block_type' )
			->andReturnUsing( static function ( $name, $args ) use ( &$called ) {
				$called[] = array( $name, $args );
				return true;
			} );

		Block::make( 'my-plugin/card' )->title( 'Card' )->register();

		$this->assertCount( 1, $called );
		$this->assertSame( 'my-plugin/card', $called[0][0] );
		$this->assertSame( 'Card', $called[0][1]['title'] );
	}

	public function test_register_passes_render_callback(): void {
		$cb     = static function (): string { return '<div></div>'; };
		$called = array();

		WP_Mock::userFunction( 'register_block_type' )
			->andReturnUsing( static function ( $name, $args ) use ( &$called ) {
				$called[] = $args;
				return true;
			} );

		Block::make( 'my-plugin/card' )->render( $cb )->register();

		$this->assertArrayHasKey( 'render_callback', $called[0] );
		$this->assertSame( $cb, $called[0]['render_callback'] );
	}
}
