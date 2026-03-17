# Application

The `Application` class extends `Container` and serves as the framework's bootstrap singleton.

## Getting the instance

```php
use WPFlint\Application;

$app = Application::get_instance( __DIR__ );
```

## Bootstrap

Call `bootstrap()` to hook into WordPress lifecycle:

```php
$app->bootstrap();
```

This registers three hooks:
- `plugins_loaded` (priority 1) — registers all providers
- `init` (priority 5) — boots non-deferred providers
- `wp_loaded` — boots remaining deferred providers

## Registering providers

```php
$app->register( PaymentServiceProvider::class );
$app->register( new CacheServiceProvider( $app ) );
```

Non-deferred providers are registered immediately. Deferred providers are stored and resolved lazily.

## Base path

```php
$app->base_path();              // /path/to/plugin
$app->base_path( 'src/Http' );  // /path/to/plugin/src/Http
```

## Base bindings

The application automatically registers itself as:
- `'app'`
- `WPFlint\Application::class`

## FrameworkServiceProvider

Registered automatically, binds:
- `ContainerInterface::class` → the app instance
- `Container::class` → the app instance

## API

| Method | Description |
|---|---|
| `get_instance()` | Get/create the singleton |
| `clear_instance()` | Reset singleton (testing) |
| `bootstrap()` | Hook into WP lifecycle |
| `register($provider)` | Register a service provider |
| `boot_providers()` | Boot all non-deferred providers |
| `boot_deferred_providers()` | Boot all remaining deferred providers |
| `base_path($path)` | Get plugin base path |
| `is_booted()` | Check if app has booted |
| `get_providers()` | Get registered providers |
| `get_deferred_providers()` | Get deferred provider map |
