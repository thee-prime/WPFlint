# Asset Manager

WPFlint's asset system provides fluent builders for scripts and styles with support for dependency chains, versioning, conditional loading, and localization. An optional `AssetManager` service lets you collect and enqueue all assets from one place.

---

## Scripts

### Basic Usage

```php
use WPFlint\Assets\Script;

Script::make( 'my-plugin-app', plugin_dir_url( __FILE__ ) . 'assets/js/app.js' )
    ->deps( array( 'jquery', 'wp-api' ) )
    ->version( '1.2.0' )
    ->footer()
    ->enqueue();
```

### `Script::make( string $handle, string $src ): self`

Static factory. `$handle` is the unique script handle; `$src` is the URL to the `.js` file.

### `deps( array $deps ): self`

Array of script handle dependencies. Defaults to `[]`.

### `version( string $ver ): self`

Script version appended as a `?ver=` query string. Pass `false` via `wp_version()` to disable.

### `footer( bool $in_footer = true ): self`

Load in `<footer>` (`true`, the default after calling `footer()`) or `<head>` (`false`).

### `localize( string $object_name, array $data ): self`

Outputs a JavaScript object before the script tag. Wraps `wp_localize_script()`.

```php
Script::make( 'my-app', get_template_directory_uri() . '/app.js' )
    ->localize( 'MyApp', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'my_app' ),
    ) )
    ->enqueue();
```

### `only_on( callable $condition ): self`

Conditional callback — the script is only enqueued when `$condition()` returns `true`.

```php
// Only enqueue on admin pages:
->only_on( 'is_admin' )

// Only on a specific page:
->only_on( function () {
    return is_page( 'checkout' );
} )
```

### `enqueue(): void`

Calls `wp_enqueue_script()` (and `wp_localize_script()` if localize data was set), respecting the `only_on` condition.

### `register_asset(): void`

Calls `wp_register_script()` without enqueueing. Use when you want to register a script globally and enqueue it conditionally later.

---

## Styles

### Basic Usage

```php
use WPFlint\Assets\Style;

Style::make( 'my-plugin-style', plugin_dir_url( __FILE__ ) . 'assets/css/app.css' )
    ->deps( array( 'wp-components' ) )
    ->version( '1.2.0' )
    ->media( 'all' )
    ->enqueue();
```

### `Style::make( string $handle, string $src ): self`

Static factory.

### `deps( array $deps ): self`

Array of stylesheet handle dependencies.

### `version( string $ver ): self`

Style version string.

### `media( string $media ): self`

CSS media attribute (`'all'`, `'screen'`, `'print'`). Defaults to `'all'`.

### `only_on( callable $condition ): self`

Same conditional loading as Script.

### `enqueue(): void`

Calls `wp_enqueue_style()`, respecting the `only_on` condition.

### `register_asset(): void`

Calls `wp_register_style()` without enqueueing.

---

## Asset Manager

`AssetManager` collects scripts and styles and enqueues them all at once. It is bound as `'assets'` in the container by `AssetServiceProvider`.

### Using the Manager

```php
use WPFlint\Assets\AssetManager;
use WPFlint\Assets\Script;
use WPFlint\Assets\Style;

$manager = new AssetManager();

// Add pre-built asset objects:
$manager->add(
    Script::make( 'my-app', plugin_dir_url( __FILE__ ) . 'app.js' )
        ->footer()
        ->version( '1.0' )
);

$manager->add(
    Style::make( 'my-style', plugin_dir_url( __FILE__ ) . 'app.css' )
        ->version( '1.0' )
);

// Or use the convenience helpers (returns the object for further chaining):
$manager->script( 'my-extra', plugin_dir_url( __FILE__ ) . 'extra.js' )
    ->deps( array( 'my-app' ) )
    ->footer();

$manager->style( 'my-extra-style', plugin_dir_url( __FILE__ ) . 'extra.css' );

// Enqueue everything:
$manager->enqueue();
```

### AssetServiceProvider

Register the provider to have the manager automatically hooked to `wp_enqueue_scripts` and `admin_enqueue_scripts`:

```php
$app->register( \WPFlint\Assets\AssetServiceProvider::class );
```

Then resolve the manager from the container and add assets in your plugin's service provider:

```php
public function boot(): void {
    $assets = $this->app->make( \WPFlint\Assets\AssetManager::class );

    $assets->script( 'my-plugin', plugin_dir_url( PLUGIN_FILE ) . 'assets/js/app.js' )
        ->deps( array( 'jquery' ) )
        ->footer()
        ->version( MY_PLUGIN_VERSION );

    $assets->style( 'my-plugin', plugin_dir_url( PLUGIN_FILE ) . 'assets/css/app.css' )
        ->version( MY_PLUGIN_VERSION );
}
```

---

## Conditional Loading Examples

```php
// Admin-only script:
Script::make( 'admin-utils', plugin_dir_url( __FILE__ ) . 'admin.js' )
    ->only_on( 'is_admin' )
    ->footer()
    ->enqueue();

// Only on the checkout page:
Style::make( 'checkout-style', plugin_dir_url( __FILE__ ) . 'checkout.css' )
    ->only_on( function () {
        return function_exists( 'is_checkout' ) && is_checkout();
    } )
    ->enqueue();

// Only when a specific plugin is active:
Script::make( 'woo-integration', plugin_dir_url( __FILE__ ) . 'woo.js' )
    ->only_on( function () {
        return class_exists( 'WooCommerce' );
    } )
    ->footer()
    ->enqueue();
```

---

## API Summary

| Method | Script | Style |
|--------|--------|-------|
| `make($handle, $src)` | ✓ | ✓ |
| `deps(array)` | ✓ | ✓ |
| `version(string)` | ✓ | ✓ |
| `only_on(callable)` | ✓ | ✓ |
| `enqueue()` | ✓ | ✓ |
| `register_asset()` | ✓ | ✓ |
| `footer(bool)` | ✓ | — |
| `localize(string, array)` | ✓ | — |
| `media(string)` | — | ✓ |
