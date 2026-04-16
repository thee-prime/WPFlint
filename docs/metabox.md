# Metabox Builder

WPFlint's `MetaBox` class wraps `add_meta_box()` and the `save_post` hook behind a fluent API. Fields are rendered and saved automatically — nonce generation, nonce verification, autosave guards, and capability checks are all handled internally.

## Basic Usage

```php
use WPFlint\Admin\MetaBox;

$box = MetaBox::make( 'book_details', 'Book Details' )
    ->screen( 'book' )
    ->context( 'normal' )
    ->priority( 'high' );

$box->field( '_isbn',    'ISBN' )->type( 'text' );
$box->field( '_pages',   'Page Count' )->type( 'number' );
$box->field( '_summary', 'Summary' )->type( 'textarea' );

$box->register();
```

Register inside the `add_meta_boxes` action (or `init`):

```php
add_action( 'add_meta_boxes', function () use ( $box ) {
    $box->register();
} );
```

Or from a service provider's `boot()`:

```php
public function boot(): void {
    add_action( 'add_meta_boxes', function () {
        MetaBox::make( 'book_details', 'Book Details' )
            ->screen( 'book' )
            ->field( '_isbn', 'ISBN' )
            ->register();
    } );
}
```

---

## MetaBox API

### `MetaBox::make( string $id, string $title ): self`

Static factory.

- `$id` — HTML/CSS id for the metabox, also used to derive the nonce action.
- `$title` — Visible title rendered in the metabox header.

### `screen( string|array $screen ): self`

Post type(s) where the metabox appears. Accepts a string or an array of post type slugs.

```php
->screen( 'post' )
->screen( array( 'post', 'page', 'product' ) )
```

Defaults to `'post'`.

### `context( string $context ): self`

Display context: `'normal'` (default), `'side'`, or `'advanced'`.

### `priority( string $priority ): self`

Display priority: `'default'` (default), `'high'`, `'core'`, or `'low'`.

### `field( string $id, string $label ): MetaBoxField`

Adds a field and returns the `MetaBoxField` instance for further configuration. The field is stored internally — call `register()` on the `MetaBox` after all fields are configured.

```php
$box = MetaBox::make( 'event_details', 'Event Details' )->screen( 'event' );

$box->field( '_event_date',     'Date' )->type( 'text' );
$box->field( '_event_location', 'Location' )->type( 'text' )->description( 'City or venue name.' );
$box->field( '_event_price',    'Price' )->type( 'number' )->default_value( '0' );

$box->register();
```

### `register(): void`

- Calls `add_meta_box()` with the render callback.
- Hooks a `save_post` callback that: verifies the nonce, skips autosaves, checks `edit_post` capability, then calls `save()` on each field.

### Getters

```php
$box->get_id();            // 'book_details'
$box->get_title();         // 'Book Details'
$box->get_screen();        // 'book'
$box->get_context();       // 'normal'
$box->get_priority();      // 'default'
$box->get_nonce_action();  // 'book_details_nonce'
$box->get_fields();        // MetaBoxField[]
```

---

## MetaBoxField API

`MetaBoxField` is returned by `MetaBox::field()`. Chain setters before calling `MetaBox::register()`.

### `type( string $type ): self`

Input type. Supported: `text` (default), `textarea`, `checkbox`, `select`, `number`, `email`, `url`.

### `description( string $text ): self`

Helper text shown below the input.

### `default_value( mixed $value ): self`

Value shown when no meta has been saved for the field.

### `options( array $options ): self`

Options for `select` fields — associative array of `value => label`.

```php
$box->field( '_status', 'Status' )
    ->type( 'select' )
    ->options( array(
        'draft'     => 'Draft',
        'published' => 'Published',
        'archived'  => 'Archived',
    ) )
    ->default_value( 'draft' );
```

### `sanitize_with( callable $callback ): self`

Custom sanitise callback. Receives the raw `$_POST` value after `wp_unslash()` and must return the sanitised value. Overrides the built-in type-based sanitisation.

```php
$box->field( '_price', 'Price' )
    ->type( 'text' )
    ->sanitize_with( function ( $value ) {
        return max( 0.0, (float) $value );
    } );
```

### Built-in Sanitisation

| Type | Sanitise function |
|------|-------------------|
| `text` | `sanitize_text_field()` |
| `textarea` | `sanitize_textarea_field()` |
| `checkbox` | stores `'1'` when checked, deletes meta when unchecked |
| `select`, `number`, `email`, `url` | `sanitize_text_field()` |

Override with `sanitize_with()` for stricter rules (e.g. `absint`, `floatval`, `sanitize_email`).

### Getters

```php
$field->get_id();          // '_isbn'
$field->get_label();       // 'ISBN'
$field->get_type();        // 'text'
$field->get_description(); // ''
$field->get_default();     // ''
$field->get_options();     // []
```

---

## Reading Saved Values

Saved fields are standard WordPress post meta. Read them with `get_post_meta()`:

```php
$isbn    = get_post_meta( $post->ID, '_isbn', true );
$summary = get_post_meta( $post->ID, '_summary', true );
$active  = (bool) get_post_meta( $post->ID, '_active', true );
```

---

## Full Example

```php
use WPFlint\Admin\MetaBox;

add_action( 'add_meta_boxes', function () {

    $box = MetaBox::make( 'product_details', 'Product Details' )
        ->screen( 'product' )
        ->context( 'normal' )
        ->priority( 'high' );

    $box->field( '_sku', 'SKU' )
        ->type( 'text' )
        ->description( 'Unique product identifier.' )
        ->required();

    $box->field( '_price', 'Price (USD)' )
        ->type( 'number' )
        ->default_value( '0' )
        ->sanitize_with( function ( $v ) {
            return number_format( max( 0.0, (float) $v ), 2, '.', '' );
        } );

    $box->field( '_in_stock', 'In Stock' )
        ->type( 'checkbox' )
        ->default_value( '1' );

    $box->field( '_condition', 'Condition' )
        ->type( 'select' )
        ->options( array(
            'new'          => 'New',
            'refurbished'  => 'Refurbished',
            'used'         => 'Used',
        ) )
        ->default_value( 'new' );

    $box->field( '_notes', 'Internal Notes' )
        ->type( 'textarea' )
        ->description( 'Not shown to customers.' );

    $box->register();
} );
```
