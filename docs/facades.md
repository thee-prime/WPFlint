# Facades

Static proxies that resolve their backing instance from the container at runtime.

## Quick Start

```php
use WPFlint\Facades\Config;

Config::get('app.name');        // calls $app->make('config')->get('app.name')
Config::set('app.debug', true);
```

## How It Works

Each facade extends `WPFlint\Facades\Facade` and implements `get_facade_accessor()`, which returns the container binding key. When a static method is called, the base class resolves the instance from the container and forwards the call.

## Creating a Custom Facade

```php
namespace MyPlugin\Facades;

use WPFlint\Facades\Facade;

class Cache extends Facade {
    protected static function get_facade_accessor(): string {
        return 'cache';
    }
}
```

Then use it: `Cache::remember('key', 3600, fn() => expensive())`.

## Instance Caching

Facades cache the resolved instance for performance. Call `Facade::clear_resolved_instances()` in test tearDown to reset.

## Built-in Facades

| Facade | Accessor | Resolves To |
|--------|----------|-------------|
| `Config` | `'config'` | `WPFlint\Config\Repository` |

## Files

| File | Purpose |
|------|---------|
| `src/Facades/Facade.php` | Abstract base class |
| `src/Facades/Config.php` | Config facade |
| `tests/Facades/FacadeTest.php` | Facade + Config facade tests |
