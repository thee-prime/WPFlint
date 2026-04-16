# Admin Notices

WPFlint's `Notice` class provides a fluent API for three kinds of admin notices:

| Kind | Description |
|------|-------------|
| **Inline** | Rendered immediately inside your own hook callback |
| **Flash** | Stored in a transient, displayed once on the next page load |
| **Persistent** | Stored in an option, displayed on every page load until dismissed |

---

## Static Factories

Create a notice with one of the four factory methods:

```php
use WPFlint\Admin\Notice;

Notice::success( 'Settings saved.' );
Notice::error( 'Something went wrong.' );
Notice::warning( 'Please check your configuration.' );
Notice::info( 'Your license expires in 7 days.' );
```

Chain `->dismissible()` to add the WordPress dismiss button:

```php
Notice::success( 'Plugin activated!' )->dismissible();
```

---

## Inline Notices

Render the HTML string directly in your own `admin_notices` callback:

```php
add_action( 'admin_notices', function () {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo Notice::warning( 'Please configure your API key.' )->render();
} );
```

### `render(): string`

Returns the HTML `<div class="notice notice-{type}">` string. All content is escaped internally with `esc_attr()` and `wp_kses_post()`.

### `output(): void`

Calls `render()` and echoes the result.

---

## Flash Notices

Stored in a transient keyed by the current user's ID. Displayed once on the next admin page load, then deleted.

```php
// After a form save action:
Notice::success( 'Settings saved.' )->dismissible()->flash();

// Redirect — the notice shows after the redirect:
wp_redirect( admin_url( 'options-general.php?page=my-plugin' ) );
exit;
```

`flash()` automatically registers the `admin_notices` display hook.

### `flash(): void`

Stores the notice and wires up `display_flash()` to `admin_notices`.

### `Notice::display_flash(): void`

Static method. Reads the transient, renders all queued notices, then deletes the transient. You rarely call this directly — `flash()` does it.

**Constants:**

- `Notice::FLASH_TRANSIENT_PREFIX` — `'wpflint_flash_'` (followed by the user ID)

---

## Persistent Notices

Stored in the options table. Displayed on every admin page load until explicitly dismissed.

```php
// After detecting an API error:
Notice::error( 'API key invalid. Please update your settings.' )
    ->dismissible()
    ->persistent( 'my_plugin_api_error' );

// Later — dismiss it (e.g., after the key is fixed):
Notice::dismiss( 'my_plugin_api_error' );
```

### `persistent( string $key ): void`

Stores the notice under `wpflint_notice_{$key}` and registers `display_persistent()` on `admin_notices`.

### `Notice::display_persistent( string $key ): void`

Static method. Reads the option and renders the notice if it exists.

### `Notice::dismiss( string $key ): void`

Deletes the option so the notice no longer appears.

**Constants:**

- `Notice::PERSISTENT_OPTION_PREFIX` — `'wpflint_notice_'`

---

## Dismissible Notices

```php
public function dismissible( bool $dismissible = true ): self
```

Adds the `is-dismissible` CSS class. WordPress's admin JS handles the client-side dismiss behaviour. For persistent notices you also need to hook an AJAX handler to call `Notice::dismiss()`.

---

## Getters

```php
$notice = Notice::success( 'Saved.' )->dismissible();

$notice->get_type();        // 'success'
$notice->get_message();     // 'Saved.'
$notice->is_dismissible();  // true
```

---

## Type Constants

| Constant | Value |
|----------|-------|
| `Notice::SUCCESS` | `'success'` |
| `Notice::ERROR` | `'error'` |
| `Notice::WARNING` | `'warning'` |
| `Notice::INFO` | `'info'` |

---

## Full Example

```php
use WPFlint\Admin\Notice;

// settings-save.php handler
function my_plugin_save_settings(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    check_admin_referer( 'my_plugin_settings' );

    $result = update_option( 'my_plugin_settings', $_POST['my_plugin'] );

    if ( $result ) {
        Notice::success( __( 'Settings saved.', 'my-plugin' ) )
            ->dismissible()
            ->flash();
    } else {
        Notice::error( __( 'Could not save settings. Please try again.', 'my-plugin' ) )
            ->flash();
    }

    wp_redirect( admin_url( 'options-general.php?page=my-plugin' ) );
    exit;
}

// Show a persistent notice when an API key is missing:
add_action( 'admin_init', function () {
    $options = get_option( 'my_plugin_settings', array() );

    if ( empty( $options['api_key'] ) ) {
        Notice::warning( __( 'My Plugin requires an API key.', 'my-plugin' ) )
            ->persistent( 'my_plugin_missing_key' );
    } else {
        // Key is configured — dismiss the notice if it was set.
        Notice::dismiss( 'my_plugin_missing_key' );
    }
} );
```
