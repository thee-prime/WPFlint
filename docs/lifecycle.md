# Plugin Lifecycle

WPFlint provides a fluent wrapper over WordPress activation, deactivation, and uninstall hooks so every plugin lifecycle event is wired up in one expressive chain.

## Basic Usage

```php
use WPFlint\Lifecycle\Lifecycle;

Lifecycle::for( __FILE__ )
    ->on_activate( function () {
        // Create tables, set default options, etc.
        $migrator->run();
    } )
    ->on_deactivate( function () {
        // Remove scheduled events, flush rewrite rules, etc.
        wp_clear_scheduled_hook( 'my_plugin_cron' );
    } )
    ->on_uninstall( MyPlugin\Uninstaller::class )
    ->register();
```

Call `register()` once, near the top of your main plugin file — before WordPress fires any hooks.

---

## API Reference

### `Lifecycle::for( string $plugin_file ): self`

Static factory. `$plugin_file` must be the absolute path to the main plugin file, typically `__FILE__`.

### `on_activate( callable $callback ): self`

Queues a callback to run when the plugin is activated. You can call it multiple times to queue several callbacks — they run in order.

```php
Lifecycle::for( __FILE__ )
    ->on_activate( function () { /* create tables */ } )
    ->on_activate( function () { /* set defaults  */ } )
    ->register();
```

Internally wraps `register_activation_hook()`.

### `on_deactivate( callable $callback ): self`

Queues a callback to run on deactivation. Multiple calls accumulate.

```php
->on_deactivate( function () {
    wp_clear_scheduled_hook( 'my_plugin_cron' );
} )
```

Internally wraps `register_deactivation_hook()`.

### `on_uninstall( string $class_name ): self`

Registers a class as an uninstall handler. The class must have a **public static `uninstall()` method** — WordPress spawns a separate PHP process for uninstall and closures cannot be serialised across processes.

```php
namespace MyPlugin;

class Uninstaller {
    public static function uninstall(): void {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}my_plugin_orders" );
        delete_option( 'my_plugin_settings' );
    }
}
```

```php
->on_uninstall( \MyPlugin\Uninstaller::class )
```

Multiple uninstall handlers can be chained — each one is registered separately via `register_uninstall_hook()`.

### `register(): void`

Wires all accumulated callbacks to their WordPress hooks. Must be called at the plugin root level (not inside a hook callback).

---

## Service Provider Integration

Register the Lifecycle inside your bootstrap file, not inside a service provider, because activation/deactivation hooks must fire before `plugins_loaded`.

```php
// my-plugin.php (main plugin file)
use WPFlint\Lifecycle\Lifecycle;
use WPFlint\Application;

Lifecycle::for( __FILE__ )
    ->on_activate( function () {
        require_once __DIR__ . '/vendor/autoload.php';
        \MyPlugin\Installer::activate();
    } )
    ->on_deactivate( function () {
        \MyPlugin\Installer::deactivate();
    } )
    ->on_uninstall( \MyPlugin\Installer::class )
    ->register();

require_once __DIR__ . '/vendor/autoload.php';

$app = Application::get_instance( __DIR__ );
$app->register( \MyPlugin\Providers\AppServiceProvider::class );
$app->bootstrap();
```

---

## Notes

- Activation and deactivation callbacks are closures and are called directly by WordPress.
- Uninstall handlers must be **named class methods** (`ClassName::uninstall`), not closures, because WordPress runs `uninstall.php` in a fresh PHP process.
- `register()` is idempotent for the same `$plugin_file` as long as you only call it once per request.
