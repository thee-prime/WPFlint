<?php
/**
 * Tests for the RestAuth helper.
 *
 * @package WPFlint\Tests\Http
 */

declare(strict_types=1);

namespace WPFlint\Tests\Http;

use WPFlint\Http\RestAuth;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\Http\RestAuth
 */
class RestAuthTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	// ---------------------------------------------------------------
	// capability()
	// ---------------------------------------------------------------

	public function test_capability_returns_callable(): void {
		$cb = RestAuth::capability( 'manage_options' );
		$this->assertIsCallable( $cb );
	}

	public function test_capability_callable_returns_true_when_user_has_cap(): void {
		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

		$cb = RestAuth::capability( 'manage_options' );
		$this->assertTrue( $cb() );
	}

	public function test_capability_callable_returns_false_when_user_lacks_cap(): void {
		WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( false );

		$cb = RestAuth::capability( 'manage_options' );
		$this->assertFalse( $cb() );
	}

	// ---------------------------------------------------------------
	// logged_in()
	// ---------------------------------------------------------------

	public function test_logged_in_returns_callable(): void {
		$cb = RestAuth::logged_in();
		$this->assertIsCallable( $cb );
	}

	public function test_logged_in_callable_returns_true_when_authenticated(): void {
		WP_Mock::userFunction( 'is_user_logged_in' )->andReturn( true );

		$cb = RestAuth::logged_in();
		$this->assertTrue( $cb() );
	}

	public function test_logged_in_callable_returns_false_when_not_authenticated(): void {
		WP_Mock::userFunction( 'is_user_logged_in' )->andReturn( false );

		$cb = RestAuth::logged_in();
		$this->assertFalse( $cb() );
	}

	// ---------------------------------------------------------------
	// public_access()
	// ---------------------------------------------------------------

	public function test_public_access_returns_callable(): void {
		$cb = RestAuth::public_access();
		$this->assertIsCallable( $cb );
	}

	public function test_public_access_callable_always_returns_true(): void {
		$cb = RestAuth::public_access();
		$this->assertTrue( $cb() );
	}

	// ---------------------------------------------------------------
	// all_of()
	// ---------------------------------------------------------------

	public function test_all_of_returns_callable(): void {
		$cb = RestAuth::all_of( 'edit_posts', 'upload_files' );
		$this->assertIsCallable( $cb );
	}

	public function test_all_of_returns_true_when_user_has_all_caps(): void {
		WP_Mock::userFunction( 'current_user_can' )->andReturn( true );

		$cb = RestAuth::all_of( 'edit_posts', 'upload_files' );
		$this->assertTrue( $cb() );
	}

	public function test_all_of_returns_false_when_user_lacks_one_cap(): void {
		WP_Mock::userFunction( 'current_user_can' )
			->andReturnUsing( static function ( $cap ) {
				return 'edit_posts' === $cap;
			} );

		$cb = RestAuth::all_of( 'edit_posts', 'upload_files' );
		$this->assertFalse( $cb() );
	}

	// ---------------------------------------------------------------
	// any_of()
	// ---------------------------------------------------------------

	public function test_any_of_returns_callable(): void {
		$cb = RestAuth::any_of( 'edit_posts', 'edit_pages' );
		$this->assertIsCallable( $cb );
	}

	public function test_any_of_returns_true_when_user_has_one_cap(): void {
		WP_Mock::userFunction( 'current_user_can' )
			->andReturnUsing( static function ( $cap ) {
				return 'edit_pages' === $cap;
			} );

		$cb = RestAuth::any_of( 'edit_posts', 'edit_pages' );
		$this->assertTrue( $cb() );
	}

	public function test_any_of_returns_false_when_user_has_no_caps(): void {
		WP_Mock::userFunction( 'current_user_can' )->andReturn( false );

		$cb = RestAuth::any_of( 'edit_posts', 'edit_pages' );
		$this->assertFalse( $cb() );
	}

	// ---------------------------------------------------------------
	// Direct boolean checks
	// ---------------------------------------------------------------

	public function test_require_logged_in_delegates_to_is_user_logged_in(): void {
		WP_Mock::userFunction( 'is_user_logged_in' )->andReturn( true );

		$this->assertTrue( RestAuth::require_logged_in() );
	}

	public function test_require_capability_delegates_to_current_user_can(): void {
		WP_Mock::userFunction( 'current_user_can' )
			->with( 'edit_posts' )
			->andReturn( false );

		$this->assertFalse( RestAuth::require_capability( 'edit_posts' ) );
	}

	// ---------------------------------------------------------------
	// namespace()
	// ---------------------------------------------------------------

	public function test_namespace_builds_versioned_string(): void {
		$ns = RestAuth::namespace( 'my-plugin', 1 );
		$this->assertSame( 'my-plugin/v1', $ns );
	}

	public function test_namespace_default_version_is_1(): void {
		$ns = RestAuth::namespace( 'my-plugin' );
		$this->assertSame( 'my-plugin/v1', $ns );
	}

	public function test_namespace_respects_higher_versions(): void {
		$ns = RestAuth::namespace( 'my-plugin', 3 );
		$this->assertSame( 'my-plugin/v3', $ns );
	}
}
