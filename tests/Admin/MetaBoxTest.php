<?php
/**
 * Tests for the MetaBox and MetaBoxField builders.
 *
 * @package WPFlint\Tests\Admin
 */

declare(strict_types=1);

namespace WPFlint\Tests\Admin;

use WPFlint\Admin\MetaBox;
use WPFlint\Admin\MetaBoxField;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\Admin\MetaBox
 * @covers \WPFlint\Admin\MetaBoxField
 */
class MetaBoxTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		// Clean up $_POST side-effects.
		$_POST = array();
	}

	// ---------------------------------------------------------------
	// MetaBox factory
	// ---------------------------------------------------------------

	public function test_make_returns_instance(): void {
		$box = MetaBox::make( 'book_details', 'Book Details' );
		$this->assertInstanceOf( MetaBox::class, $box );
	}

	public function test_make_sets_id_and_title(): void {
		$box = MetaBox::make( 'book_details', 'Book Details' );
		$this->assertSame( 'book_details', $box->get_id() );
		$this->assertSame( 'Book Details', $box->get_title() );
	}

	public function test_nonce_action_derived_from_id(): void {
		$box = MetaBox::make( 'my_box', 'My Box' );
		$this->assertSame( 'my_box_nonce', $box->get_nonce_action() );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	public function test_screen_setter(): void {
		$box = MetaBox::make( 'id', 'Title' )->screen( 'book' );
		$this->assertSame( 'book', $box->get_screen() );
	}

	public function test_screen_accepts_array(): void {
		$box = MetaBox::make( 'id', 'Title' )->screen( array( 'post', 'page' ) );
		$this->assertSame( array( 'post', 'page' ), $box->get_screen() );
	}

	public function test_context_setter(): void {
		$box = MetaBox::make( 'id', 'Title' )->context( 'side' );
		$this->assertSame( 'side', $box->get_context() );
	}

	public function test_priority_setter(): void {
		$box = MetaBox::make( 'id', 'Title' )->priority( 'high' );
		$this->assertSame( 'high', $box->get_priority() );
	}

	public function test_screen_returns_self(): void {
		$box = MetaBox::make( 'id', 'Title' );
		$this->assertSame( $box, $box->screen( 'post' ) );
	}

	// ---------------------------------------------------------------
	// field()
	// ---------------------------------------------------------------

	public function test_field_returns_meta_box_field(): void {
		$box   = MetaBox::make( 'id', 'Title' );
		$field = $box->field( '_isbn', 'ISBN' );
		$this->assertInstanceOf( MetaBoxField::class, $field );
	}

	public function test_field_stored_in_fields_array(): void {
		$box = MetaBox::make( 'id', 'Title' );
		$box->field( '_isbn', 'ISBN' );
		$box->field( '_pages', 'Pages' );
		$this->assertCount( 2, $box->get_fields() );
	}

	public function test_field_allows_chaining_type(): void {
		$box   = MetaBox::make( 'id', 'Title' );
		$field = $box->field( '_isbn', 'ISBN' )->type( 'number' );
		$this->assertSame( 'number', $field->get_type() );
	}

	// ---------------------------------------------------------------
	// register()
	// ---------------------------------------------------------------

	public function test_register_calls_add_meta_box(): void {
		$calls = array();

		WP_Mock::userFunction( 'add_meta_box' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		WP_Mock::userFunction( 'add_action' )->andReturn( null );

		MetaBox::make( 'book_details', 'Book Details' )
			->screen( 'book' )
			->context( 'side' )
			->priority( 'high' )
			->register();

		$this->assertCount( 1, $calls );
		$this->assertSame( 'book_details', $calls[0][0] );
		$this->assertSame( 'Book Details', $calls[0][1] );
		$this->assertSame( 'book', $calls[0][3] );
		$this->assertSame( 'side', $calls[0][4] );
		$this->assertSame( 'high', $calls[0][5] );
	}

	public function test_register_render_callback_is_callable(): void {
		$calls = array();

		WP_Mock::userFunction( 'add_meta_box' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		WP_Mock::userFunction( 'add_action' )->andReturn( null );

		MetaBox::make( 'id', 'Title' )->register();

		$this->assertIsCallable( $calls[0][2] );
	}

	public function test_register_hooks_save_post(): void {
		WP_Mock::userFunction( 'add_meta_box' )->andReturn( null );
		WP_Mock::expectActionAdded( 'save_post', \WP_Mock\Functions::type( 'callable' ) );

		MetaBox::make( 'id', 'Title' )->register();

		$this->addToAssertionCount( 1 );
	}

	// ---------------------------------------------------------------
	// MetaBoxField — constructor & getters
	// ---------------------------------------------------------------

	public function test_field_constructor_sets_id_and_label(): void {
		$field = new MetaBoxField( '_isbn', 'ISBN' );
		$this->assertSame( '_isbn', $field->get_id() );
		$this->assertSame( 'ISBN', $field->get_label() );
	}

	public function test_field_default_type_is_text(): void {
		$field = new MetaBoxField( '_key', 'Label' );
		$this->assertSame( 'text', $field->get_type() );
	}

	public function test_field_type_setter(): void {
		$field = ( new MetaBoxField( '_key', 'Label' ) )->type( 'textarea' );
		$this->assertSame( 'textarea', $field->get_type() );
	}

	public function test_field_description_setter(): void {
		$field = ( new MetaBoxField( '_key', 'Label' ) )->description( 'A helper.' );
		$this->assertSame( 'A helper.', $field->get_description() );
	}

	public function test_field_default_value_setter(): void {
		$field = ( new MetaBoxField( '_key', 'Label' ) )->default_value( 'hello' );
		$this->assertSame( 'hello', $field->get_default() );
	}

	public function test_field_options_setter(): void {
		$opts  = array( 'a' => 'Option A', 'b' => 'Option B' );
		$field = ( new MetaBoxField( '_key', 'Label' ) )->options( $opts );
		$this->assertSame( $opts, $field->get_options() );
	}

	// ---------------------------------------------------------------
	// MetaBoxField::save()
	// ---------------------------------------------------------------

	public function test_field_save_updates_post_meta(): void {
		$_POST['_isbn'] = 'ISBN-123';

		WP_Mock::userFunction( 'wp_unslash' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );

		$updated = array();
		WP_Mock::userFunction( 'update_post_meta' )
			->andReturnUsing( static function ( $post_id, $key, $value ) use ( &$updated ) {
				$updated[] = array( $post_id, $key, $value );
				return true;
			} );

		$field = new MetaBoxField( '_isbn', 'ISBN' );
		$field->save( 10 );

		$this->assertCount( 1, $updated );
		$this->assertSame( array( 10, '_isbn', 'ISBN-123' ), $updated[0] );
	}

	public function test_field_save_sanitizes_textarea(): void {
		$_POST['_bio'] = "Line one\nLine two";

		WP_Mock::userFunction( 'wp_unslash' )->andReturnArg( 0 );

		$sanitized = null;
		WP_Mock::userFunction( 'sanitize_textarea_field' )
			->andReturnUsing( static function ( $v ) use ( &$sanitized ) {
				$sanitized = $v;
				return $v;
			} );
		WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );

		$field = ( new MetaBoxField( '_bio', 'Bio' ) )->type( 'textarea' );
		$field->save( 10 );

		$this->assertNotNull( $sanitized );
	}

	public function test_field_save_checkbox_deletes_meta_when_unchecked(): void {
		// $_POST['_active'] not set — checkbox unchecked.
		$deleted = array();
		WP_Mock::userFunction( 'delete_post_meta' )
			->andReturnUsing( static function ( $post_id, $key ) use ( &$deleted ) {
				$deleted[] = array( $post_id, $key );
				return true;
			} );

		$field = ( new MetaBoxField( '_active', 'Active' ) )->type( 'checkbox' );
		$field->save( 10 );

		$this->assertCount( 1, $deleted );
		$this->assertSame( array( 10, '_active' ), $deleted[0] );
	}

	public function test_field_save_uses_custom_sanitize_callback(): void {
		$_POST['_count'] = '42px';

		WP_Mock::userFunction( 'wp_unslash' )->andReturnArg( 0 );

		$updated = array();
		WP_Mock::userFunction( 'update_post_meta' )
			->andReturnUsing( static function ( $post_id, $key, $value ) use ( &$updated ) {
				$updated[] = array( $post_id, $key, $value );
				return true;
			} );

		$field = ( new MetaBoxField( '_count', 'Count' ) )
			->sanitize_with( 'intval' );
		$field->save( 10 );

		$this->assertCount( 1, $updated );
		$this->assertSame( 42, $updated[0][2] );
	}

	public function test_field_save_does_nothing_when_not_in_post(): void {
		// $_POST is empty — save() should return without calling update_post_meta.
		$update_called = false;
		$delete_called = false;

		WP_Mock::userFunction( 'update_post_meta' )
			->andReturnUsing( static function () use ( &$update_called ) {
				$update_called = true;
				return true;
			} );
		WP_Mock::userFunction( 'delete_post_meta' )
			->andReturnUsing( static function () use ( &$delete_called ) {
				$delete_called = true;
				return true;
			} );

		$field = new MetaBoxField( '_absent', 'Absent' );
		$field->save( 10 );

		$this->assertFalse( $update_called );
		$this->assertFalse( $delete_called );
	}

	// ---------------------------------------------------------------
	// MetaBoxField::render()
	// ---------------------------------------------------------------

	public function test_field_render_outputs_label(): void {
		WP_Mock::userFunction( 'get_post_meta' )->andReturn( '' );
		WP_Mock::userFunction( 'esc_attr' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_html' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_textarea' )->andReturnArg( 0 );

		ob_start();
		( new MetaBoxField( '_isbn', 'ISBN' ) )->render( 1 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'ISBN', (string) $html );
	}

	public function test_field_render_textarea_type(): void {
		WP_Mock::userFunction( 'get_post_meta' )->andReturn( 'existing text' );
		WP_Mock::userFunction( 'esc_attr' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_html' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_textarea' )->andReturnArg( 0 );

		ob_start();
		( new MetaBoxField( '_bio', 'Bio' ) )->type( 'textarea' )->render( 1 );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<textarea', (string) $html );
		$this->assertStringContainsString( 'existing text', (string) $html );
	}

	public function test_field_render_checkbox_type(): void {
		WP_Mock::userFunction( 'get_post_meta' )->andReturn( '1' );
		WP_Mock::userFunction( 'esc_attr' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_html' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'checked' )->andReturn( 'checked' );

		ob_start();
		( new MetaBoxField( '_active', 'Active' ) )->type( 'checkbox' )->render( 1 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'type="checkbox"', (string) $html );
	}

	public function test_field_render_select_type(): void {
		WP_Mock::userFunction( 'get_post_meta' )->andReturn( 'b' );
		WP_Mock::userFunction( 'esc_attr' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_html' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'selected' )->andReturn( '' );

		ob_start();
		( new MetaBoxField( '_color', 'Color' ) )
			->type( 'select' )
			->options( array( 'a' => 'Alpha', 'b' => 'Beta' ) )
			->render( 1 );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<select', (string) $html );
		$this->assertStringContainsString( 'Alpha', (string) $html );
		$this->assertStringContainsString( 'Beta', (string) $html );
	}

	public function test_field_render_shows_description(): void {
		WP_Mock::userFunction( 'get_post_meta' )->andReturn( '' );
		WP_Mock::userFunction( 'esc_attr' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'esc_html' )->andReturnArg( 0 );

		ob_start();
		( new MetaBoxField( '_key', 'Key' ) )->description( 'Helper text.' )->render( 1 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Helper text.', (string) $html );
	}
}
