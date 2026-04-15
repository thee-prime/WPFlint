# Registration Module

Fluent builders for registering WordPress custom post types, taxonomies, and meta fields.

## Overview

The Registration module provides three builder classes:

- `PostType` — wraps `register_post_type()`
- `Taxonomy` — wraps `register_taxonomy()`
- `MetaField` — wraps `register_post_meta()`, `register_term_meta()`, or `register_meta()`

All builders are standalone value objects. You do not need to register `RegistrationServiceProvider` to use them.

## Service Provider

```php
$app->register( WPFlint\Registration\RegistrationServiceProvider::class );
```

The provider is a convenience entry point. The builders work identically without it.

## PostType

### Basic usage

```php
use WPFlint\Registration\PostType;

add_action( 'init', function() {
    PostType::make( 'book' )
        ->label( 'Book', 'Books' )
        ->public()
        ->supports( [ 'title', 'editor', 'thumbnail' ] )
        ->icon( 'dashicons-book-alt' )
        ->show_in_rest()
        ->register();
} );
```

### API

| Method | Description |
|--------|-------------|
| `PostType::make( string $slug )` | Static factory |
| `->label( string $singular, string $plural = '' )` | Set labels (plural defaults to singular + 's') |
| `->public( bool $public = true )` | Make publicly accessible |
| `->exclude_from_search( bool $exclude = true )` | Hide from search results |
| `->publicly_queryable( bool $queryable = true )` | Control front-end query access |
| `->show_in_menu( bool $show = true )` | Show in admin nav menu |
| `->show_in_rest( bool $show = true )` | Expose via REST API |
| `->rest_base( string $base )` | Set REST API base slug |
| `->hierarchical( bool $hierarchical = true )` | Enable parent/child (like pages) |
| `->supports( array $features )` | Set supported features |
| `->icon( string $icon )` | Dashicon or image URL |
| `->menu_position( int $position )` | Admin menu position |
| `->has_archive( bool\|string $archive = true )` | Enable archive / custom slug |
| `->rewrite( array\|bool $rewrite )` | Permalink rewrite options |
| `->capability_type( string $type, bool $map_meta = true )` | Capability type |
| `->taxonomies( array $taxonomies )` | Attach taxonomies at registration |
| `->args( array $args )` | Merge extra args (takes precedence) |
| `->register()` | Call `register_post_type()` |
| `->unregister()` | Call `unregister_post_type()` |
| `->registered()` | Returns `post_type_exists()` |
| `->get_slug()` | Return the slug |
| `->get_args()` | Build and return args without registering |

### Auto-generated labels

When `->label()` is called, `get_args()` builds a complete labels array:

- `name`, `singular_name`
- `add_new_item`, `edit_item`, `new_item`
- `view_item`, `view_items`
- `search_items`, `not_found`, `not_found_in_trash`
- `all_items`, `menu_name`

## Taxonomy

### Basic usage

```php
use WPFlint\Registration\Taxonomy;

add_action( 'init', function() {
    Taxonomy::make( 'genre' )
        ->label( 'Genre', 'Genres' )
        ->for( 'book' )
        ->hierarchical()
        ->show_in_rest()
        ->register();
} );
```

### Attaching to multiple post types

```php
Taxonomy::make( 'genre' )
    ->for( [ 'book', 'magazine' ] )
    ->register();

// Or chain:
Taxonomy::make( 'genre' )
    ->for( 'book' )
    ->for( 'magazine' )
    ->register();
```

### API

| Method | Description |
|--------|-------------|
| `Taxonomy::make( string $slug )` | Static factory |
| `->label( string $singular, string $plural = '' )` | Set labels |
| `->for( string\|array $post_types )` | Attach to post type(s) |
| `->public( bool $public = true )` | Make public |
| `->show_in_rest( bool $show = true )` | Expose via REST API |
| `->rest_base( string $base )` | REST API base slug |
| `->show_admin_column( bool $show = true )` | Show count column in admin list |
| `->show_tagcloud( bool $show = true )` | Show in tag cloud widget |
| `->hierarchical( bool $hierarchical = true )` | Enable parent/child (like categories) |
| `->rewrite( array\|bool $rewrite )` | Permalink rewrite options |
| `->args( array $args )` | Merge extra args |
| `->register()` | Call `register_taxonomy()` |
| `->unregister()` | Call `unregister_taxonomy()` |
| `->registered()` | Returns `taxonomy_exists()` |
| `->get_slug()` | Return the slug |
| `->get_post_types()` | Return attached post types |
| `->get_args()` | Build and return args without registering |

### Auto-generated labels

- `name`, `singular_name`
- `search_items`, `all_items`
- `parent_item`, `parent_item_colon`
- `edit_item`, `update_item`
- `add_new_item`, `new_item_name`
- `menu_name`

## MetaField

### Basic usage

```php
use WPFlint\Registration\MetaField;

add_action( 'init', function() {
    // Post meta
    MetaField::post( 'book', '_price' )
        ->type( 'number' )
        ->single()
        ->sanitize( 'floatval' )
        ->show_in_rest()
        ->register();

    // Term meta
    MetaField::term( 'genre', '_color' )
        ->type( 'string' )
        ->single()
        ->register();

    // User meta
    MetaField::user( '_bio_extra' )
        ->type( 'string' )
        ->single()
        ->show_in_rest()
        ->register();

    // Comment meta
    MetaField::comment( '_rating' )
        ->type( 'integer' )
        ->single()
        ->register();
} );
```

### Static factories

| Factory | Underlying WP function |
|---------|------------------------|
| `MetaField::post( string $post_type, string $key )` | `register_post_meta( $post_type, $key, $args )` |
| `MetaField::term( string $taxonomy, string $key )` | `register_term_meta( $taxonomy, $key, $args )` |
| `MetaField::user( string $key )` | `register_meta( 'user', $key, $args )` |
| `MetaField::comment( string $key )` | `register_meta( 'comment', $key, $args )` |

Pass an empty string as `$post_type` or `$taxonomy` to apply to all subtypes.

### API

| Method | Description |
|--------|-------------|
| `->type( string $type )` | Value type: `string`, `boolean`, `integer`, `number`, `array`, `object` |
| `->single( bool $single = true )` | Single value (not array of values) |
| `->default( mixed $value )` | Default value |
| `->description( string $description )` | Human-readable description |
| `->sanitize( callable $callback )` | Sanitization callback |
| `->auth_callback( callable $callback )` | Authorization callback |
| `->show_in_rest( bool\|array $schema = true )` | Expose via REST API (optionally with JSON Schema) |
| `->args( array $args )` | Merge extra args |
| `->register()` | Register the meta field |
| `->unregister()` | Call `unregister_meta_key()` |
| `->get_key()` | Return the meta key |
| `->get_object_type()` | Return `'post'`, `'term'`, `'user'`, or `'comment'` |
| `->get_subtype()` | Return the post type or taxonomy slug |
| `->get_args()` | Return args without registering |

### REST API schema

```php
MetaField::post( 'book', '_price' )
    ->show_in_rest( [
        'schema' => [
            'type'        => 'number',
            'description' => 'Book price in USD',
        ],
    ] )
    ->register();
```

## Using inside a Service Provider

```php
use WPFlint\Providers\ServiceProvider;
use WPFlint\Registration\PostType;
use WPFlint\Registration\Taxonomy;
use WPFlint\Registration\MetaField;

class BookServiceProvider extends ServiceProvider {

    public function boot(): void {
        add_action( 'init', function() {
            PostType::make( 'book' )
                ->label( 'Book', 'Books' )
                ->public()
                ->show_in_rest()
                ->register();

            Taxonomy::make( 'genre' )
                ->label( 'Genre', 'Genres' )
                ->for( 'book' )
                ->register();

            MetaField::post( 'book', '_price' )
                ->type( 'number' )
                ->single()
                ->register();
        } );
    }
}
```
