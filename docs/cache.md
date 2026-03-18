# Cache

WPFlint provides a multi-driver cache system with tag-based invalidation, a `fresh()` bypass mode, and Model integration.

## CacheManager

Central entry point. Resolves the configured driver and provides `tags()` / `fresh()` API.

```php
use WPFlint\Cache\CacheManager;

$cache = new CacheManager( 'transient', 'wpflint' );

// Basic operations
$cache->put( 'key', 'value', 300 );         // store for 5 min
$cache->get( 'key' );                        // 'value'
$cache->get( 'missing', 'default' );         // 'default'
$cache->has( 'key' );                        // true
$cache->forget( 'key' );                     // delete
$cache->flush();                              // clear all
```

### Remember (get-or-set)

```php
$orders = $cache->remember( 'all_orders', 3600, function () {
    return Order::all();
} );
```

### Fresh Mode (bypass reads)

```php
// Bypass cached value, re-execute callback, store new result.
$orders = $cache->fresh()->remember( 'all_orders', 3600, function () {
    return Order::all();
} );

// fresh() also affects get() and has():
$cache->fresh()->get( 'key' );  // returns default, ignores cache
$cache->fresh()->has( 'key' );  // returns false

// Fresh mode resets after one call.
```

## Tagged Cache

Group cache keys by tags for bulk invalidation.

```php
// Store under tags
$cache->tags( array( 'orders' ) )->remember( 'order_list', 300, function () {
    return Order::all();
} );

$cache->tags( 'orders' )->put( 'order_42', $order, 600 );

// Flush all keys under a tag
$cache->tags( 'orders' )->flush();

// Forget a specific key
$cache->tags( 'orders' )->forget( 'order_42' );
```

Tag storage uses WordPress options: `{prefix}_cache_tag_{tag}` stores an array of tracked cache keys.

## Cache Facade

Static proxy via the `Cache` facade:

```php
use WPFlint\Facades\Cache;

Cache::put( 'key', 'value', 300 );
Cache::get( 'key' );
Cache::forget( 'key' );
Cache::remember( 'key', 3600, fn() => expensive_query() );
Cache::tags( array( 'orders' ) )->remember( 'k', 300, fn() => Order::all() );
Cache::tags( 'orders' )->flush();
Cache::fresh()->remember( 'key', 3600, $callback );
```

## Drivers

### TransientDriver (default)

Uses `get_transient()` / `set_transient()` / `delete_transient()`. Works on all WordPress installs.

```php
$cache = new CacheManager( 'transient' );
```

### ObjectCacheDriver

Uses `wp_cache_get()` / `wp_cache_set()` / `wp_cache_delete()`. Falls back to TransientDriver if no persistent object cache is detected (`wp_using_ext_object_cache()`).

```php
$cache = new CacheManager( 'object' );
```

### ArrayDriver

In-memory PHP array. No persistence between requests. Ideal for testing.

```php
$cache = new CacheManager( 'array' );
```

## Model Integration

Models have `cached()` and `fresh_find()` static methods for cache-aware lookups.

```php
// Cached find — uses CacheManager if available, TTL defaults to 3600s.
$order = Order::cached( 42 );
$order = Order::cached( 42, 600 ); // custom TTL

// Fresh find — clears cached entry, fetches directly from DB.
$order = Order::fresh_find( 42 );
```

Cache key format: `{table}_{id}` (e.g. `orders_42`).

## CacheServiceProvider

Registers the CacheManager as a deferred singleton:

```php
use WPFlint\Cache\CacheServiceProvider;

$app->register( CacheServiceProvider::class );
```

Configuration (via Config):
- `cache.driver` — `'transient'`, `'object'`, or `'array'` (default: `'transient'`)
- `cache.prefix` — option prefix for tag tracking (default: `'wpflint'`)

## Security

- Tag tracking uses `update_option()` / `get_option()` with non-autoloaded options
- Cache keys are developer-controlled (not user input)
- No user data is cached without explicit developer intent
