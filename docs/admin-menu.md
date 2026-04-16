# Admin Menu & Pages

WPFlint provides a fluent builder over `add_menu_page()` and `add_submenu_page()` that removes boilerplate and keeps your admin registration readable.

## Basic Usage

```php
use WPFlint\Admin\AdminPage;

AdminPage::make( 'My Plugin', 'my-plugin' )
    ->capability( 'manage_options' )
    ->icon( 'dashicons-admin-tools' )
    ->position( 80 )
    ->render( function () {
        echo '<div class="wrap"><h1>My Plugin</h1></div>';
    } )
    ->register();
```

Register inside a service provider's `boot()` method, on the `admin_menu` action:

```php
public function boot(): void {
    add_action( 'admin_menu', function () {
        AdminPage::make( 'My Plugin', 'my-plugin' )
            ->capability( 'manage_options' )
            ->render( function () {
                // render your admin page
            } )
            ->register();
    } );
}
```

---

## Top-Level Pages

### `AdminPage::make( string $title, string $slug ): self`

Static factory. Creates a top-level menu page.

- `$title` — Page title shown in the browser tab.
- `$slug` — URL slug for the admin page (`?page=my-plugin`).

### `capability( string $cap ): self`

WordPress capability required to see this page. Defaults to `'manage_options'`.

### `icon( string $icon ): self`

Dashicon class (e.g. `'dashicons-admin-tools'`) or a URL to a custom icon, or `'none'` for no icon. Defaults to `''`.

### `position( int $position ): self`

Menu position in the admin sidebar. Defaults to `null` (end of menu).

### `render( callable $callback ): self`

Callback that outputs the page HTML. Called when the user visits the page.

```php
->render( function () {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
    // your content
    echo '</div>';
} )
```

### `register(): void`

Calls `add_menu_page()` and then recursively registers all subpages.

---

## Subpages

### `submenu( string $title, string $slug, callable $render ): self`

Adds a submenu page under this top-level page. Returns the parent `AdminPage` instance so you can chain more calls.

```php
AdminPage::make( 'My Plugin', 'my-plugin' )
    ->capability( 'manage_options' )
    ->render( function () { /* main page */ } )
    ->submenu( 'Settings', 'my-plugin-settings', function () {
        echo '<div class="wrap"><h1>Settings</h1></div>';
    } )
    ->submenu( 'Tools', 'my-plugin-tools', function () {
        echo '<div class="wrap"><h1>Tools</h1></div>';
    } )
    ->register();
```

Each submenu page inherits the parent's `capability` by default. Override it on the submenu's `AdminPage` instance if needed (see Advanced below).

### `parent_slug( string $slug ): self`

Set the parent menu slug for this page when it will be registered as a submenu. Called automatically by `submenu()` — you rarely need this directly.

---

## Advanced: Separate Subpage Instances

For more control, build subpages separately and attach them:

```php
$settings_page = AdminPage::make( 'Settings', 'my-plugin-settings' )
    ->capability( 'manage_options' )
    ->render( function () { /* ... */ } );

AdminPage::make( 'My Plugin', 'my-plugin' )
    ->render( function () { /* ... */ } )
    ->add_subpage( $settings_page )
    ->register();
```

---

## Getters

```php
$page = AdminPage::make( 'Title', 'slug' );

$page->get_title();       // 'Title'
$page->get_slug();        // 'slug'
$page->get_capability();  // 'manage_options'
$page->get_icon();        // ''
$page->get_position();    // null
$page->get_subpages();    // []
```

---

## Full Example

```php
use WPFlint\Admin\AdminPage;
use WPFlint\View\View;

add_action( 'admin_menu', function () {

    AdminPage::make( 'My Shop', 'my-shop' )
        ->capability( 'manage_options' )
        ->icon( 'dashicons-cart' )
        ->position( 56 )
        ->render( function () {
            View::make( 'admin.dashboard' )->output();
        } )
        ->submenu( 'Orders', 'my-shop-orders', function () {
            View::make( 'admin.orders' )->output();
        } )
        ->submenu( 'Settings', 'my-shop-settings', function () {
            View::make( 'admin.settings' )->output();
        } )
        ->register();
} );
```
