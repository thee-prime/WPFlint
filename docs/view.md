# Views & Templates

WPFlint's `View` class is a lightweight PHP template renderer that uses dot-notation paths, scoped variable extraction, and output buffering. Zero external dependencies.

## Setup

Set the base directory once — typically in your plugin bootstrap or a service provider:

```php
use WPFlint\View\View;

// Absolute path to your templates directory:
View::set_base_path( plugin_dir_path( __FILE__ ) . 'resources/views' );
```

Or register `ViewServiceProvider` to have it resolve `resources/views` relative to your plugin root automatically:

```php
$app->register( \WPFlint\View\ViewServiceProvider::class );
```

---

## Basic Usage

```php
use WPFlint\View\View;

// Render and return HTML as a string:
$html = View::make( 'admin.settings' )
    ->with( array( 'title' => 'Settings', 'options' => $options ) )
    ->render();

echo $html;

// Or output directly:
View::make( 'partials.notice' )
    ->with( 'message', 'Saved!' )
    ->output();
```

---

## Path Resolution

Dot notation maps to directory separators:

| Template identifier | Resolved path |
|---------------------|---------------|
| `'admin.settings'` | `{base}/admin/settings.php` |
| `'emails.confirm'` | `{base}/emails/confirm.php` |
| `'partials.notice'` | `{base}/partials/notice.php` |

---

## API Reference

### `View::set_base_path( string $path ): void`

Sets the default base directory for all templates. Call once during plugin bootstrap.

### `View::get_base_path(): string`

Returns the current default base path.

### `View::make( string $template, string $base_path = '' ): static`

Factory. Creates a `View` instance for the given dot-notation template. Pass `$base_path` to override the default for this view only.

### `with( array|string $key, mixed $value = null ): self`

Pass data to the template. Accepts either an associative array or a key/value pair:

```php
->with( array( 'title' => 'Page Title', 'items' => $items ) )
->with( 'title', 'Page Title' )   // key/value shorthand
```

Multiple `with()` calls accumulate — later calls merge into existing data.

### `from( string $path ): self`

Override the base path for this view instance only:

```php
View::make( 'email.confirm' )
    ->from( get_template_directory() . '/email-templates' )
    ->with( 'user', $user )
    ->render();
```

### `render(): string`

Renders the template, extracts data into local scope, and returns the output as a string. Uses `EXTR_SKIP` — existing local variables are never overwritten.

### `output(): void`

Calls `render()` and immediately echoes the result.

### `get_path(): string`

Returns the resolved absolute file path without rendering.

```php
View::make( 'admin.settings' )->get_path();
// /path/to/plugin/resources/views/admin/settings.php
```

### `get_template(): string`

Returns the raw dot-notation template identifier.

### `get_data(): array`

Returns the data array that will be passed to the template.

---

## Template Files

Templates are plain PHP files. All variables passed via `with()` are available as local variables:

```php
<!-- resources/views/admin/settings.php -->
<div class="wrap">
    <h1><?php echo esc_html( $title ); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields( $option_group ); ?>
        <?php do_settings_sections( $page_slug ); ?>
        <?php submit_button(); ?>
    </form>
</div>
```

Call another view from within a template (partials):

```php
<!-- resources/views/admin/dashboard.php -->
<?php echo \WPFlint\View\View::make( 'partials.header' )->with( 'title', $title )->render(); ?>
<div class="dashboard-content">
    <!-- content -->
</div>
```

---

## Integration with Other Modules

**Shortcodes:**

```php
Shortcode::make( 'pricing_table' )
    ->render( function ( array $atts ): string {
        return View::make( 'shortcodes.pricing-table' )->with( $atts )->render();
    } )
    ->register();
```

**Email templates:**

```php
Mail::to( $user->user_email )
    ->subject( 'Order Confirmed' )
    ->template( 'emails.order-confirmed', array( 'order' => $order ) )
    ->send();
```

**Admin pages:**

```php
AdminPage::make( 'Dashboard', 'my-plugin' )
    ->render( function () {
        View::make( 'admin.dashboard' )
            ->with( array( 'stats' => my_plugin_get_stats() ) )
            ->output();
    } )
    ->register();
```

---

## ViewServiceProvider

Registers the View base path automatically from the Application's base path:

```php
// Resolves to: {$app->base_path()}/resources/views
$app->register( \WPFlint\View\ViewServiceProvider::class );
```

You can still call `View::set_base_path()` manually after registration to override.
