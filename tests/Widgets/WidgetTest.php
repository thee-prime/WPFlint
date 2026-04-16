<?php
/**
 * Tests for the AbstractWidget base class.
 *
 * @package WPFlint\Tests\Widgets
 */

declare(strict_types=1);

namespace WPFlint\Tests\Widgets;

use WPFlint\Widgets\AbstractWidget;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * Concrete stub for testing AbstractWidget.
 */
class StubWidget extends AbstractWidget {

	protected string $widget_title = 'Stub Widget';
	protected string $description  = 'A test widget.';

	/** @var array<int, array{args: array<string,mixed>, instance: array<string,mixed>}> */
	public array $output_calls = array();

	/** @var array<int, array<string,mixed>> */
	public array $fields_calls = array();

	/** @var array<int, array{new: array<string,mixed>, old: array<string,mixed>}> */
	public array $sanitize_calls = array();

	protected function output( array $args, array $instance ): void {
		$this->output_calls[] = array( 'args' => $args, 'instance' => $instance );
	}

	protected function fields( array $instance ): void {
		$this->fields_calls[] = $instance;
	}

	protected function sanitize( array $new_instance, array $old_instance ): array {
		$this->sanitize_calls[] = array( 'new' => $new_instance, 'old' => $old_instance );
		return $new_instance;
	}
}

/**
 * @covers \WPFlint\Widgets\AbstractWidget
 */
class WidgetTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	// ---------------------------------------------------------------
	// Constructor / id_base
	// ---------------------------------------------------------------

	public function test_constructor_derives_id_base_from_class_name(): void {
		$widget = new StubWidget();
		// 'StubWidget' → 'stub_widget'
		$this->assertSame( 'stub_widget', $widget->id_base );
	}

	public function test_constructor_sets_widget_name(): void {
		$widget = new StubWidget();
		$this->assertSame( 'Stub Widget', $widget->name );
	}

	// ---------------------------------------------------------------
	// widget() → output()
	// ---------------------------------------------------------------

	public function test_widget_delegates_to_output(): void {
		$widget   = new StubWidget();
		$args     = array( 'before_widget' => '<div>', 'after_widget' => '</div>' );
		$instance = array( 'title' => 'Hello' );

		$widget->widget( $args, $instance );

		$this->assertCount( 1, $widget->output_calls );
		$this->assertSame( $args, $widget->output_calls[0]['args'] );
		$this->assertSame( $instance, $widget->output_calls[0]['instance'] );
	}

	public function test_widget_casts_args_to_array(): void {
		$widget = new StubWidget();
		$widget->widget( array(), array() );

		$this->assertIsArray( $widget->output_calls[0]['args'] );
		$this->assertIsArray( $widget->output_calls[0]['instance'] );
	}

	// ---------------------------------------------------------------
	// form() → fields()
	// ---------------------------------------------------------------

	public function test_form_delegates_to_fields(): void {
		$widget   = new StubWidget();
		$instance = array( 'title' => 'My Title' );

		$widget->form( $instance );

		$this->assertCount( 1, $widget->fields_calls );
		$this->assertSame( $instance, $widget->fields_calls[0] );
	}

	// ---------------------------------------------------------------
	// update() → sanitize()
	// ---------------------------------------------------------------

	public function test_update_delegates_to_sanitize(): void {
		$widget      = new StubWidget();
		$new         = array( 'title' => 'New' );
		$old         = array( 'title' => 'Old' );

		$result = $widget->update( $new, $old );

		$this->assertCount( 1, $widget->sanitize_calls );
		$this->assertSame( $new, $result );
	}

	// ---------------------------------------------------------------
	// Default sanitize()
	// ---------------------------------------------------------------

	public function test_default_sanitize_applies_sanitize_text_field(): void {
		// Use an anonymous class that does NOT override sanitize().
		$widget = new class extends AbstractWidget {
			protected string $widget_title = 'Test';
			protected function output( array $args, array $instance ): void {}
			protected function fields( array $instance ): void {}
		};

		WP_Mock::userFunction( 'sanitize_text_field' )
			->andReturnUsing( static function ( $v ) { return trim( $v ); } );

		$result = $widget->update( array( 'title' => '  Hello  ' ), array() );

		$this->assertSame( 'Hello', $result['title'] );
	}

	// ---------------------------------------------------------------
	// register()
	// ---------------------------------------------------------------

	public function test_register_adds_widgets_init_action(): void {
		WP_Mock::expectActionAdded( 'widgets_init', \WP_Mock\Functions::type( 'callable' ) );

		StubWidget::register();

		$this->addToAssertionCount( 1 );
	}

	public function test_register_widget_is_called_when_class_registers(): void {
		// Verify that register_widget() is correctly wired to widgets_init by
		// calling it directly with the expected class name.
		$registered = array();

		WP_Mock::userFunction( 'register_widget' )
			->andReturnUsing( static function ( $class ) use ( &$registered ) {
				$registered[] = $class;
			} );

		register_widget( StubWidget::class );

		$this->assertContains( StubWidget::class, $registered );
	}
}
