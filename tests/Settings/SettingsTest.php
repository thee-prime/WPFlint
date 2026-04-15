<?php
/**
 * Tests for the Settings module.
 *
 * @package WPFlint\Tests\Settings
 */

declare(strict_types=1);

namespace WPFlint\Tests\Settings;

use WPFlint\Settings\Field;
use WPFlint\Settings\Section;
use WPFlint\Settings\Settings;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\Settings\Settings
 * @covers \WPFlint\Settings\Section
 * @covers \WPFlint\Settings\Field
 */
class SettingsTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	// ---------------------------------------------------------------
	// Field — construction & getters
	// ---------------------------------------------------------------

	public function test_field_getters_return_defaults(): void {
		$field = new Field( 'api_key', 'API Key' );

		$this->assertSame( 'api_key', $field->get_id() );
		$this->assertSame( 'API Key', $field->get_label() );
		$this->assertSame( 'text', $field->get_type() );
		$this->assertSame( '', $field->get_default() );
		$this->assertSame( '', $field->get_description() );
		$this->assertFalse( $field->is_required() );
		$this->assertEmpty( $field->get_options() );
	}

	public function test_field_type_setter(): void {
		$field = ( new Field( 'f', 'F' ) )->type( 'checkbox' );
		$this->assertSame( 'checkbox', $field->get_type() );
	}

	public function test_field_default_setter(): void {
		$field = ( new Field( 'f', 'F' ) )->default( 'hello' );
		$this->assertSame( 'hello', $field->get_default() );
	}

	public function test_field_description_setter(): void {
		$field = ( new Field( 'f', 'F' ) )->description( 'Help text' );
		$this->assertSame( 'Help text', $field->get_description() );
	}

	public function test_field_required_setter(): void {
		$field = ( new Field( 'f', 'F' ) )->required();
		$this->assertTrue( $field->is_required() );
	}

	public function test_field_required_can_be_disabled(): void {
		$field = ( new Field( 'f', 'F' ) )->required( false );
		$this->assertFalse( $field->is_required() );
	}

	public function test_field_options_setter(): void {
		$opts  = array( 'a' => 'Alpha', 'b' => 'Beta' );
		$field = ( new Field( 'f', 'F' ) )->options( $opts );
		$this->assertSame( $opts, $field->get_options() );
	}

	public function test_field_setters_return_self(): void {
		$field = new Field( 'f', 'F' );
		$this->assertSame( $field, $field->type( 'text' ) );
		$this->assertSame( $field, $field->default( '' ) );
		$this->assertSame( $field, $field->description( 'd' ) );
		$this->assertSame( $field, $field->required() );
		$this->assertSame( $field, $field->options( array() ) );
	}

	// ---------------------------------------------------------------
	// Section — construction & getters
	// ---------------------------------------------------------------

	public function test_section_getters_return_defaults(): void {
		$section = new Section( 'general', 'General' );

		$this->assertSame( 'general', $section->get_id() );
		$this->assertSame( 'General', $section->get_title() );
		$this->assertSame( '', $section->get_description() );
		$this->assertEmpty( $section->get_fields() );
	}

	public function test_section_description_setter(): void {
		$section = ( new Section( 's', 'S' ) )->description( 'Section desc' );
		$this->assertSame( 'Section desc', $section->get_description() );
	}

	public function test_section_description_returns_self(): void {
		$section = new Section( 's', 'S' );
		$this->assertSame( $section, $section->description( 'd' ) );
	}

	public function test_section_field_creates_and_stores_field(): void {
		$section = new Section( 's', 'S' );
		$field   = $section->field( 'name', 'Name' );

		$this->assertInstanceOf( Field::class, $field );
		$this->assertCount( 1, $section->get_fields() );
		$this->assertSame( $field, $section->get_fields()[0] );
	}

	public function test_section_multiple_fields_accumulate(): void {
		$section = new Section( 's', 'S' );
		$section->field( 'a', 'A' );
		$section->field( 'b', 'B' );
		$section->field( 'c', 'C' );

		$this->assertCount( 3, $section->get_fields() );
	}

	// ---------------------------------------------------------------
	// Settings — construction & getters
	// ---------------------------------------------------------------

	public function test_settings_make_returns_instance(): void {
		$settings = Settings::make( 'my_group', 'my_options' );
		$this->assertInstanceOf( Settings::class, $settings );
	}

	public function test_settings_getters_return_initial_values(): void {
		$settings = Settings::make( 'my_group', 'my_options' );

		$this->assertSame( 'my_group', $settings->get_option_group() );
		$this->assertSame( 'my_options', $settings->get_option_name() );
		$this->assertSame( '', $settings->get_page() );
		$this->assertEmpty( $settings->get_sections() );
	}

	public function test_settings_page_setter(): void {
		$settings = Settings::make( 'g', 'o' )->page( 'my-settings-page' );
		$this->assertSame( 'my-settings-page', $settings->get_page() );
	}

	public function test_settings_page_returns_self(): void {
		$settings = Settings::make( 'g', 'o' );
		$this->assertSame( $settings, $settings->page( 'p' ) );
	}

	public function test_settings_sanitize_returns_self(): void {
		$settings = Settings::make( 'g', 'o' );
		$this->assertSame( $settings, $settings->sanitize( 'sanitize_text_field' ) );
	}

	public function test_settings_section_returns_self(): void {
		$settings = Settings::make( 'g', 'o' );
		$result   = $settings->section( 'general', 'General' );
		$this->assertSame( $settings, $result );
	}

	public function test_settings_section_stores_section(): void {
		$settings = Settings::make( 'g', 'o' )
			->section( 'general', 'General' );

		$sections = $settings->get_sections();
		$this->assertCount( 1, $sections );
		$this->assertSame( 'general', $sections[0]->get_id() );
		$this->assertSame( 'General', $sections[0]->get_title() );
	}

	public function test_settings_section_callback_receives_section(): void {
		$received = null;

		Settings::make( 'g', 'o' )
			->section( 'general', 'General', static function ( Section $s ) use ( &$received ) {
				$received = $s;
			} );

		$this->assertInstanceOf( Section::class, $received );
	}

	public function test_settings_section_callback_fields_persist(): void {
		$settings = Settings::make( 'g', 'o' )
			->section( 'general', 'General', static function ( Section $s ) {
				$s->field( 'api_key', 'API Key' )->type( 'text' );
				$s->field( 'debug', 'Debug' )->type( 'checkbox' );
			} );

		$fields = $settings->get_sections()[0]->get_fields();
		$this->assertCount( 2, $fields );
		$this->assertSame( 'api_key', $fields[0]->get_id() );
		$this->assertSame( 'debug', $fields[1]->get_id() );
	}

	public function test_settings_multiple_sections_accumulate(): void {
		$settings = Settings::make( 'g', 'o' )
			->section( 'general', 'General' )
			->section( 'advanced', 'Advanced' );

		$this->assertCount( 2, $settings->get_sections() );
	}

	// ---------------------------------------------------------------
	// Settings::register() — WP function calls
	// ---------------------------------------------------------------

	public function test_register_calls_register_setting(): void {
		$calls = array();

		WP_Mock::userFunction( 'register_setting' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		WP_Mock::userFunction( 'add_settings_section' )->andReturn( null );
		WP_Mock::userFunction( 'add_settings_field' )->andReturn( null );

		Settings::make( 'my_group', 'my_options' )
			->section( 'general', 'General', static function ( Section $s ) {
				$s->field( 'name', 'Name' );
			} )
			->register();

		$this->assertCount( 1, $calls );
		$this->assertSame( 'my_group', $calls[0][0] );
		$this->assertSame( 'my_options', $calls[0][1] );
	}

	public function test_register_calls_add_settings_section_for_each_section(): void {
		$section_calls = array();

		WP_Mock::userFunction( 'register_setting' )->andReturn( null );

		WP_Mock::userFunction( 'add_settings_section' )
			->andReturnUsing( static function () use ( &$section_calls ) {
				$section_calls[] = func_get_args();
			} );

		WP_Mock::userFunction( 'add_settings_field' )->andReturn( null );

		Settings::make( 'g', 'o' )
			->page( 'my-page' )
			->section( 'general', 'General' )
			->section( 'advanced', 'Advanced' )
			->register();

		$this->assertCount( 2, $section_calls );
		$this->assertSame( 'general', $section_calls[0][0] );
		$this->assertSame( 'General', $section_calls[0][1] );
		$this->assertSame( 'my-page', $section_calls[0][3] );
		$this->assertSame( 'advanced', $section_calls[1][0] );
	}

	public function test_register_calls_add_settings_field_for_each_field(): void {
		$field_calls = array();

		WP_Mock::userFunction( 'register_setting' )->andReturn( null );
		WP_Mock::userFunction( 'add_settings_section' )->andReturn( null );

		WP_Mock::userFunction( 'add_settings_field' )
			->andReturnUsing( static function () use ( &$field_calls ) {
				$field_calls[] = func_get_args();
			} );

		Settings::make( 'g', 'o' )
			->page( 'my-page' )
			->section( 'general', 'General', static function ( Section $s ) {
				$s->field( 'api_key', 'API Key' );
				$s->field( 'debug', 'Debug Mode' );
			} )
			->register();

		$this->assertCount( 2, $field_calls );
		$this->assertSame( 'api_key', $field_calls[0][0] );
		$this->assertSame( 'API Key', $field_calls[0][1] );
		$this->assertSame( 'my-page', $field_calls[0][3] );
		$this->assertSame( 'general', $field_calls[0][4] );

		$this->assertSame( 'debug', $field_calls[1][0] );
		$this->assertSame( 'Debug Mode', $field_calls[1][1] );
	}

	public function test_register_includes_sanitize_callback_when_set(): void {
		$calls = array();

		WP_Mock::userFunction( 'register_setting' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		WP_Mock::userFunction( 'add_settings_section' )->andReturn( null );

		$sanitizer = static function ( $value ) { return $value; };

		Settings::make( 'g', 'o' )
			->sanitize( $sanitizer )
			->register();

		$this->assertArrayHasKey( 'sanitize_callback', $calls[0][2] );
		$this->assertSame( $sanitizer, $calls[0][2]['sanitize_callback'] );
	}

	public function test_register_without_sanitize_passes_empty_args(): void {
		$calls = array();

		WP_Mock::userFunction( 'register_setting' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
			} );

		WP_Mock::userFunction( 'add_settings_section' )->andReturn( null );

		Settings::make( 'g', 'o' )->register();

		$this->assertSame( array(), $calls[0][2] );
	}
}
