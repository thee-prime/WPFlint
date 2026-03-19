# Events

WPFlint provides a typed event system with a Dispatcher, support for closures, class-based listeners, and WordPress hook bridging.

## Event Base Class

All events extend the abstract `Event` class:

```php
use WPFlint\Events\Event;

class OrderPlaced extends Event {
    public int $order_id;
    public float $total;

    public function __construct( int $order_id, float $total ) {
        $this->order_id = $order_id;
        $this->total    = $total;
    }
}
```

Events support propagation control:

```php
$event->stop_propagation();       // stop further listeners
$event->is_propagation_stopped(); // check
```

## Dispatcher

### Registering Listeners

```php
use WPFlint\Events\Dispatcher;

$dispatcher = new Dispatcher( $container );

// Closure listener
$dispatcher->listen( OrderPlaced::class, function ( OrderPlaced $event ) {
    log_order( $event->order_id );
} );

// Array callable
$dispatcher->listen( OrderPlaced::class, array( $handler, 'on_order' ) );

// Class name — resolved from container, must have handle() method
$dispatcher->listen( OrderPlaced::class, SendConfirmation::class );
```

Class-based listeners must implement a `handle()` method:

```php
class SendConfirmation {
    public function handle( OrderPlaced $event ): void {
        // send email...
    }
}
```

### Firing Events

```php
$event = new OrderPlaced( 42, 99.50 );
$dispatcher->fire( $event );
```

`fire()` returns the event instance, which listeners may have modified.

### Removing Listeners

```php
$dispatcher->forget( OrderPlaced::class );
```

### Checking Listeners

```php
$dispatcher->has_listeners( OrderPlaced::class ); // bool
$dispatcher->get_listeners( OrderPlaced::class ); // array
```

## WordPress Hook Bridge

Bridge native WordPress hooks to typed events:

```php
$dispatcher->listen_wp( 'save_post', PostSaved::class, 10, 2 );

// When save_post fires, PostSaved is constructed with the hook args
// and dispatched through all registered listeners.
```

```php
class PostSaved extends Event {
    public int $post_id;
    public \WP_Post $post;

    public function __construct( int $post_id, \WP_Post $post ) {
        $this->post_id = $post_id;
        $this->post    = $post;
    }
}

$dispatcher->listen( PostSaved::class, function ( PostSaved $event ) {
    // Typed access to $event->post_id, $event->post
} );
```

## Event Facade

```php
use WPFlint\Facades\Event;

Event::listen( OrderPlaced::class, SendConfirmation::class );
Event::fire( new OrderPlaced( $order_id, $total ) );
Event::forget( OrderPlaced::class );
Event::has_listeners( OrderPlaced::class );
Event::listen_wp( 'save_post', PostSaved::class );
```

## EventServiceProvider

Registers the Dispatcher as a deferred singleton:

```php
use WPFlint\Events\EventServiceProvider;

$app->register( EventServiceProvider::class );
```

Binds both `'events'` and `Dispatcher::class` to the same singleton instance.
