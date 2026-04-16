# Settings API

WPFlint wraps the verbose WordPress Settings API (`register_setting()`, `add_settings_section()`, `add_settings_field()`) behind a concise fluent builder.

## Basic Usage

```php
use WPFlint\Settings\Settings;

Settings::make( 'my_plugin_options', 'my_plugin_settings' )
    ->page( 'my-plugin-settings' )
    ->section( 'general', 'General Settings', function ( \WPFlint\Settings\Section $s ) {

        $s->field( 'api_key', 'API Key' )
            ->type( 'text' )
            ->description( 'Your API key from the dashboard.' )
            ->required();

        $s->field( 'debug_mode', 'Debug Mode' )
            ->type( 'checkbox' );

    } )
    ->register();
```

Register inside your service provider's `boot()` method or on the `admin_init` hook.

---

## Settings

### `Settings::make( string $option_group, string $option_name ): self`

Static factory.

- `$option_group` — The settings group name used by `settings_fields()` in your page template.
- `$option_name` — The WordPress option name where all values are stored as a single serialised array.

### `page( string $page_slug ): self`

The admin page slug where the settings form is rendered. Used by `add_settings_section()` / `add_settings_field()` internally.

### `sanitize( callable $callback ): self`

Optional global sanitise callback passed to `register_setting()`. Receives the raw `$_POST` data before WordPress saves it.

```php
->sanitize( function ( array $input ): array {
    $input['api_key'] = sanitize_text_field( $input['api_key'] ?? '' );
    return $input;
} )
```

### `section( string $id, string $title, callable $builder ): self`

Adds a settings section. The `$builder` callable receives a `Section` instance and is responsible for adding fields to it.

Returns `$this` for chaining.

### `register(): void`

Calls `register_setting()`, then iterates all sections and fields, calling `add_settings_section()` and `add_settings_field()` for each.

Call from `admin_init`:

```php
add_action( 'admin_init', function () {
    Settings::make( 'my_plugin_options', 'my_plugin_settings' )
        ->page( 'my-plugin-settings' )
        ->section( 'general', 'General', function ( $s ) { ... } )
        ->register();
} );
```

---

## Sections

A `Section` instance is passed to the `$builder` closure in `Settings::section()`. Sections group related fields visually and logically.

### `field( string $id, string $label ): Field`

Adds a field to the section and returns the `Field` instance for further configuration.

### `description( string $text ): self`

Sets a description paragraph shown at the top of the section.

---

## Fields

`Field` is returned by `Section::field()`. Chain setters on it to configure the field.

### `type( string $type ): self`

Input type. Supported: `text` (default), `textarea`, `checkbox`, `select`, `number`, `email`, `url`, `password`.

### `description( string $text ): self`

Helper text displayed below the input.

### `default_value( $value ): self`

Default value shown when no option is saved yet.

### `required(): self`

Marks the field as required (adds `required` attribute to the input).

### `options( array $options ): self`

For `select` fields — associative array of `value => label` pairs.

```php
$s->field( 'currency', 'Currency' )
    ->type( 'select' )
    ->options( array(
        'usd' => 'US Dollar',
        'eur' => 'Euro',
        'gbp' => 'British Pound',
    ) );
```

---

## Rendering the Form

In your admin page template, use the standard WordPress Settings API render helpers:

```php
<form method="post" action="options.php">
    <?php settings_fields( 'my_plugin_options' ); ?>
    <?php do_settings_sections( 'my-plugin-settings' ); ?>
    <?php submit_button(); ?>
</form>
```

Reading saved values:

```php
$options = get_option( 'my_plugin_settings', array() );
$api_key = $options['api_key'] ?? '';
```

---

## Full Example

```php
use WPFlint\Settings\Settings;

add_action( 'admin_init', function () {

    Settings::make( 'my_shop_options', 'my_shop_settings' )
        ->page( 'my-shop-settings' )
        ->sanitize( function ( array $input ): array {
            $input['api_key']   = sanitize_text_field( $input['api_key'] ?? '' );
            $input['debug']     = isset( $input['debug'] ) ? '1' : '0';
            $input['currency']  = sanitize_text_field( $input['currency'] ?? 'usd' );
            return $input;
        } )
        ->section( 'api', 'API Settings', function ( \WPFlint\Settings\Section $s ) {

            $s->description( 'Configure your API credentials.' );

            $s->field( 'api_key', 'API Key' )
                ->type( 'text' )
                ->description( 'Found in your dashboard under API keys.' )
                ->required();

        } )
        ->section( 'general', 'General', function ( \WPFlint\Settings\Section $s ) {

            $s->field( 'currency', 'Currency' )
                ->type( 'select' )
                ->options( array( 'usd' => 'USD', 'eur' => 'EUR' ) )
                ->default_value( 'usd' );

            $s->field( 'debug', 'Debug Mode' )
                ->type( 'checkbox' )
                ->description( 'Log extra output to the error log.' );

        } )
        ->register();
} );
```
