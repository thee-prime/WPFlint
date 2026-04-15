<?php
/**
 * Tests for the Admin Notice builder.
 *
 * @package WPFlint\Tests\Admin
 */

declare(strict_types=1);

namespace WPFlint\Tests\Admin;

use WPFlint\Admin\Notice;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\Admin\Notice
 */
class NoticeTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	// ---------------------------------------------------------------
	// Static factories
	// ---------------------------------------------------------------

	public function test_success_factory(): void {
		$notice = Notice::success( 'All good.' );
		$this->assertSame( Notice::SUCCESS, $notice->get_type() );
		$this->assertSame( 'All good.', $notice->get_message() );
	}

	public function test_error_factory(): void {
		$notice = Notice::error( 'Something broke.' );
		$this->assertSame( Notice::ERROR, $notice->get_type() );
	}

	public function test_warning_factory(): void {
		$notice = Notice::warning( 'Watch out.' );
		$this->assertSame( Notice::WARNING, $notice->get_type() );
	}

	public function test_info_factory(): void {
		$notice = Notice::info( 'FYI.' );
		$this->assertSame( Notice::INFO, $notice->get_type() );
	}

	// ---------------------------------------------------------------
	// dismissible()
	// ---------------------------------------------------------------

	public function test_not_dismissible_by_default(): void {
		$notice = Notice::success( 'OK' );
		$this->assertFalse( $notice->is_dismissible() );
	}

	public function test_dismissible_setter(): void {
		$notice = Notice::success( 'OK' )->dismissible();
		$this->assertTrue( $notice->is_dismissible() );
	}

	public function test_dismissible_can_be_disabled(): void {
		$notice = Notice::success( 'OK' )->dismissible( false );
		$this->assertFalse( $notice->is_dismissible() );
	}

	public function test_dismissible_returns_self(): void {
		$notice = Notice::success( 'OK' );
		$this->assertSame( $notice, $notice->dismissible() );
	}

	// ---------------------------------------------------------------
	// render()
	// ---------------------------------------------------------------

	public function test_render_contains_notice_type_class(): void {
		WP_Mock::userFunction( 'esc_attr' )->andReturnUsing( static function ( $v ) { return $v; } );
		WP_Mock::userFunction( 'wp_kses_post' )->andReturnUsing( static function ( $v ) { return $v; } );

		$html = Notice::success( 'Saved.' )->render();

		$this->assertStringContainsString( 'notice-success', $html );
		$this->assertStringContainsString( 'Saved.', $html );
	}

	public function test_render_contains_is_dismissible_class_when_set(): void {
		WP_Mock::userFunction( 'esc_attr' )->andReturnUsing( static function ( $v ) { return $v; } );
		WP_Mock::userFunction( 'wp_kses_post' )->andReturnUsing( static function ( $v ) { return $v; } );

		$html = Notice::error( 'Oops.' )->dismissible()->render();

		$this->assertStringContainsString( 'is-dismissible', $html );
	}

	public function test_render_no_dismissible_class_when_not_set(): void {
		WP_Mock::userFunction( 'esc_attr' )->andReturnUsing( static function ( $v ) { return $v; } );
		WP_Mock::userFunction( 'wp_kses_post' )->andReturnUsing( static function ( $v ) { return $v; } );

		$html = Notice::info( 'Info.' )->render();

		$this->assertStringNotContainsString( 'is-dismissible', $html );
	}

	public function test_render_wraps_in_div_with_notice_class(): void {
		WP_Mock::userFunction( 'esc_attr' )->andReturnUsing( static function ( $v ) { return $v; } );
		WP_Mock::userFunction( 'wp_kses_post' )->andReturnUsing( static function ( $v ) { return $v; } );

		$html = Notice::warning( 'Careful.' )->render();

		$this->assertStringContainsString( '<div class="notice', $html );
		$this->assertStringContainsString( '</div>', $html );
	}

	// ---------------------------------------------------------------
	// flash()
	// ---------------------------------------------------------------

	public function test_flash_stores_in_transient(): void {
		$transient_set = array();

		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 1 );
		WP_Mock::userFunction( 'get_transient' )->andReturn( false );
		WP_Mock::userFunction( 'set_transient' )
			->andReturnUsing( static function ( $key, $value ) use ( &$transient_set ) {
				$transient_set[ $key ] = $value;
				return true;
			} );

		// add_action is called by flash() to register the display hook.
		// We silence it here; the hook registration is tested separately.
		WP_Mock::userFunction( 'add_action' )->andReturn( null );

		Notice::success( 'Saved.' )->flash();

		$key = Notice::FLASH_TRANSIENT_PREFIX . '1';
		$this->assertArrayHasKey( $key, $transient_set );
		$this->assertCount( 1, $transient_set[ $key ] );
		$this->assertSame( 'success', $transient_set[ $key ][0]['type'] );
		$this->assertSame( 'Saved.', $transient_set[ $key ][0]['message'] );
	}

	public function test_flash_stores_dismissible_flag(): void {
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 1 );
		WP_Mock::userFunction( 'get_transient' )->andReturn( false );

		$transient_set = array();
		WP_Mock::userFunction( 'set_transient' )
			->andReturnUsing( static function ( $key, $value ) use ( &$transient_set ) {
				$transient_set[ $key ] = $value;
				return true;
			} );
		WP_Mock::userFunction( 'add_action' )->andReturn( null );

		Notice::success( 'Saved.' )->dismissible()->flash();

		$key = Notice::FLASH_TRANSIENT_PREFIX . '1';
		$this->assertTrue( $transient_set[ $key ][0]['dismissible'] );
	}

	public function test_flash_accumulates_existing_notices(): void {
		$existing = array(
			array( 'type' => 'info', 'message' => 'First.', 'dismissible' => false ),
		);

		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 1 );
		WP_Mock::userFunction( 'get_transient' )->andReturn( $existing );

		$transient_set = array();
		WP_Mock::userFunction( 'set_transient' )
			->andReturnUsing( static function ( $key, $value ) use ( &$transient_set ) {
				$transient_set[ $key ] = $value;
				return true;
			} );
		WP_Mock::userFunction( 'add_action' )->andReturn( null );

		Notice::error( 'Second.' )->flash();

		$key = Notice::FLASH_TRANSIENT_PREFIX . '1';
		$this->assertCount( 2, $transient_set[ $key ] );
	}

	// ---------------------------------------------------------------
	// display_flash()
	// ---------------------------------------------------------------

	public function test_display_flash_outputs_notices_and_deletes_transient(): void {
		$existing = array(
			array( 'type' => 'success', 'message' => 'Done.', 'dismissible' => false ),
		);

		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 1 );
		WP_Mock::userFunction( 'get_transient' )->andReturn( $existing );
		WP_Mock::userFunction( 'esc_attr' )->andReturnUsing( static function ( $v ) { return $v; } );
		WP_Mock::userFunction( 'wp_kses_post' )->andReturnUsing( static function ( $v ) { return $v; } );

		$deleted = array();
		WP_Mock::userFunction( 'delete_transient' )
			->andReturnUsing( static function ( $key ) use ( &$deleted ) {
				$deleted[] = $key;
				return true;
			} );

		ob_start();
		Notice::display_flash();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Done.', $output );
		$this->assertContains( Notice::FLASH_TRANSIENT_PREFIX . '1', $deleted );
	}

	public function test_display_flash_does_nothing_when_no_transient(): void {
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 1 );
		WP_Mock::userFunction( 'get_transient' )->andReturn( false );
		WP_Mock::userFunction( 'delete_transient' )->never();

		ob_start();
		Notice::display_flash();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	// ---------------------------------------------------------------
	// persistent()
	// ---------------------------------------------------------------

	public function test_persistent_stores_in_option(): void {
		$options_set = array();

		WP_Mock::userFunction( 'update_option' )
			->andReturnUsing( static function ( $key, $value ) use ( &$options_set ) {
				$options_set[ $key ] = $value;
				return true;
			} );

		// add_action registers the admin_notices display hook.
		WP_Mock::userFunction( 'add_action' )->andReturn( null );

		Notice::error( 'Invalid key.' )->persistent( 'my_plugin_api_error' );

		$key = Notice::PERSISTENT_OPTION_PREFIX . 'my_plugin_api_error';
		$this->assertArrayHasKey( $key, $options_set );
		$this->assertSame( 'error', $options_set[ $key ]['type'] );
		$this->assertSame( 'Invalid key.', $options_set[ $key ]['message'] );
	}

	// ---------------------------------------------------------------
	// display_persistent()
	// ---------------------------------------------------------------

	public function test_display_persistent_outputs_stored_notice(): void {
		WP_Mock::userFunction( 'get_option' )->andReturn(
			array( 'type' => 'error', 'message' => 'API error.', 'dismissible' => true )
		);
		WP_Mock::userFunction( 'esc_attr' )->andReturnUsing( static function ( $v ) { return $v; } );
		WP_Mock::userFunction( 'wp_kses_post' )->andReturnUsing( static function ( $v ) { return $v; } );

		ob_start();
		Notice::display_persistent( 'my_key' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'API error.', $output );
		$this->assertStringContainsString( 'is-dismissible', $output );
	}

	public function test_display_persistent_does_nothing_when_no_option(): void {
		WP_Mock::userFunction( 'get_option' )->andReturn( false );

		ob_start();
		Notice::display_persistent( 'missing_key' );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	// ---------------------------------------------------------------
	// dismiss()
	// ---------------------------------------------------------------

	public function test_dismiss_deletes_option(): void {
		$deleted = array();

		WP_Mock::userFunction( 'delete_option' )
			->andReturnUsing( static function ( $key ) use ( &$deleted ) {
				$deleted[] = $key;
				return true;
			} );

		Notice::dismiss( 'my_plugin_api_error' );

		$this->assertContains( Notice::PERSISTENT_OPTION_PREFIX . 'my_plugin_api_error', $deleted );
	}
}
