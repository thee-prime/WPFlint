# WPFlint

A Laravel-inspired framework for building WordPress plugins. Zero production dependencies. PHP 7.4+.

WPFlint gives you the tools you expect from a modern PHP framework — IoC container, Eloquent-style ORM, migrations, routing, middleware, validation, events, caching, and facades — all built on top of WordPress APIs and fully compliant with WP.org plugin guidelines.

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Architecture Overview](#architecture-overview)
- [Application](#application)
- [Service Container](#service-container)
- [Service Providers](#service-providers)
- [Configuration](#configuration)
- [Routing](#routing)
- [Middleware](#middleware)
- [Controllers](#controllers)
- [Requests & Validation](#requests--validation)
- [Responses](#responses)
- [Database: Migrations](#database-migrations)
- [Database: Schema Builder](#database-schema-builder)
- [Database: Query Builder](#database-query-builder)
- [Database: ORM](#database-orm)
- [Relationships](#relationships)
- [Events](#events)
- [Cache](#cache)
- [Facades](#facades)
- [WP-CLI Commands](#wp-cli-commands)
- [Testing](#testing)
- [WP.org Compliance](#wporg-compliance)
- [Directory Structure](#directory-structure)

---

## Installation

```bash
composer require wpflint/wpflint
```

## Quick Start

Create your main plugin file:

```php
<?php
/**
 * Plugin Name: My Shop
 * Text Domain: my-shop
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use WPFlint\Application;

$app = Application::get_instance( __DIR__ );
$app->register( MyShop\Providers\ShopServiceProvider::class );
$app->bootstrap();
```

That's it. WPFlint hooks into the WordPress lifecycle automatically. Your service provider handles the rest.

---

## Architecture Overview

WPFlint follows the service container pattern. The `Application` class extends the IoC container, manages service providers, and hooks into WordPress at the right lifecycle points.

```
Application (extends Container)
    |
    +-- ServiceProviders register bindings
    |       +-- register()   runs on plugins_loaded
    |       +-- boot()       runs on init
    |
    +-- Router dispatches AJAX & REST requests
    +-- Migrator manages database schema
    +-- Dispatcher fires typed events
    +-- CacheManager handles multi-driver caching
```

---

## Application

The `Application` singleton bootstraps the framework and manages the plugin lifecycle.

```php
use WPFlint\Application;

// Get or create the singleton instance.
$app = Application::get_instance( __DIR__ );

// Register service providers.
$app->register( MyServiceProvider::class );

// Bootstrap — hooks into WordPress lifecycle.
$app->bootstrap();
```

### Lifecycle Hooks

When you call `bootstrap()`, WPFlint registers three WordPress actions:

| Hook | Priority | What happens |
|------|----------|-------------|
| `plugins_loaded` | 1 | All providers available for registration |
| `init` | 5 | `boot()` called on all non-deferred providers |
| `wp_loaded` | 10 | Deferred providers registered and booted |

### API

```php
$app = Application::get_instance( $base_path );
Application::clear_instance();                    // reset (for testing)

$app->register( ProviderClass::class );           // register a provider
$app->boot_providers();                           // boot all providers
$app->base_path();                                // plugin root directory
$app->base_path( 'config/app.php' );             // append a path
$app->is_booted();                                // bool
$app->get_providers();                            // registered providers
$app->get_deferred_providers();                   // deferred provider map
```

---

## Service Container

PSR-11 compliant IoC container with auto-resolution, singletons, and contextual bindings.

### Binding

```php
// Simple binding — new instance each time.
$app->bind( LoggerInterface::class, FileLogger::class );

// Singleton — same instance every time.
$app->singleton( CacheManager::class, function ( $app ) {
    return new CacheManager( $app );
} );

// Register an existing instance.
$app->instance( 'config', $configObject );
```

### Resolving

```php
$logger = $app->make( LoggerInterface::class );

// PSR-11 aliases.
$logger = $app->get( LoggerInterface::class );
$app->has( LoggerInterface::class ); // true
```

### Auto-Resolution

Constructor dependencies are resolved automatically via reflection:

```php
class OrderService {
    public function __construct( LoggerInterface $logger, CacheManager $cache ) {}
}

// Both $logger and $cache resolved from the container.
$service = $app->make( OrderService::class );
```

### Contextual Bindings

Give different implementations to different consumers:

```php
$app->when( OrderService::class )
    ->needs( LoggerInterface::class )
    ->give( OrderLogger::class );

$app->when( PaymentService::class )
    ->needs( LoggerInterface::class )
    ->give( PaymentLogger::class );
```

### Error Handling

| Exception | When |
|-----------|------|
| `NotFoundException` | Class does not exist |
| `ContainerException` | Class not instantiable, circular dependency, or unresolvable primitive |

---

## Service Providers

Service providers are the central place to configure your plugin. Each provider has two lifecycle methods: `register()` for binding into the container, and `boot()` for hooking into WordPress.

### Creating a Provider

```php
use WPFlint\Providers\ServiceProvider;

class ShopServiceProvider extends ServiceProvider {

    public function register(): void {
        $this->app->singleton( OrderService::class, function ( $app ) {
            return new OrderService( $app->make( CacheManager::class ) );
        } );
    }

    public function boot(): void {
        // Register routes, event listeners, etc.
    }
}
```

### Deferred Providers

Deferred providers are only registered when one of their provided bindings is actually needed:

```php
class PaymentServiceProvider extends ServiceProvider {

    public bool $defer = true;

    public function register(): void {
        $this->app->singleton( PaymentGateway::class, StripeGateway::class );
    }

    public function provides(): array {
        return array( PaymentGateway::class );
    }
}
```

---

## Configuration

Dot-notation configuration with file-based loading.

### Configuration Files

Place PHP files in `config/` that return arrays. The filename becomes the top-level key:

```php
// config/app.php
return array(
    'name'    => 'My Shop',
    'version' => '1.0.0',
    'debug'   => false,
);
```

### Accessing Configuration

```php
use WPFlint\Facades\Config;

Config::get( 'app.name' );                  // 'My Shop'
Config::get( 'app.missing', 'default' );    // 'default'
Config::set( 'app.debug', true );
Config::has( 'app.name' );                  // true
Config::all();                               // entire config array
Config::push( 'app.middleware', 'throttle' ); // append to array
```

### Environment Helpers

```php
use WPFlint\Config\Repository;

// Checks PHP constants first, then $_ENV, then returns default.
$debug = Repository::env( 'WP_DEBUG', false );
```

---

## Routing

WPFlint provides routing for both WordPress AJAX and REST API endpoints.

### AJAX Routes

```php
use WPFlint\Http\Router;

$router = $app->make( Router::class );

// Logged-in users only (default).
$router->ajax( 'my-shop/save-order', array( OrderController::class, 'store' ) )
    ->middleware( array( 'nonce:save_order', 'can:edit_posts' ) );

// Public route (logged-in + guests).
$router->ajax( 'my-shop/get-products', array( ProductController::class, 'index' ) )
    ->nopriv()
    ->middleware( array( 'throttle:60,1' ) );
```

### REST API Routes

```php
use WPFlint\Http\RestRouter;

$router->rest( 'my-shop/v1', function ( RestRouter $r ) {
    $r->get( '/orders', array( OrderRestController::class, 'index' ) );
    $r->post( '/orders', array( OrderRestController::class, 'store' ) );
    $r->get( '/orders/(?P<id>\d+)', array( OrderRestController::class, 'show' ) );
    $r->put( '/orders/(?P<id>\d+)', array( OrderRestController::class, 'update' ) );
    $r->delete( '/orders/(?P<id>\d+)', array( OrderRestController::class, 'destroy' ) );
} );
```

### Registering Routes

Register routes in your service provider's `boot()` method, then register the `HttpServiceProvider`:

```php
$app->register( \WPFlint\Http\HttpServiceProvider::class );
```

---

## Middleware

Middleware filters HTTP requests before they reach your controller. WPFlint includes three built-in middleware and supports custom middleware.

### Built-in Middleware

**Nonce Verification** — `nonce:{action}`

```php
->middleware( array( 'nonce:save_order' ) )
// Uses check_ajax_referer() to verify the WordPress nonce.
```

**Capability Check** — `can:{capability}`

```php
->middleware( array( 'can:edit_posts' ) )
// Uses current_user_can() to verify permissions.
```

**Rate Limiting** — `throttle:{max},{minutes}`

```php
->middleware( array( 'throttle:60,1' ) )
// 60 requests per minute, tracked via WordPress transients.
```

### Custom Middleware

Implement `MiddlewareInterface`:

```php
use WPFlint\Http\Middleware\MiddlewareInterface;
use WPFlint\Http\Request;
use WPFlint\Http\Response;
use Closure;

class EnsureStoreIsOpen implements MiddlewareInterface {

    public function handle( Request $request, Closure $next ) {
        if ( ! get_option( 'store_open' ) ) {
            return Response::error(
                __( 'Store is closed.', 'my-shop' ),
                403
            );
        }
        return $next( $request );
    }
}
```

Register an alias for cleaner route definitions:

```php
$router->alias_middleware( 'store.open', EnsureStoreIsOpen::class );

// Then use it:
$router->ajax( 'my-shop/checkout', array( CheckoutController::class, 'store' ) )
    ->middleware( array( 'store.open', 'nonce:checkout', 'can:edit_posts' ) );
```

### Pipeline

The Pipeline sends a request through a middleware stack:

```php
use WPFlint\Http\Pipeline;

$result = ( new Pipeline() )
    ->send( $request )
    ->through( array( $middleware1, $middleware2 ) )
    ->then( function ( Request $request ) {
        return Response::json( array( 'ok' => true ) );
    } );
```

---

## Controllers

### AJAX Controllers

Extend `Controller`. Constructor dependencies are auto-resolved from the container. If a method type-hints a `Request` subclass, it is validated and authorized automatically before the method runs.

```php
use WPFlint\Http\Controller;
use WPFlint\Http\Response;

class OrderController extends Controller {

    private OrderService $orders;

    public function __construct( OrderService $orders ) {
        $this->orders = $orders;
    }

    public function store( StoreOrderRequest $request ): Response {
        // $request is already validated and sanitized.
        $order = $this->orders->create( $request->validated() );
        return Response::json( $order->to_array(), 201 );
    }

    public function index( Request $request ): Response {
        return Response::json( $this->orders->all() );
    }
}
```

### REST Controllers

Extend `RestController` for WordPress REST API endpoints:

```php
use WPFlint\Http\RestController;

class OrderRestController extends RestController {

    protected string $namespace = 'my-shop/v1';
    protected string $rest_base = 'orders';

    public function index( \WP_REST_Request $request ): \WP_REST_Response {
        $orders = Order::all();
        return $this->respond( $orders );
    }

    public function show( \WP_REST_Request $request ): \WP_REST_Response {
        $order = Order::find( (int) $request->get_param( 'id' ) );
        if ( ! $order ) {
            return $this->error( __( 'Not found.', 'my-shop' ), 404 );
        }
        return $this->respond( $order->to_array() );
    }

    public function get_items_permissions_check( $request ): bool {
        return current_user_can( 'read' );
    }
}
```

---

## Requests & Validation

### Base Request

```php
use WPFlint\Http\Request;

$request = new Request( $data );

$request->input( 'name' );               // single value
$request->input( 'user.name' );          // dot notation
$request->input( 'missing', 'default' ); // with default
$request->all();                          // all input
$request->only( array( 'name' ) );       // subset
$request->except( array( 'secret' ) );   // exclude keys
$request->has( 'name' );                 // bool
$request->file( 'avatar' );             // uploaded file or null
```

### Form Requests

Subclass `Request` to add validation, authorization, and sanitization:

```php
class StoreOrderRequest extends Request {

    public function authorize(): bool {
        return current_user_can( 'edit_posts' );
    }

    public function rules(): array {
        return array(
            'status'             => 'required|in:pending,paid,cancelled',
            'total'              => 'required|numeric|min:0',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.qty'        => 'required|integer|min:1',
            'note'               => 'nullable|string|max:500',
        );
    }

    public function sanitize(): array {
        return array(
            'status' => 'sanitize_text_field',
            'total'  => 'floatval',
        );
    }
}
```

### Validation Rules

| Rule | Description |
|------|-------------|
| `required` | Must be present and non-empty |
| `nullable` | Allows null; skips remaining rules if null |
| `string` | Must be a string |
| `integer` | Must be an integer |
| `numeric` | Must be numeric |
| `email` | Must pass `is_email()` |
| `url` | Must pass `filter_var( FILTER_VALIDATE_URL )` |
| `boolean` | Must be true/false/0/1 |
| `array` | Must be an array |
| `in:a,b,c` | Must be one of the listed values |
| `min:N` | Minimum value (numeric), length (string), or count (array) |
| `max:N` | Maximum value (numeric), length (string), or count (array) |

Rules are pipe-separated: `'required|string|min:3|max:100'`

Use `*` for array item validation: `'items.*.product_id' => 'required|integer'`

### Using Validated Data

```php
$request = new StoreOrderRequest( $data );

if ( $request->validate() ) {
    $clean = $request->validated(); // sanitized, validated data
} else {
    $errors = $request->errors();   // array of field => message
}
```

---

## Responses

```php
use WPFlint\Http\Response;

// Success
Response::json( array( 'order' => $order ), 200 );
Response::json( array( 'id' => 1 ), 201 );
Response::no_content(); // 204

// Error
Response::error( __( 'Not found.', 'my-shop' ), 404 );
Response::error( __( 'Bad request.', 'my-shop' ) ); // defaults to 400

// Headers
$response->with_header( 'X-Custom', 'value' );

// Status checks
$response->is_successful(); // 2xx
$response->is_error();      // 4xx or 5xx

// Send
$response->send_ajax();          // calls wp_send_json_success/error
$rest = $response->to_rest();    // convert to WP_REST_Response
```

---

## Database: Migrations

Laravel-inspired migration system with version control, rollback support, and multi-plugin scoping.

### Creating a Migration

```php
use WPFlint\Database\Migrations\Migration;
use WPFlint\Database\Schema\Blueprint;

class CreateOrdersTable extends Migration {

    public function up(): void {
        $this->schema()->create( 'orders', function ( Blueprint $table ) {
            $table->big_increments( 'id' );
            $table->string( 'status' )->default( 'pending' );
            $table->decimal( 'total', 10, 2 );
            $table->text( 'notes' )->nullable();
            $table->timestamps();
        } );
    }

    public function down(): void {
        $this->schema()->drop( 'orders' );
    }
}
```

### Running Migrations

```php
use WPFlint\Database\Migrations\Migrator;
use WPFlint\Database\Migrations\MigrationRepository;

$repository = new MigrationRepository( 'my-shop' );
$migrator   = new Migrator( $repository, array(
    CreateUsersTable::class,
    CreateOrdersTable::class,
) );

$ran         = $migrator->run();           // run pending
$rolled_back = $migrator->rollback();      // rollback last batch
$rolled_back = $migrator->rollback( 2 );   // rollback 2 batches
$status      = $migrator->get_status();    // migration status
$pending     = $migrator->get_pending();   // unrun migrations
```

### Migration Repository

Tracks run migrations in `{prefix}wpflint_migrations`, scoped by plugin slug so multiple plugins don't collide.

---

## Database: Schema Builder

Fluent API for creating and modifying database tables.

### Column Types

```php
$table->big_increments( 'id' );        // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
$table->string( 'name' );              // VARCHAR(255)
$table->string( 'code', 50 );          // VARCHAR(50)
$table->integer( 'quantity' );         // INT
$table->decimal( 'price', 10, 2 );     // DECIMAL(10,2)
$table->boolean( 'active' );           // TINYINT(1)
$table->text( 'description' );         // TEXT
$table->timestamps();                   // created_at + updated_at DATETIME
$table->soft_deletes();                 // deleted_at DATETIME NULL
```

### Column Modifiers

```php
$table->string( 'status' )->default( 'pending' );
$table->text( 'notes' )->nullable();
```

### Indexes

```php
$table->index( 'email' );                           // single column
$table->index( array( 'status', 'created_at' ) );  // composite
$table->unique( 'email' );                           // unique constraint
```

### Foreign Keys

```php
$table->foreign( 'user_id' )
    ->references( 'id' )
    ->on( 'users' )
    ->on_delete( 'CASCADE' )
    ->on_update( 'CASCADE' );
```

### Table Operations

```php
$schema->create( 'orders', function ( Blueprint $table ) { ... } );
$schema->drop( 'orders' );
$schema->has_table( 'orders' );        // bool
$schema->add_column( 'orders', function ( Blueprint $table ) {
    $table->string( 'tracking_number' )->nullable();
} );
```

---

## Database: Query Builder

Fluent SQL builder that compiles and executes queries via `$wpdb->prepare()`.

```php
use WPFlint\Database\ORM\QueryBuilder;

$query = QueryBuilder::table( 'orders' );
```

### WHERE Clauses

```php
$query->where( 'status', 'pending' );              // = implied
$query->where( 'total', '>', 100 );                // explicit operator
$query->or_where( 'status', 'paid' );
$query->where_in( 'id', array( 1, 2, 3 ) );
$query->where_not_in( 'id', array( 4, 5 ) );
$query->where_null( 'deleted_at' );
$query->where_not_null( 'paid_at' );
$query->where_between( 'total', 10, 100 );
$query->where_like( 'name', '%john%' );
$query->where_raw( 'total > %d', array( 50 ) );
```

### Ordering, Grouping, Limits

```php
$query->order_by( 'created_at', 'DESC' );
$query->latest();                    // created_at DESC
$query->oldest();                    // created_at ASC
$query->group_by( 'status' );
$query->having( 'COUNT(*)', '>', 5 );
$query->limit( 10 )->offset( 20 );
```

### Joins

```php
$query->join( 'users', 'orders.user_id', '=', 'users.id' );
$query->left_join( 'profiles', 'users.id', '=', 'profiles.user_id' );
$query->right_join( 'logs', 'orders.id', '=', 'logs.order_id' );
```

### Retrieval

```php
$rows   = $query->get();                // array of associative arrays
$row    = $query->first();              // single row or null
$row    = $query->find( 42 );           // find by primary key
$value  = $query->value( 'status' );    // single column value
$ids    = $query->pluck( 'id' );        // array of values
$map    = $query->pluck( 'name', 'id' ); // keyed array
$exists = $query->exists();
```

### Aggregates

```php
$query->count();
$query->max( 'total' );
$query->min( 'total' );
$query->avg( 'total' );
$query->sum( 'total' );
```

### Pagination & Chunking

```php
$page = $query->paginate( 15, 1 );
// Returns: ['data' => [...], 'total' => 100, 'per_page' => 15,
//           'current_page' => 1, 'last_page' => 7]

$query->chunk( 100, function ( array $rows ) {
    // Process batch. Return false to stop.
} );
```

### Write Operations

```php
$id = $query->insert( array( 'status' => 'pending', 'total' => 99.50 ) );
$query->insert_many( array( $row1, $row2 ) );

$query->where( 'id', 1 )->update( array( 'status' => 'paid' ) );
$query->where( 'id', 1 )->increment( 'views' );
$query->where( 'id', 1 )->decrement( 'stock', 3 );
$query->where( 'id', 1 )->delete();
```

---

## Database: ORM

Laravel-inspired Active Record ORM. Every query uses `$wpdb->prepare()`.

### Defining Models

```php
use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;

class Order extends Model {

    protected static string $table = 'orders';         // optional, auto-inferred
    protected static string $primary_key = 'id';        // default
    protected static bool $timestamps = true;            // default

    protected array $fillable = array( 'status', 'total', 'meta' );
    protected array $hidden = array( 'internal_notes' );
    protected array $casts = array(
        'total'  => 'float',
        'meta'   => 'array',
        'active' => 'boolean',
    );

    public function scope_pending( ModelQueryBuilder $q ): ModelQueryBuilder {
        return $q->where( 'status', 'pending' );
    }

    public function scope_expensive( ModelQueryBuilder $q, float $min ): ModelQueryBuilder {
        return $q->where( 'total', '>=', $min );
    }
}
```

### Table Name Inference

If `$table` is not set, it's inferred from the class name:

- `Order` becomes `orders`
- `UserProfile` becomes `user_profiles`
- `Category` becomes `categories`

### Querying

```php
// Find
$order = Order::find( 1 );                    // Model or null
$order = Order::find_or_fail( 1 );            // Model or throws

// All
$orders = Order::all();                        // array of Models

// Where
$orders = Order::where( 'status', 'pending' )->get_models();
$orders = Order::where( 'total', '>', 100 )->get_models();
$orders = Order::where_in( 'id', array( 1, 2, 3 ) )->get_models();

// Scopes
$orders = Order::pending()->get_models();
$orders = Order::expensive( 500 )->get_models();

// Raw query builder
$builder = Order::query();
```

### Creating & Updating

```php
// Create
$order = Order::create( array( 'status' => 'pending', 'total' => 99.50 ) );

// Find or create
$order = Order::first_or_create(
    array( 'status' => 'pending' ),  // search
    array( 'total' => 0 )             // defaults if created
);

// Update or create
$order = Order::update_or_create(
    array( 'email' => 'john@example.com' ),
    array( 'name' => 'John' )
);

// Instance update
$order->status = 'paid';
$order->save();

// Mass delete
Order::destroy( 1 );
```

### Dirty Tracking

```php
$order->set_attribute( 'status', 'paid' );
$order->is_dirty();          // true
$order->is_dirty( 'status' ); // true
$order->get_dirty();          // ['status' => 'paid']
```

### Serialization

```php
$order->to_array();  // excludes $hidden, applies casts
$order->to_json();
```

### Casting

| Cast Type | PHP Type |
|-----------|----------|
| `int` / `integer` | `int` |
| `float` / `double` | `float` |
| `string` | `string` |
| `bool` / `boolean` | `bool` |
| `array` / `json` | `array` (auto json_encode on save) |
| `datetime` | `string` |

### Cache Integration

```php
$order = Order::cached( 42 );         // cached find (TTL 3600s)
$order = Order::cached( 42, 600 );    // custom TTL
$order = Order::fresh_find( 42 );     // bypass cache, fetch from DB
```

---

## Relationships

### Defining Relationships

```php
class User extends Model {
    protected static string $table = 'users';

    public function profile(): HasOne {
        return $this->has_one( Profile::class );
    }

    public function orders(): HasMany {
        return $this->has_many( Order::class );
    }
}

class Order extends Model {
    protected static string $table = 'orders';

    public function user(): BelongsTo {
        return $this->belongs_to( User::class );
    }
}
```

Foreign keys are auto-inferred:
- `has_one` / `has_many`: foreign key = `{parent_snake}_id`
- `belongs_to`: foreign key = `{related_snake}_id`

Or specify explicitly:

```php
$this->has_many( Order::class, 'customer_id', 'id' );
```

### Lazy Loading

```php
$user    = User::find( 1 );
$profile = $user->profile()->get_results();  // single Model or null
$orders  = $user->orders()->get_results();   // array of Models
$user    = $order->user()->get_results();    // single Model
```

### Eager Loading

Prevents N+1 queries using `WHERE IN` batching:

```php
$users = User::where( 'active', 1 )
    ->with( array( 'orders', 'profile' ) )
    ->get_models();

foreach ( $users as $user ) {
    $user->orders;   // already loaded
    $user->profile;  // already loaded
}
```

Loaded relations are included in `to_array()` and `to_json()`.

---

## Events

Typed event system with closure and class-based listeners, plus WordPress hook bridging.

### Defining Events

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

### Registering Listeners

```php
use WPFlint\Events\Dispatcher;

$dispatcher = new Dispatcher( $app );

// Closure
$dispatcher->listen( OrderPlaced::class, function ( OrderPlaced $event ) {
    log_order( $event->order_id );
} );

// Class-based (must have a handle() method)
$dispatcher->listen( OrderPlaced::class, SendConfirmation::class );

// Array callable
$dispatcher->listen( OrderPlaced::class, array( $handler, 'on_order' ) );
```

### Class Listeners

```php
class SendConfirmation {
    public function handle( OrderPlaced $event ): void {
        // send email using $event->order_id
    }
}
```

### Firing Events

```php
$dispatcher->fire( new OrderPlaced( 42, 99.50 ) );
```

### Stop Propagation

```php
$dispatcher->listen( OrderPlaced::class, function ( OrderPlaced $event ) {
    $event->stop_propagation(); // subsequent listeners won't run
} );
```

### WordPress Hook Bridge

Bridge native WordPress hooks to typed events:

```php
$dispatcher->listen_wp( 'save_post', PostSaved::class, 10, 2 );

// When save_post fires, PostSaved is constructed with the hook args
// and dispatched through all registered listeners.
```

### Event Facade

```php
use WPFlint\Facades\Event;

Event::listen( OrderPlaced::class, SendConfirmation::class );
Event::fire( new OrderPlaced( 42, 99.50 ) );
Event::forget( OrderPlaced::class );
Event::has_listeners( OrderPlaced::class );
Event::listen_wp( 'save_post', PostSaved::class );
```

---

## Cache

Multi-driver cache system with tag-based invalidation and fresh mode.

### Basic Usage

```php
use WPFlint\Cache\CacheManager;

$cache = new CacheManager( 'transient' );

$cache->put( 'key', 'value', 300 );      // store for 5 minutes
$cache->get( 'key' );                     // 'value'
$cache->get( 'missing', 'default' );      // 'default'
$cache->has( 'key' );                     // true
$cache->forget( 'key' );                  // delete
$cache->flush();                           // clear all
```

### Remember (Get or Set)

```php
$orders = $cache->remember( 'all_orders', 3600, function () {
    return Order::all();
} );
```

### Fresh Mode

Bypass the cache, re-execute the callback, and store the new result:

```php
$orders = $cache->fresh()->remember( 'all_orders', 3600, function () {
    return Order::all();
} );

// fresh() resets after one call.
```

### Tagged Cache

Group cache keys by tags for bulk invalidation:

```php
// Store under tags
$cache->tags( array( 'orders' ) )->remember( 'order_list', 300, function () {
    return Order::all();
} );

$cache->tags( 'orders' )->put( 'order_42', $order, 600 );

// Flush all keys under a tag
$cache->tags( 'orders' )->flush();
```

### Cache Facade

```php
use WPFlint\Facades\Cache;

Cache::put( 'key', 'value', 300 );
Cache::get( 'key' );
Cache::remember( 'key', 3600, fn() => expensive_query() );
Cache::tags( 'orders' )->flush();
Cache::fresh()->remember( 'key', 3600, $callback );
```

### Drivers

| Driver | Backend | Use Case |
|--------|---------|----------|
| `transient` | `get_transient()` / `set_transient()` | Default, works everywhere |
| `object` | `wp_cache_get()` / `wp_cache_set()` | Persistent object cache (Redis, Memcached) |
| `array` | In-memory PHP array | Testing |

```php
$cache = new CacheManager( 'array' ); // for tests
```

---

## Facades

Static proxies that resolve their backing instance from the container at runtime.

### Built-in Facades

| Facade | Accessor | Resolves To |
|--------|----------|-------------|
| `Config` | `'config'` | `Repository` |
| `Cache` | `'cache'` | `CacheManager` |
| `Event` | `'events'` | `Dispatcher` |

### Creating Custom Facades

```php
use WPFlint\Facades\Facade;

class Order extends Facade {

    protected static function get_facade_accessor(): string {
        return 'orders'; // container binding key
    }
}

// Usage:
Order::find( 1 );
Order::pending()->get_models();
```

### Testing

Clear cached instances in test tearDown:

```php
Facade::clear_resolved_instances();
```

---

## WP-CLI Commands

Dev-only commands excluded from production builds via `.distignore`.

### Database

| Command | Description |
|---------|-------------|
| `wp wpflint migrate` | Run pending migrations |
| `wp wpflint migrate --rollback` | Roll back last batch |
| `wp wpflint migrate --rollback --steps=N` | Roll back N batches |
| `wp wpflint migrate --fresh` | Drop all tables, re-run (with confirmation) |
| `wp wpflint migrate --status` | Show migration status |
| `wp wpflint cache:clear` | Clear all application cache |
| `wp wpflint cache:clear --tag=orders` | Clear a specific cache tag |

### Code Generation

| Command | Description |
|---------|-------------|
| `wp wpflint make:migration <Name>` | Generate migration stub |
| `wp wpflint make:model <Name>` | Generate model stub |
| `wp wpflint make:model <Name> --migration` | Generate model + migration |
| `wp wpflint make:controller <Name>` | Generate AJAX controller |
| `wp wpflint make:controller <Name> --rest` | Generate REST controller |
| `wp wpflint make:middleware <Name>` | Generate middleware stub |
| `wp wpflint make:request <Name>` | Generate form request stub |
| `wp wpflint make:provider <Name>` | Generate service provider stub |
| `wp wpflint make:event <Name>` | Generate event stub |
| `wp wpflint make:facade <Name>` | Generate facade stub |

All `make:*` commands accept `--path=<dir>` to customize the output directory.

---

## Testing

WPFlint uses PHPUnit 9 with [WP_Mock](https://github.com/10up/wp_mock) for mocking WordPress functions and [Brain\Monkey](https://github.com/Brain-WP/BrainMonkey) for hooks.

### Running Tests

```bash
composer test
```

### Test Structure

Tests mirror the source directory structure:

```
tests/
  bootstrap.php
  ApplicationTest.php
  Container/ContainerTest.php
  Providers/ServiceProviderTest.php
  Config/RepositoryTest.php
  Database/
    Schema/BlueprintTest.php
    Migrations/MigratorTest.php
    ORM/ModelTest.php
    ORM/QueryBuilderTest.php
    ORM/RelationTest.php
  Http/
    RequestTest.php
    ResponseTest.php
    RouterTest.php
    MiddlewareTest.php
    PipelineTest.php
  Cache/
    CacheManagerTest.php
    TaggedCacheTest.php
  Events/DispatcherTest.php
  Console/CommandTest.php
  Integration/FullPluginTest.php
```

### Writing Tests

```php
use WP_Mock;
use WP_Mock\Tools\TestCase;
use Mockery;

class MyTest extends TestCase {

    public function setUp(): void {
        parent::setUp();
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function testSomething(): void {
        WP_Mock::userFunction( 'get_option', array(
            'return' => 'value',
        ) );

        // your test...
    }
}
```

---

## WP.org Compliance

WPFlint is built for WordPress.org plugin directory compliance:

- All database queries use `$wpdb->prepare()`
- All user input sanitized with `sanitize_*()` functions
- All output escaped with `esc_*()` functions
- AJAX handlers verify nonces via `check_ajax_referer()` and capabilities via `current_user_can()`
- All translatable strings use `__()` or `_e()` with proper text domains
- No `eval()`, `exec()`, or `system()` calls
- `.distignore` excludes dev-only files from distribution

---

## Directory Structure

```
src/
  Application.php               # Singleton bootstrap, extends Container
  Container/
    Container.php                # PSR-11 IoC container
    ContextualBindingBuilder.php # when()->needs()->give() API
  Providers/
    ServiceProvider.php          # Base provider class
    FrameworkServiceProvider.php  # Core framework bindings
  Config/
    Repository.php               # Dot-notation config manager
    ConfigServiceProvider.php    # Registers config singleton
  Http/
    Router.php                   # AJAX + REST route registration
    RestRouter.php               # REST API route builder
    Route.php                    # Single route definition
    Controller.php               # AJAX controller base
    RestController.php           # REST controller base
    Request.php                  # Input + validation + sanitization
    Response.php                 # Unified response builder
    Pipeline.php                 # Middleware pipeline
    HttpServiceProvider.php      # Registers router
    Middleware/
      MiddlewareInterface.php    # Contract for middleware
      VerifyNonce.php            # nonce:{action}
      CheckCapability.php        # can:{capability}
      ThrottleRequests.php       # throttle:{max},{minutes}
  Database/
    Schema/
      Schema.php                 # Table create/drop/modify
      Blueprint.php              # Column definitions
      ColumnDefinition.php       # Column modifiers
      ForeignKeyDefinition.php   # Foreign key builder
    Migrations/
      Migration.php              # Base migration class
      Migrator.php               # Run/rollback orchestrator
      MigrationRepository.php   # Tracks run migrations
    ORM/
      QueryBuilder.php           # Fluent SQL builder
      Model.php                  # Active Record base
      ModelQueryBuilder.php      # Model-aware query builder
      ModelNotFoundException.php # Exception for find_or_fail
      Relation.php               # Base relation
      HasOne.php                 # One-to-one
      HasMany.php                # One-to-many
      BelongsTo.php              # Inverse one-to-one/many
      RawExpression.php          # Raw SQL fragments
  Cache/
    CacheManager.php             # Central cache API
    CacheDriverInterface.php     # Driver contract
    TaggedCache.php              # Tag-based invalidation
    CacheServiceProvider.php     # Registers cache singleton
    Drivers/
      TransientDriver.php        # WordPress transients
      ObjectCacheDriver.php      # wp_cache_* with fallback
      ArrayDriver.php            # In-memory (testing)
  Events/
    Event.php                    # Base event class
    Dispatcher.php               # Event dispatcher
    EventServiceProvider.php     # Registers dispatcher
  Facades/
    Facade.php                   # Static proxy base
    Config.php                   # Config facade
    Cache.php                    # Cache facade
    Event.php                    # Event facade
  Console/                       # Dev-only (excluded from prod)
    Command.php                  # Base WP-CLI command
    MigrateCommand.php           # Database migrations
    CacheClearCommand.php        # Cache clearing
    MakeControllerCommand.php    # make:controller
    MakeMiddlewareCommand.php    # make:middleware
    MakeRequestCommand.php       # make:request
    MakeMigrationCommand.php     # make:migration
    MakeModelCommand.php         # make:model
    MakeProviderCommand.php      # make:provider
    MakeEventCommand.php         # make:event
    MakeFacadeCommand.php        # make:facade
```

---

## License

GPL-2.0-or-later
