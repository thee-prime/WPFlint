<?php

declare(strict_types=1);

namespace WPFlint\Tests\Http;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Http\Middleware\CheckCapability;
use WPFlint\Http\Middleware\ThrottleRequests;
use WPFlint\Http\Middleware\VerifyNonce;
use WPFlint\Http\Request;
use WPFlint\Http\Response;

/**
 * @covers \WPFlint\Http\Middleware\VerifyNonce
 * @covers \WPFlint\Http\Middleware\CheckCapability
 * @covers \WPFlint\Http\Middleware\ThrottleRequests
 */
class MiddlewareTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// VerifyNonce
	// ---------------------------------------------------------------

	public function testVerifyNoncePassesOnValid(): void {
		WP_Mock::userFunction( 'check_ajax_referer', array(
			'args'   => array( 'save_order', '_wpnonce', false ),
			'return' => 1,
		) );

		$middleware = new VerifyNonce( 'save_order' );
		$request    = new Request();
		$called     = false;

		$result = $middleware->handle( $request, function ( $req ) use ( &$called ) {
			$called = true;
			return Response::json( array( 'ok' => true ) );
		} );

		$this->assertTrue( $called );
		$this->assertInstanceOf( Response::class, $result );
	}

	public function testVerifyNonceReturnsErrorOnInvalid(): void {
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'check_ajax_referer', array(
			'args'   => array( 'save_order', '_wpnonce', false ),
			'return' => false,
		) );

		$middleware = new VerifyNonce( 'save_order' );
		$request    = new Request();

		$result = $middleware->handle( $request, function () {
			return Response::json();
		} );

		$this->assertInstanceOf( Response::class, $result );
		$this->assertSame( 403, $result->get_status() );
	}

	// ---------------------------------------------------------------
	// CheckCapability
	// ---------------------------------------------------------------

	public function testCheckCapabilityPassesWhenAllowed(): void {
		WP_Mock::userFunction( 'current_user_can', array(
			'args'   => array( 'edit_posts' ),
			'return' => true,
		) );

		$middleware = new CheckCapability( 'edit_posts' );
		$request    = new Request();
		$called     = false;

		$middleware->handle( $request, function () use ( &$called ) {
			$called = true;
			return Response::json();
		} );

		$this->assertTrue( $called );
	}

	public function testCheckCapabilityReturnsErrorWhenDenied(): void {
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'current_user_can', array(
			'args'   => array( 'manage_options' ),
			'return' => false,
		) );

		$middleware = new CheckCapability( 'manage_options' );
		$request    = new Request();

		$result = $middleware->handle( $request, function () {
			return Response::json();
		} );

		$this->assertSame( 403, $result->get_status() );
	}

	// ---------------------------------------------------------------
	// ThrottleRequests
	// ---------------------------------------------------------------

	public function testThrottleAllowsUnderLimit(): void {
		WP_Mock::userFunction( 'get_current_user_id', array( 'return' => 1 ) );
		WP_Mock::userFunction( 'get_transient', array( 'return' => 5 ) );
		WP_Mock::userFunction( 'set_transient', array( 'return' => true ) );

		$middleware = new ThrottleRequests( 60, 1 );
		$request    = new Request();
		$called     = false;

		$middleware->handle( $request, function () use ( &$called ) {
			$called = true;
			return Response::json();
		} );

		$this->assertTrue( $called );
	}

	public function testThrottleBlocksOverLimit(): void {
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'get_current_user_id', array( 'return' => 1 ) );
		WP_Mock::userFunction( 'get_transient', array( 'return' => 60 ) );

		$middleware = new ThrottleRequests( 60, 1 );
		$request    = new Request();

		$result = $middleware->handle( $request, function () {
			return Response::json();
		} );

		$this->assertSame( 429, $result->get_status() );
	}

	public function testThrottleUsesIpForGuests(): void {
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'get_current_user_id', array( 'return' => 0 ) );
		WP_Mock::userFunction( 'get_transient', array( 'return' => 0 ) );
		WP_Mock::userFunction( 'set_transient', array( 'return' => true ) );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'wp_unslash' );

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$middleware = new ThrottleRequests( 60, 1 );
		$request    = new Request();
		$called     = false;

		$middleware->handle( $request, function () use ( &$called ) {
			$called = true;
			return Response::json();
		} );

		$this->assertTrue( $called );
		unset( $_SERVER['REMOTE_ADDR'] );
	}
}
