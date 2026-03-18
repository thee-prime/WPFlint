<?php

declare(strict_types=1);

namespace WPFlint\Tests\Http;

use Closure;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Http\Middleware\MiddlewareInterface;
use WPFlint\Http\Pipeline;
use WPFlint\Http\Request;
use WPFlint\Http\Response;

/**
 * @covers \WPFlint\Http\Pipeline
 */
class PipelineTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function testPipelineCallsDestinationWithNoMiddleware(): void {
		$pipeline = new Pipeline();
		$request  = new Request( array( 'test' => 1 ) );

		$result = $pipeline
			->send( $request )
			->through( array() )
			->then( function ( Request $req ) {
				return Response::json( $req->all() );
			} );

		$this->assertInstanceOf( Response::class, $result );
		$this->assertSame( array( 'test' => 1 ), $result->get_data() );
	}

	public function testPipelineExecutesMiddlewareInOrder(): void {
		$log = array();

		$mw1 = new class( $log ) implements MiddlewareInterface {
			private $log;
			public function __construct( &$log ) {
				$this->log = &$log;
			}
			public function handle( Request $request, Closure $next ) {
				$this->log[] = 'mw1_before';
				$result = $next( $request );
				$this->log[] = 'mw1_after';
				return $result;
			}
		};

		$mw2 = new class( $log ) implements MiddlewareInterface {
			private $log;
			public function __construct( &$log ) {
				$this->log = &$log;
			}
			public function handle( Request $request, Closure $next ) {
				$this->log[] = 'mw2_before';
				$result = $next( $request );
				$this->log[] = 'mw2_after';
				return $result;
			}
		};

		$pipeline = new Pipeline();
		$request  = new Request();

		$pipeline
			->send( $request )
			->through( array( $mw1, $mw2 ) )
			->then( function () use ( &$log ) {
				$log[] = 'destination';
				return Response::json();
			} );

		$this->assertSame(
			array( 'mw1_before', 'mw2_before', 'destination', 'mw2_after', 'mw1_after' ),
			$log
		);
	}

	public function testPipelineMiddlewareCanShortCircuit(): void {
		WP_Mock::passthruFunction( '__' );

		$blocker = new class implements MiddlewareInterface {
			public function handle( Request $request, Closure $next ) {
				return Response::error( 'Blocked', 403 );
			}
		};

		$pipeline = new Pipeline();
		$request  = new Request();
		$reached  = false;

		$result = $pipeline
			->send( $request )
			->through( array( $blocker ) )
			->then( function () use ( &$reached ) {
				$reached = true;
				return Response::json();
			} );

		$this->assertFalse( $reached );
		$this->assertSame( 403, $result->get_status() );
	}
}
