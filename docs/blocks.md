# Block Registration (Gutenberg)

WPFlint wraps `register_block_type()` behind a fluent builder so you can register Gutenberg blocks without wrestling with the raw args array.

## Basic Usage

```php
use WPFlint\Blocks\Block;

Block::make( 'my-plugin/pricing-table' )
    ->title( 'Pricing Table' )
    ->category( 'widgets' )
    ->icon( 'dashicons-cart' )
    ->editor_script( 'my-plugin-blocks' )
    ->render( function ( array $attrs, string $content ): string {
        return '<div class="pricing-table">' . wp_kses_post( $content ) . '</div>';
    } )
    ->register();
```

Register inside the `init` hook:

```php
add_action( 'init', function () {
    Block::make( 'my-plugin/pricing-table' )
        ->title( 'Pricing Table' )
        ->register();
} );
```

---

## API Reference

### `Block::make( string $name ): self`

Static factory. `$name` must follow the `namespace/block-name` format (e.g. `'my-plugin/call-to-action'`).

### `title( string $title ): self`

Human-readable block title shown in the block inserter.

### `category( string $category ): self`

Block category slug. WordPress built-ins: `'text'`, `'media'`, `'design'`, `'widgets'`, `'theme'`, `'embed'`. Defaults to `''` (not set).

### `icon( string $icon ): self`

Dashicon class (e.g. `'dashicons-admin-tools'`) or an inline SVG string.

### `description( string $description ): self`

Short description shown in the block inserter tooltip.

### `keywords( array $keywords ): self`

Array of strings used for fuzzy search in the block inserter.

```php
->keywords( array( 'price', 'plan', 'subscription' ) )
```

### `attributes( array $attributes ): self`

Block attribute definitions following the block.json schema. Each key is an attribute name; the value is a type definition array.

```php
->attributes( array(
    'title'     => array( 'type' => 'string',  'default' => '' ),
    'highlight' => array( 'type' => 'boolean', 'default' => false ),
    'columns'   => array( 'type' => 'integer', 'default' => 3 ),
) )
```

### `editor_script( string $handle ): self`

Registered script handle for the block's editor JavaScript (built with `@wordpress/scripts` or similar).

### `script( string $handle ): self`

Registered script handle for the block's frontend JavaScript.

### `style( string $handle ): self`

Registered style handle applied on both frontend and editor.

### `editor_style( string $handle ): self`

Registered style handle applied in the editor only.

### `render( callable $callback ): self`

Server-side render callback. Receives:

| Parameter | Type | Description |
|-----------|------|-------------|
| `$attrs` | `array` | Block attributes |
| `$content` | `string` | Inner blocks / InnerBlocks content |

Must **return** a string.

```php
->render( function ( array $attrs, string $content ): string {
    $title = isset( $attrs['title'] ) ? esc_html( $attrs['title'] ) : '';
    return "<section class=\"cta\"><h2>{$title}</h2>{$content}</section>";
} )
```

### `to_args(): array`

Returns the args array that will be passed to `register_block_type()`. Only non-empty values are included. Useful for debugging or testing.

### `register(): void`

Calls `register_block_type( $name, $args )`. Safe to call on the `init` hook.

---

## Getters

```php
$block = Block::make( 'my-plugin/card' )->title( 'Card' )->category( 'design' );

$block->get_name();              // 'my-plugin/card'
$block->get_title();             // 'Card'
$block->get_category();          // 'design'
$block->get_keywords();          // []
$block->get_attributes();        // []
$block->get_render_callback();   // null or callable
```

---

## Registering Scripts and Styles

Enqueue your block editor script before or alongside block registration using `wp_register_script()` (or WPFlint's `Script` builder):

```php
use WPFlint\Assets\Script;
use WPFlint\Assets\Style;
use WPFlint\Blocks\Block;

add_action( 'init', function () {

    wp_register_script(
        'my-plugin-blocks',
        plugin_dir_url( __FILE__ ) . 'build/blocks.js',
        array( 'wp-blocks', 'wp-element', 'wp-editor' ),
        '1.0.0'
    );

    wp_register_style(
        'my-plugin-blocks-style',
        plugin_dir_url( __FILE__ ) . 'build/blocks.css',
        array(),
        '1.0.0'
    );

    Block::make( 'my-plugin/hero' )
        ->title( 'Hero Section' )
        ->category( 'design' )
        ->editor_script( 'my-plugin-blocks' )
        ->style( 'my-plugin-blocks-style' )
        ->attributes( array(
            'heading'    => array( 'type' => 'string', 'default' => 'Welcome' ),
            'subheading' => array( 'type' => 'string', 'default' => '' ),
            'align'      => array( 'type' => 'string', 'default' => 'center' ),
        ) )
        ->render( function ( array $attrs ): string {
            return sprintf(
                '<div class="hero hero--%s"><h1>%s</h1><p>%s</p></div>',
                esc_attr( $attrs['align'] ),
                esc_html( $attrs['heading'] ),
                esc_html( $attrs['subheading'] )
            );
        } )
        ->register();
} );
```

---

## Dynamic vs Static Blocks

**Static blocks** — JavaScript handles both editing and rendering. No `render_callback` needed. Use `editor_script()` alone.

**Dynamic blocks** — PHP renders the frontend output. Provide a `render()` callback. The editor still needs `editor_script()` for the edit experience.

```php
// Dynamic block — PHP renders the output:
Block::make( 'my-plugin/latest-posts' )
    ->editor_script( 'my-plugin-blocks' )
    ->render( function ( array $attrs ): string {
        $posts = get_posts( array(
            'numberposts' => $attrs['count'] ?? 3,
            'post_status' => 'publish',
        ) );
        $html = '<ul class="latest-posts">';
        foreach ( $posts as $post ) {
            $html .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">'
                . esc_html( $post->post_title ) . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    } )
    ->register();
```
