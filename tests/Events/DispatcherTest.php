<?php

declare(strict_types=1);

namespace WPFlint\Tests\Events;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Container\Container;
use WPFlint\Events\Dispatcher;
use WPFlint\Events\Event;

/**
 * @covers \WPFlint\Events\Dispatcher
 * @covers \WPFlint\Events\Event
 */
class DispatcherTest extends TestCase {

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
	// listen + fire with Closure
	// ---------------------------------------------------------------

	public function testListenAndFireWithClosure(): void {
		$dispatcher = new Dispatcher();
		$called     = false;

		$dispatcher->listen( StubOrderPlaced::class, function ( StubOrderPlaced $event ) use ( &$called ) {
			$called = true;
		} );

		$dispatcher->fire( new StubOrderPlaced( 42 ) );

		$this->assertTrue( $called );
	}

	public function testFirePassesEventToListener(): void {
		$dispatcher = new Dispatcher();
		$received   = null;

		$dispatcher->listen( StubOrderPlaced::class, function ( StubOrderPlaced $event ) use ( &$received ) {
			$received = $event->order_id;
		} );

		$dispatcher->fire( new StubOrderPlaced( 99 ) );

		$this->assertSame( 99, $received );
	}

	// ---------------------------------------------------------------
	// listen + fire with array callable
	// ---------------------------------------------------------------

	public function testListenAndFireWithArrayCallable(): void {
		$dispatcher = new Dispatcher();
		$handler    = new StubEventHandler();

		$dispatcher->listen( StubOrderPlaced::class, array( $handler, 'on_order' ) );
		$dispatcher->fire( new StubOrderPlaced( 10 ) );

		$this->assertSame( 10, $handler->last_order_id );
	}

	// ---------------------------------------------------------------
	// listen + fire with class name (container resolution)
	// ---------------------------------------------------------------

	public function testListenAndFireWithClassName(): void {
		$container  = new Container();
		$dispatcher = new Dispatcher( $container );

		$dispatcher->listen( StubOrderPlaced::class, StubClassListener::class );
		$dispatcher->fire( new StubOrderPlaced( 77 ) );

		$this->assertSame( 77, StubClassListener::$last_order_id );

		// Reset static state.
		StubClassListener::$last_order_id = null;
	}

	public function testClassListenerResolvedWithoutContainer(): void {
		$dispatcher = new Dispatcher();

		$dispatcher->listen( StubOrderPlaced::class, StubClassListener::class );
		$dispatcher->fire( new StubOrderPlaced( 55 ) );

		$this->assertSame( 55, StubClassListener::$last_order_id );

		StubClassListener::$last_order_id = null;
	}

	// ---------------------------------------------------------------
	// Multiple listeners
	// ---------------------------------------------------------------

	public function testMultipleListenersCalled(): void {
		$dispatcher = new Dispatcher();
		$log        = array();

		$dispatcher->listen( StubOrderPlaced::class, function () use ( &$log ) {
			$log[] = 'first';
		} );

		$dispatcher->listen( StubOrderPlaced::class, function () use ( &$log ) {
			$log[] = 'second';
		} );

		$dispatcher->fire( new StubOrderPlaced( 1 ) );

		$this->assertSame( array( 'first', 'second' ), $log );
	}

	// ---------------------------------------------------------------
	// Propagation stopped
	// ---------------------------------------------------------------

	public function testStopPropagationPreventsSubsequentListeners(): void {
		$dispatcher = new Dispatcher();
		$log        = array();

		$dispatcher->listen( StubOrderPlaced::class, function ( StubOrderPlaced $event ) use ( &$log ) {
			$log[] = 'first';
			$event->stop_propagation();
		} );

		$dispatcher->listen( StubOrderPlaced::class, function () use ( &$log ) {
			$log[] = 'second';
		} );

		$dispatcher->fire( new StubOrderPlaced( 1 ) );

		$this->assertSame( array( 'first' ), $log );
	}

	public function testIsPropagationStoppedDefaultFalse(): void {
		$event = new StubOrderPlaced( 1 );
		$this->assertFalse( $event->is_propagation_stopped() );
	}

	public function testStopPropagationSetsFlag(): void {
		$event = new StubOrderPlaced( 1 );
		$event->stop_propagation();
		$this->assertTrue( $event->is_propagation_stopped() );
	}

	// ---------------------------------------------------------------
	// forget
	// ---------------------------------------------------------------

	public function testForgetRemovesListeners(): void {
		$dispatcher = new Dispatcher();
		$called     = false;

		$dispatcher->listen( StubOrderPlaced::class, function () use ( &$called ) {
			$called = true;
		} );

		$dispatcher->forget( StubOrderPlaced::class );
		$dispatcher->fire( new StubOrderPlaced( 1 ) );

		$this->assertFalse( $called );
	}

	// ---------------------------------------------------------------
	// has_listeners
	// ---------------------------------------------------------------

	public function testHasListenersReturnsTrue(): void {
		$dispatcher = new Dispatcher();
		$dispatcher->listen( StubOrderPlaced::class, function () {} );

		$this->assertTrue( $dispatcher->has_listeners( StubOrderPlaced::class ) );
	}

	public function testHasListenersReturnsFalse(): void {
		$dispatcher = new Dispatcher();
		$this->assertFalse( $dispatcher->has_listeners( StubOrderPlaced::class ) );
	}

	// ---------------------------------------------------------------
	// get_listeners
	// ---------------------------------------------------------------

	public function testGetListenersReturnsRegistered(): void {
		$dispatcher = new Dispatcher();
		$closure    = function () {};

		$dispatcher->listen( StubOrderPlaced::class, $closure );

		$this->assertCount( 1, $dispatcher->get_listeners( StubOrderPlaced::class ) );
	}

	public function testGetListenersReturnsEmptyForUnknown(): void {
		$dispatcher = new Dispatcher();
		$this->assertSame( array(), $dispatcher->get_listeners( 'Unknown\\Event' ) );
	}

	// ---------------------------------------------------------------
	// fire returns event
	// ---------------------------------------------------------------

	public function testFireReturnsEvent(): void {
		$dispatcher = new Dispatcher();
		$event      = new StubOrderPlaced( 42 );

		$result = $dispatcher->fire( $event );

		$this->assertSame( $event, $result );
	}

	public function testFireWithNoListenersReturnsEvent(): void {
		$dispatcher = new Dispatcher();
		$event      = new StubOrderPlaced( 42 );

		$result = $dispatcher->fire( $event );

		$this->assertSame( $event, $result );
	}

	// ---------------------------------------------------------------
	// listen_wp — bridge WordPress hooks
	// ---------------------------------------------------------------

	public function testListenWpRegistersAction(): void {
		$dispatcher = new Dispatcher();

		WP_Mock::expectActionAdded( 'save_post', WP_Mock\Functions::type( 'Closure' ), 10, 1 );

		$dispatcher->listen_wp( 'save_post', StubPostSaved::class );

		$this->assertConditionsMet();
	}

	public function testListenWpWithCustomPriorityAndArgs(): void {
		$dispatcher = new Dispatcher();

		WP_Mock::expectActionAdded( 'save_post', WP_Mock\Functions::type( 'Closure' ), 20, 2 );

		$dispatcher->listen_wp( 'save_post', StubPostSaved::class, 20, 2 );

		$this->assertConditionsMet();
	}
}

// ---------------------------------------------------------------
// Test event stubs
// ---------------------------------------------------------------

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

class StubOrderPlaced extends Event {

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	public int $order_id;

	/**
	 * Constructor.
	 *
	 * @param int $order_id Order ID.
	 */
	public function __construct( int $order_id ) {
		$this->order_id = $order_id;
	}
}

class StubPostSaved extends Event {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public int $post_id;

	/**
	 * Constructor.
	 *
	 * @param int $post_id Post ID.
	 */
	public function __construct( int $post_id = 0 ) {
		$this->post_id = $post_id;
	}
}

class StubEventHandler {

	/**
	 * Last order ID received.
	 *
	 * @var int|null
	 */
	public ?int $last_order_id = null;

	/**
	 * Handle the event.
	 *
	 * @param StubOrderPlaced $event The event.
	 */
	public function on_order( StubOrderPlaced $event ): void {
		$this->last_order_id = $event->order_id;
	}
}

class StubClassListener {

	/**
	 * Last order ID received (static for inspection).
	 *
	 * @var int|null
	 */
	public static ?int $last_order_id = null;

	/**
	 * Handle the event.
	 *
	 * @param StubOrderPlaced $event The event.
	 */
	public function handle( StubOrderPlaced $event ): void {
		static::$last_order_id = $event->order_id;
	}
}

// phpcs:enable
