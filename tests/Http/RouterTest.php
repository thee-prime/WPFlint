<?php

declare(strict_types=1);

namespace WPFlint\Tests\Http;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Container\Container;
use WPFlint\Http\Response;
use WPFlint\Http\RestRouter;
use WPFlint\Http\Route;
use WPFlint\Http\Router;

/**
 * @covers \WPFlint\Http\Router
 * @covers \WPFlint\Http\Route
 * @covers \WPFlint\Http\RestRouter
 */
class RouterTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Route
	// ---------------------------------------------------------------

	public function testRouteStoresActionAndHandler(): void {
		$route = new Route( 'my_action', array( 'Controller', 'method' ) );
		$this->assertSame( 'my_action', $route->get_action() );
		$this->assertSame( array( 'Controller', 'method' ), $route->get_handler() );
	}

	public function testRouteMiddlewareChaining(): void {
		$route = new Route( 'act', array( 'C', 'm' ) );
		$route->middleware( array( 'nonce:save' ) )->middleware( array( 'can:edit_posts' ) );

		$this->assertSame( array( 'nonce:save', 'can:edit_posts' ), $route->get_middleware() );
	}

	public function testRouteNoprivDefault(): void {
		$route = new Route( 'act', array( 'C', 'm' ) );
		$this->assertFalse( $route->is_nopriv() );
	}

	public function testRouteNoprivCanBeSet(): void {
		$route = new Route( 'act', array( 'C', 'm' ) );
		$route->nopriv();
		$this->assertTrue( $route->is_nopriv() );
	}

	// ---------------------------------------------------------------
	// Router — AJAX registration
	// ---------------------------------------------------------------

	public function testAjaxRegistersRoute(): void {
		$container = new Container();
		$router    = new Router( $container );

		$route = $router->ajax( 'test_action', array( 'TestCtrl', 'index' ) );

		$this->assertInstanceOf( Route::class, $route );
		$this->assertCount( 1, $router->get_routes() );
	}

	public function testBootRegistersAjaxHooks(): void {
		$container = new Container();
		$router    = new Router( $container );

		$router->ajax( 'test_action', array( 'TestCtrl', 'index' ) );

		WP_Mock::expectActionAdded( 'wp_ajax_test_action', WP_Mock\Functions::type( 'Closure' ) );

		$router->boot();
		$this->assertConditionsMet();
	}

	public function testBootRegistersNoprivHook(): void {
		$container = new Container();
		$router    = new Router( $container );

		$router->ajax( 'public_action', array( 'TestCtrl', 'index' ) )->nopriv();

		WP_Mock::expectActionAdded( 'wp_ajax_public_action', WP_Mock\Functions::type( 'Closure' ) );
		WP_Mock::expectActionAdded( 'wp_ajax_nopriv_public_action', WP_Mock\Functions::type( 'Closure' ) );

		$router->boot();
		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// Router — REST registration
	// ---------------------------------------------------------------

	public function testRestRegistersGroup(): void {
		$container = new Container();
		$router    = new Router( $container );

		$router->rest( 'my-plugin/v1', function ( RestRouter $r ) {
			$r->get( '/orders', function () {} );
		} );

		$this->assertCount( 1, $router->get_rest_groups() );
	}

	public function testBootRegistersRestApiInitHook(): void {
		$container = new Container();
		$router    = new Router( $container );

		$router->rest( 'my-plugin/v1', function ( RestRouter $r ) {
			$r->get( '/orders', function () {} );
		} );

		WP_Mock::expectActionAdded( 'rest_api_init', array( $router, 'register_rest_routes' ) );

		$router->boot();
		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// Router — middleware alias
	// ---------------------------------------------------------------

	public function testAliasMiddleware(): void {
		$container = new Container();
		$router    = new Router( $container );
		$router->alias_middleware( 'store.open', 'App\\Middleware\\StoreOpen' );

		// No assertion needed — just verifying no error.
		// The alias is used internally by resolve_middleware.
		$this->assertTrue( true );
	}

	// ---------------------------------------------------------------
	// RestRouter
	// ---------------------------------------------------------------

	public function testRestRouterCollectsRoutes(): void {
		$rest = new RestRouter( 'my-plugin/v1' );
		$rest->get( '/orders', function () {} );
		$rest->post( '/orders', function () {} );
		$rest->put( '/orders/1', function () {} );
		$rest->delete( '/orders/1', function () {} );
		$rest->patch( '/orders/1', function () {} );

		$this->assertCount( 5, $rest->get_routes() );
		$this->assertSame( 'my-plugin/v1', $rest->get_namespace() );
	}

	public function testRestRouterRouteMethods(): void {
		$rest = new RestRouter( 'ns/v1' );
		$rest->get( '/a', function () {} );
		$rest->post( '/b', function () {} );

		$routes = $rest->get_routes();
		$this->assertSame( 'GET', $routes[0]['method'] );
		$this->assertSame( 'POST', $routes[1]['method'] );
	}
}
