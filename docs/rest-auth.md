# REST API Auth Helpers

`RestAuth` provides factory methods that return `permission_callback` callables ready to drop into `register_rest_route()`, plus direct boolean checks and a versioned namespace builder.

---

## Permission Callback Factories

### `RestAuth::capability( string $cap ): callable`

Returns a callback that allows the request when the current user holds `$cap`.

```php
use WPFlint\Http\RestAuth;

register_rest_route( 'my-plugin/v1', '/settings', array(
    'methods'             => 'GET',
    'callback'            => array( $controller, 'index' ),
    'permission_callback' => RestAuth::capability( 'manage_options' ),
) );
```

### `RestAuth::logged_in(): callable`

Returns a callback that allows the request when any user is authenticated.

```php
'permission_callback' => RestAuth::logged_in(),
```

### `RestAuth::public_access(): callable`

Returns a callback that always returns `true`. Use for publicly accessible endpoints.

```php
'permission_callback' => RestAuth::public_access(),
```

### `RestAuth::all_of( string ...$caps ): callable`

Returns a callback that requires the user to hold **all** of the listed capabilities.

```php
'permission_callback' => RestAuth::all_of( 'edit_posts', 'upload_files' ),
```

### `RestAuth::any_of( string ...$caps ): callable`

Returns a callback that requires the user to hold **at least one** of the capabilities.

```php
'permission_callback' => RestAuth::any_of( 'edit_posts', 'edit_pages' ),
```

---

## Direct Boolean Checks

Use these inside custom permission logic or controllers:

### `RestAuth::require_logged_in(): bool`

```php
if ( ! RestAuth::require_logged_in() ) {
    return new \WP_Error( 'rest_forbidden', 'Authentication required.', array( 'status' => 401 ) );
}
```

### `RestAuth::require_capability( string $cap ): bool`

```php
if ( ! RestAuth::require_capability( 'manage_options' ) ) {
    return new \WP_Error( 'rest_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
}
```

---

## Namespace Builder

### `RestAuth::namespace( string $plugin, int $version = 1 ): string`

Builds a versioned REST namespace string in the standard `plugin/vN` format.

```php
RestAuth::namespace( 'my-plugin' );       // 'my-plugin/v1'
RestAuth::namespace( 'my-plugin', 2 );    // 'my-plugin/v2'
RestAuth::namespace( 'my-plugin', 3 );    // 'my-plugin/v3'
```

Use it wherever you'd write the namespace string manually:

```php
$ns = RestAuth::namespace( 'my-plugin', 1 );

register_rest_route( $ns, '/orders', array( ... ) );
register_rest_route( $ns, '/orders/(?P<id>\d+)', array( ... ) );
```

---

## Integration with RestController

`RestController` already handles REST registration. Use `RestAuth` to fill the `permission_callback` fields when registering routes manually or in override methods:

```php
use WPFlint\Http\RestController;
use WPFlint\Http\RestAuth;

class OrderRestController extends RestController {

    protected string $namespace = 'my-plugin/v1';
    protected string $rest_base = 'orders';

    public function get_items_permissions_check( $request ): bool {
        return RestAuth::require_logged_in();
    }

    public function create_item_permissions_check( $request ): bool {
        return RestAuth::require_capability( 'edit_posts' );
    }

    public function delete_item_permissions_check( $request ): bool {
        return RestAuth::require_capability( 'delete_posts' );
    }
}
```

---

## Combining with WPFlint Router

When registering REST routes via `Router::rest()`, assign permission callbacks through the same `RestAuth` factories:

```php
use WPFlint\Http\Router;
use WPFlint\Http\RestAuth;

$router->rest( RestAuth::namespace( 'my-plugin' ), function ( $r ) {

    // Public read endpoint:
    $r->get( '/products',
        array( ProductController::class, 'index' ),
        RestAuth::public_access()
    );

    // Authenticated write endpoint:
    $r->post( '/products',
        array( ProductController::class, 'store' ),
        RestAuth::capability( 'edit_posts' )
    );

    // Admin-only delete:
    $r->delete( '/products/(?P<id>\d+)',
        array( ProductController::class, 'destroy' ),
        RestAuth::capability( 'delete_posts' )
    );
} );
```

---

## Full Example — Versioned API with Mixed Auth

```php
use WPFlint\Http\RestAuth;

// Version 1 namespace:
$v1 = RestAuth::namespace( 'my-shop', 1 );

// Public product catalogue:
register_rest_route( $v1, '/products', array(
    'methods'             => 'GET',
    'callback'            => array( $products_ctrl, 'index' ),
    'permission_callback' => RestAuth::public_access(),
) );

// Authenticated customer orders:
register_rest_route( $v1, '/orders', array(
    array(
        'methods'             => 'GET',
        'callback'            => array( $orders_ctrl, 'index' ),
        'permission_callback' => RestAuth::logged_in(),
    ),
    array(
        'methods'             => 'POST',
        'callback'            => array( $orders_ctrl, 'store' ),
        'permission_callback' => RestAuth::logged_in(),
    ),
) );

// Admin-only settings:
register_rest_route( $v1, '/settings', array(
    'methods'             => \WP_REST_Server::CREATABLE,
    'callback'            => array( $settings_ctrl, 'update' ),
    'permission_callback' => RestAuth::all_of( 'manage_options', 'manage_woocommerce' ),
) );
```

---

## API Summary

| Method | Returns | Description |
|--------|---------|-------------|
| `capability( $cap )` | `callable` | Requires a single capability |
| `logged_in()` | `callable` | Requires authentication |
| `public_access()` | `callable` | Always returns true |
| `all_of( ...$caps )` | `callable` | Requires ALL capabilities |
| `any_of( ...$caps )` | `callable` | Requires at least ONE capability |
| `require_logged_in()` | `bool` | Direct check — is logged in |
| `require_capability( $cap )` | `bool` | Direct check — has capability |
| `namespace( $plugin, $version )` | `string` | Builds `plugin/vN` namespace |
