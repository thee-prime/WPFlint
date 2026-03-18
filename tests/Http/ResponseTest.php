<?php

declare(strict_types=1);

namespace WPFlint\Tests\Http;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Http\Response;

/**
 * @covers \WPFlint\Http\Response
 */
class ResponseTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function testJsonCreatesSuccessResponse(): void {
		$response = Response::json( array( 'id' => 1 ), 201 );
		$this->assertSame( array( 'id' => 1 ), $response->get_data() );
		$this->assertSame( 201, $response->get_status() );
	}

	public function testJsonDefaultsTo200(): void {
		$response = Response::json( array( 'ok' => true ) );
		$this->assertSame( 200, $response->get_status() );
	}

	public function testErrorCreatesErrorResponse(): void {
		$response = Response::error( 'Not found', 404 );
		$this->assertSame( array( 'message' => 'Not found' ), $response->get_data() );
		$this->assertSame( 404, $response->get_status() );
	}

	public function testErrorDefaultsTo400(): void {
		$response = Response::error( 'Bad request' );
		$this->assertSame( 400, $response->get_status() );
	}

	public function testNoContentReturns204(): void {
		$response = Response::no_content();
		$this->assertNull( $response->get_data() );
		$this->assertSame( 204, $response->get_status() );
	}

	public function testWithHeaderSetsHeader(): void {
		$response = Response::json()->with_header( 'X-Custom', 'value' );
		$this->assertSame( array( 'X-Custom' => 'value' ), $response->get_headers() );
	}

	public function testIsSuccessfulFor2xx(): void {
		$this->assertTrue( Response::json( null, 200 )->is_successful() );
		$this->assertTrue( Response::json( null, 201 )->is_successful() );
		$this->assertTrue( Response::no_content()->is_successful() );
	}

	public function testIsSuccessfulFalseFor4xx(): void {
		$this->assertFalse( Response::error( 'err', 400 )->is_successful() );
	}

	public function testIsErrorFor4xxAnd5xx(): void {
		$this->assertTrue( Response::error( 'err', 400 )->is_error() );
		$this->assertTrue( Response::error( 'err', 500 )->is_error() );
	}

	public function testIsErrorFalseFor2xx(): void {
		$this->assertFalse( Response::json()->is_error() );
	}
}
