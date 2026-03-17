# Config

Dot-notation configuration repository with file-based loading and environment helpers.

## Quick Start

```php
// Via service provider (automatic when ConfigServiceProvider is registered):
$config = $app->make('config');
$config->get('app.debug');

// Via facade:
use WPFlint\Facades\Config;
Config::get('app.name', 'My Plugin');
Config::set('app.debug', true);
```

## Configuration Files

Place PHP files in your plugin's `config/` directory. Each file should return an array:

```php
// config/app.php
return [
    'name'  => 'My Plugin',
    'debug' => false,
    'slug'  => 'my-plugin',
];
```

The filename becomes the top-level key: `Config::get('app.name')` reads from `config/app.php`.

## API

### `get(string $key, $default = null): mixed`

Retrieve a value using dot-notation. Returns `$default` if the key does not exist.

```php
$config->get('app.debug');           // false
$config->get('app.missing', true);   // true (default)
```

### `set(string $key, $value): void`

Set a value using dot-notation. Creates intermediate arrays as needed.

```php
$config->set('cache.driver', 'redis');
$config->set('a.b.c', 'deep');
```

### `has(string $key): bool`

Check if a key exists (even if the value is `null`).

```php
$config->has('app.debug');   // true
$config->has('app.nope');    // false
```

### `all(): array`

Return the full configuration array.

### `push(string $key, $value): void`

Append a value to an array config item.

```php
$config->push('app.providers', MyProvider::class);
```

### `Repository::env(string $key, $default = null): mixed`

Static helper. Checks for a PHP constant first (`defined()`/`constant()`), then `$_ENV`, then returns `$default`.

```php
Repository::env('WP_DEBUG', false);
Repository::env('DB_HOST', 'localhost');
```

## Files

| File | Purpose |
|------|---------|
| `src/Config/Repository.php` | Dot-notation config store |
| `src/Config/ConfigServiceProvider.php` | Registers `'config'` singleton, loads files from `config/` |
| `tests/Config/RepositoryTest.php` | Repository unit tests |
| `tests/Config/ConfigServiceProviderTest.php` | Provider unit tests |
