# HTTP Layer

WPFlint provides a full HTTP layer for handling AJAX and REST API requests with validation, middleware, and routing.

## Request

Base class for capturing and validating input.

```php
use WPFlint\Http\Request;

// Create from data
$request = new Request( array( 'name' => 'John', 'email' => 'john@example.com' ) );

// Access input
$request->input( 'name' );               // 'John'
$request->input( 'missing', 'default' ); // 'default'
$request->input( 'user.name' );          // dot notation for nested
$request->all();                          // all input
$request->only( array( 'name' ) );       // subset
$request->except( array( 'email' ) );    // exclude keys
$request->has( 'name' );                 // true
$request->file( 'avatar' );             // uploaded file or null
```

### Form Requests (Validation)

Subclass `Request` to define validation rules, sanitization, and authorization:

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
| `nullable` | Allows null values; skips other rules if null |
| `string` | Must be a string |
| `integer` | Must be an integer |
| `numeric` | Must be numeric |
| `email` | Must pass `is_email()` |
| `url` | Must pass `filter_var(FILTER_VALIDATE_URL)` |
| `boolean` | Must be true/false/0/1/'0'/'1' |
| `array` | Must be an array |
| `in:a,b,c` | Must be one of the listed values |
| `min:N` | Minimum value (numeric), length (string), or count (array) |
| `max:N` | Maximum value (numeric), length (string), or count (array) |

Rules are pipe-separated: `'required|string|min:3|max:100'`

### Wildcard Validation

Use `*` for array items:

```php
'items.*.product_id' => 'required|integer'
```

### Using Validated Data

```php
$request = new StoreOrderRequest( $data );

if ( ! $request->authorize() ) {
    // unauthorized
}

if ( $request->validate() ) {
    $clean = $request->validated(); // sanitized data
} else {
    $errors = $request->errors();   // array of field => message
}
```

## Response

Unified response for both AJAX and REST endpoints.

```php
use WPFlint\Http\Response;

// Success responses
Response::json( array( 'order' => $order ), 200 );
Response::json( array( 'id' => 1 ), 201 );
Response::no_content(); // 204

// Error responses
Response::error( 'Not found', 404 );
Response::error( 'Bad request' );  // defaults to 400

// Headers
$response->with_header( 'X-Custom', 'value' );

// Status checks
$response->is_successful(); // 2xx
$response->is_error();      // 4xx or 5xx

// Send as AJAX (calls wp_send_json_success/error)
$response->send_ajax();

// Convert to WP_REST_Response
$rest_response = $response->to_rest();
```

## Middleware

### Built-in Middleware

**Nonce Verification** — `nonce:{action}`
```php
// Verifies WordPress nonce via check_ajax_referer()
->middleware( array( 'nonce:save_order' ) )
```

**Capability Check** — `can:{capability}`
```php
// Checks current_user_can()
->middleware( array( 'can:edit_posts' ) )
```

**Throttle Requests** — `throttle:{max},{minutes}`
```php
// Rate limit using WordPress transients
->middleware( array( 'throttle:60,1' ) ) // 60 requests per minute
```

### Custom Middleware

```php
use WPFlint\Http\Middleware\MiddlewareInterface;
use WPFlint\Http\Request;
use WPFlint\Http\Response;

class EnsureStoreIsOpen implements MiddlewareInterface {

    public function handle( Request $request, Closure $next ) {
        if ( ! get_option( 'store_open' ) ) {
            return Response::error( 'Store is closed', 403 );
        }
        return $next( $request );
    }
}

// Register alias
$router->alias_middleware( 'store.open', EnsureStoreIsOpen::class );
```

## Pipeline

Sends a request through a middleware stack:

```php
use WPFlint\Http\Pipeline;

$result = ( new Pipeline() )
    ->send( $request )
    ->through( array( $middleware1, $middleware2 ) )
    ->then( function ( Request $request ) {
        return Response::json( array( 'ok' => true ) );
    } );
```

Middleware executes in order. Any middleware can short-circuit by returning a Response without calling `$next`.

## Router

### AJAX Routes

```php
use WPFlint\Http\Router;

$router = $app->make( Router::class );

// Basic AJAX route (logged-in users only)
$router->ajax( 'my-plugin/save-order', array( OrderController::class, 'store' ) )
    ->middleware( array( 'nonce:save_order', 'can:edit_posts' ) );

// Public AJAX route (logged-in + guests)
$router->ajax( 'my-plugin/get-orders', array( OrderController::class, 'index' ) )
    ->nopriv()
    ->middleware( array( 'throttle:60,1' ) );
```

### REST API Routes

```php
use WPFlint\Http\RestRouter;

$router->rest( 'my-plugin/v1', function ( RestRouter $r ) {
    $r->get( '/orders', array( OrderRestController::class, 'index' ) );
    $r->post( '/orders', array( OrderRestController::class, 'store' ) );
    $r->get( '/orders/(?P<id>\d+)', array( OrderRestController::class, 'show' ) );
    $r->put( '/orders/(?P<id>\d+)', array( OrderRestController::class, 'update' ) );
    $r->delete( '/orders/(?P<id>\d+)', array( OrderRestController::class, 'destroy' ) );
} );
```

## Controller

AJAX controllers extend `Controller`. Dependencies are auto-resolved from the container.
If a method type-hints a `Request` subclass, it is validated and authorized automatically.

```php
use WPFlint\Http\Controller;
use WPFlint\Http\Response;

class OrderController extends Controller {

    private OrderService $orders;

    public function __construct( OrderService $orders ) {
        $this->orders = $orders;
    }

    public function store( StoreOrderRequest $request ): Response {
        // $request is already validated and sanitized
        $order = $this->orders->create( $request->validated() );
        return Response::json( $order->to_array(), 201 );
    }

    public function index( Request $request ): Response {
        return Response::json( $this->orders->all() );
    }
}
```

## RestController

REST controllers extend `RestController` with `respond()` and `error()` helpers:

```php
use WPFlint\Http\RestController;

class OrderRestController extends RestController {

    protected string $namespace = 'my-plugin/v1';
    protected string $rest_base = 'orders';

    public function index( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->respond( Order::all() );
    }

    public function show( \WP_REST_Request $request ): \WP_REST_Response {
        $order = Order::find( (int) $request->get_param( 'id' ) );
        if ( ! $order ) {
            return $this->error( 'Not found', 404 );
        }
        return $this->respond( $order->to_array() );
    }
}
```

## HttpServiceProvider

Registers the Router as a singleton and boots all routes:

```php
use WPFlint\Http\HttpServiceProvider;

// In your plugin bootstrap:
$app->register( HttpServiceProvider::class );
```

The provider:
1. Binds `Router` as a singleton in the container
2. On boot, registers all AJAX hooks and REST API routes with WordPress
