# Shortcodes

WPFlint wraps `add_shortcode()` behind a fluent builder that handles attribute merging via `shortcode_atts()` automatically.

## Basic Usage

```php
use WPFlint\Shortcodes\Shortcode;

Shortcode::make( 'my_plugin_button' )
    ->defaults( array( 'color' => 'blue', 'size' => 'medium', 'label' => 'Click me' ) )
    ->render( function ( array $atts, string $content ): string {
        return sprintf(
            '<button class="btn btn-%s btn-%s">%s</button>',
            esc_attr( $atts['color'] ),
            esc_attr( $atts['size'] ),
            esc_html( $atts['label'] )
        );
    } )
    ->register();
```

Register inside your service provider's `boot()` or on the `init` hook.

Usage in post content: `[my_plugin_button color="red" size="large"]`

---

## API Reference

### `Shortcode::make( string $tag ): self`

Static factory. `$tag` is the shortcode name (no brackets).

### `defaults( array $defaults ): self`

Default attribute key/value pairs. Merged with user-supplied attributes via `shortcode_atts()`. Keys not present in `$defaults` are stripped from user input — this is standard WordPress shortcode behaviour.

```php
->defaults( array(
    'color'  => 'blue',
    'size'   => 'medium',
    'target' => '_self',
) )
```

### `render( callable $callback ): self`

The callback that builds the shortcode output. Receives:

| Parameter | Type | Description |
|-----------|------|-------------|
| `$atts` | `array` | Merged attributes (defaults + user-supplied) |
| `$content` | `string` | Inner content between opening/closing tags (empty string for self-closing) |

Must **return** a string — do not echo inside the callback.

```php
->render( function ( array $atts, string $content ): string {
    return '<div class="notice">' . wp_kses_post( $content ) . '</div>';
} )
```

### `register(): void`

Calls `add_shortcode()`. Safe to call inside or after the `init` hook.

### `unregister(): void`

Calls `remove_shortcode()` for this tag.

---

## Returning a View

Shortcodes and the View system integrate naturally — just return `render()`:

```php
use WPFlint\Shortcodes\Shortcode;
use WPFlint\View\View;

Shortcode::make( 'pricing_table' )
    ->defaults( array( 'plan' => 'basic', 'currency' => 'usd' ) )
    ->render( function ( array $atts ): string {
        return View::make( 'shortcodes.pricing-table' )
            ->with( $atts )
            ->render();
    } )
    ->register();
```

---

## Getters

```php
$sc = Shortcode::make( 'my_tag' )->defaults( array( 'color' => 'red' ) );

$sc->get_tag();       // 'my_tag'
$sc->get_defaults();  // ['color' => 'red']
```

---

## Full Example

```php
use WPFlint\Shortcodes\Shortcode;

// Register in your service provider boot():
public function boot(): void {

    Shortcode::make( 'shop_cta' )
        ->defaults( array(
            'text'   => 'Buy Now',
            'url'    => '#',
            'style'  => 'primary',
        ) )
        ->render( function ( array $atts ): string {
            return sprintf(
                '<a href="%s" class="btn btn-%s">%s</a>',
                esc_url( $atts['url'] ),
                esc_attr( $atts['style'] ),
                esc_html( $atts['text'] )
            );
        } )
        ->register();

    Shortcode::make( 'shop_notice' )
        ->defaults( array( 'type' => 'info' ) )
        ->render( function ( array $atts, string $content ): string {
            return sprintf(
                '<div class="shop-notice shop-notice--%s">%s</div>',
                esc_attr( $atts['type'] ),
                wp_kses_post( $content )
            );
        } )
        ->register();
}
```
