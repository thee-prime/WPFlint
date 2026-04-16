# WPFlint

A Laravel-inspired framework for building WordPress plugins. Zero production dependencies. PHP 7.4+.

WPFlint gives you the tools you expect from a modern PHP framework — IoC container, Eloquent-style ORM, migrations, routing, middleware, validation, events, caching, views, mail, admin builders, and Gutenberg support — all built on top of WordPress APIs and fully compliant with WP.org plugin guidelines.

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Architecture Overview](#architecture-overview)
- [Core Framework](#core-framework)
  - [Application](#application)
  - [Service Container](#service-container)
  - [Service Providers](#service-providers)
  - [Configuration](#configuration)
  - [Facades](#facades)
- [HTTP Layer](#http-layer)
  - [Routing](#routing)
  - [Middleware](#middleware)
  - [Controllers](#controllers)
  - [Requests & Validation](#requests--validation)
  - [Responses](#responses)
  - [REST API Auth Helpers](#rest-api-auth-helpers)
- [Database](#database)
  - [Migrations](#database-migrations)
  - [Schema Builder](#database-schema-builder)
  - [Query Builder](#database-query-builder)
  - [ORM](#database-orm)
  - [Relationships](#relationships)
- [WordPress UI](#wordpress-ui)
  - [Admin Menu & Pages](#admin-menu--pages)
  - [Settings API](#settings-api)
  - [Admin Notices](#admin-notices)
  - [Metabox Builder](#metabox-builder)
  - [Shortcodes](#shortcodes)
  - [Block Registration](#block-registration)
  - [Widgets](#widgets)
- [Templates & Mail](#templates--mail)
  - [Views](#views)
  - [Mail](#mail)
- [Plugin Infrastructure](#plugin-infrastructure)
  - [Plugin Lifecycle](#plugin-lifecycle)
  - [Asset Manager](#asset-manager)
  - [Events](#events)
  - [Cache](#cache)
  - [Logging](#logging)
  - [Queue & Jobs](#queue--jobs)
  - [Scheduler](#scheduler)
- [WP-CLI Commands](#wp-cli-commands)
- [Testing](#testing)
- [WP.org Compliance](#wporg-compliance)
- [Directory Structure](#directory-structure)

---

## Installation

```bash
composer require wpflint/wpflint
```

## Quick Start

Create your main plugin file:

```php
<?php
/**
 * Plugin Name: My Shop
 * Text Domain: my-shop
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use WPFlint\Application;
use WPFlint\Lifecycle\Lifecycle;

// Wire up activation / deactivation / uninstall BEFORE autoload runs.
Lifecycle::for( __FILE__ )
    ->on_activate( function () { \MyShop\Installer::activate(); } )
    ->on_deactivate( function () { \MyShop\Installer::deactivate(); } )
    ->on_uninstall( \MyShop\Installer::class )
    ->register();

$app = Application::get_instance( __DIR__ );
$app->register( MyShop\Providers\ShopServiceProvider::class );
$app->bootstrap();
```

---

## Architecture Overview

```
Application (extends Container)
    |
    +-- ServiceProviders register bindings (register())
    |       +-- boot() runs on init
    |
    +-- Router        AJAX + REST routing + middleware pipeline
    +-- Migrator      database schema version control
    +-- Dispatcher    typed event system
    +-- CacheManager  multi-driver caching with tag invalidation
    +-- AssetManager  script/style enqueueing with conditional loading
```

Service providers are the central place to configure your plugin. Register everything — routes, event listeners, admin pages, metaboxes, widgets, assets — in `boot()`.

---

## Core Framework

### Application

```php
use WPFlint\Application;

$app = Application::get_instance( __DIR__ );  // get or create singleton
$app->register( MyServiceProvider::class );    // register a provider
$app->bootstrap();                              // hook into WP lifecycle

$app->base_path();                 // plugin root directory
$app->base_path( 'config/db.php' ); // append a path
$app->is_booted();                 // bool
```

[Full documentation →](docs/application.md)

### Service Container

PSR-11 compliant IoC container with auto-resolution, singletons, and contextual bindings.

```php
// Binding
$app->bind( LoggerInterface::class, FileLogger::class );
$app->singleton( CacheManager::class, fn( $app ) => new CacheManager( $app ) );
$app->instance( 'config', $config );

// Resolution
$service = $app->make( OrderService::class ); // auto-resolves constructor deps
$service = $app->get( OrderService::class );   // PSR-11 alias

// Contextual bindings
$app->when( OrderService::class )
    ->needs( LoggerInterface::class )
    ->give( OrderLogger::class );
```

[Full documentation →](docs/container.md)

### Service Providers

```php
use WPFlint\Providers\ServiceProvider;

class ShopServiceProvider extends ServiceProvider {

    public function register(): void {
        $this->app->singleton( OrderService::class, fn( $app ) => new OrderService( $app ) );
    }

    public function boot(): void {
        // Register routes, hooks, assets, etc.
    }
}
```

Deferred providers are only booted when one of their bindings is requested:

```php
class PaymentServiceProvider extends ServiceProvider {
    public bool $defer = true;
    public function register(): void { ... }
    public function provides(): array { return array( PaymentGateway::class ); }
}
```

[Full documentation →](docs/providers.md)

### Configuration

```php
use WPFlint\Facades\Config;

// config/app.php returns an array — filename is the top-level key.
Config::get( 'app.name' );
Config::get( 'app.missing', 'default' );
Config::set( 'app.debug', true );
Config::has( 'app.name' );
Config::push( 'app.middleware', 'throttle' );
```

[Full documentation →](docs/config.md)

### Facades

```php
use WPFlint\Facades\Cache;
use WPFlint\Facades\Event;
use WPFlint\Facades\Config;

Cache::remember( 'key', 3600, fn() => Order::all() );
Event::fire( new OrderPlaced( $order ) );
Config::get( 'app.name' );
```

Create custom facades:

```php
class Orders extends Facade {
    protected static function get_facade_accessor(): string { return 'orders'; }
}
```

[Full documentation →](docs/facades.md)

---

## HTTP Layer

### Routing

```php
use WPFlint\Http\Router;

$router = $app->make( Router::class );

// AJAX routes
$router->ajax( 'my-shop/save-order', array( OrderController::class, 'store' ) )
    ->middleware( array( 'nonce:save_order', 'can:edit_posts' ) );

$router->ajax( 'my-shop/products', array( ProductController::class, 'index' ) )
    ->nopriv()
    ->middleware( array( 'throttle:60,1' ) );

// REST API routes
$router->rest( 'my-shop/v1', function ( RestRouter $r ) {
    $r->get( '/orders',               array( OrderRestController::class, 'index' ) );
    $r->post( '/orders',              array( OrderRestController::class, 'store' ) );
    $r->get( '/orders/(?P<id>\d+)',   array( OrderRestController::class, 'show' ) );
    $r->put( '/orders/(?P<id>\d+)',   array( OrderRestController::class, 'update' ) );
    $r->delete( '/orders/(?P<id>\d+)', array( OrderRestController::class, 'destroy' ) );
} );
```

[Full documentation →](docs/http.md)

### Middleware

Built-in middleware: `nonce:{action}`, `can:{capability}`, `throttle:{max},{minutes}`.

Custom middleware:

```php
class EnsureStoreIsOpen implements MiddlewareInterface {
    public function handle( Request $request, Closure $next ) {
        if ( ! get_option( 'store_open' ) ) {
            return Response::error( 'Store is closed.', 403 );
        }
        return $next( $request );
    }
}
```

[Full documentation →](docs/http.md)

### Controllers

```php
class OrderController extends Controller {
    public function __construct( private OrderService $orders ) {}

    public function store( StoreOrderRequest $request ): Response {
        // $request already validated + sanitized
        $order = $this->orders->create( $request->validated() );
        return Response::json( $order->to_array(), 201 );
    }
}

class OrderRestController extends RestController {
    protected string $namespace = 'my-shop/v1';
    protected string $rest_base = 'orders';

    public function index( \WP_REST_Request $req ): \WP_REST_Response {
        return $this->respond( Order::all() );
    }
}
```

[Full documentation →](docs/http.md)

### Requests & Validation

```php
class StoreOrderRequest extends Request {
    public function authorize(): bool { return current_user_can( 'edit_posts' ); }
    public function rules(): array {
        return array(
            'status' => 'required|in:pending,paid,cancelled',
            'total'  => 'required|numeric|min:0',
            'items'  => 'required|array|min:1',
        );
    }
    public function sanitize(): array {
        return array( 'status' => 'sanitize_text_field', 'total' => 'floatval' );
    }
}
```

[Full documentation →](docs/validation.md)

### Responses

```php
Response::json( array( 'order' => $order ), 201 );
Response::error( 'Not found.', 404 );
Response::no_content();
$response->with_header( 'X-Custom', 'value' );
$response->send_ajax();
$response->to_rest();
```

[Full documentation →](docs/http.md)

### REST API Auth Helpers

Factory methods that return `permission_callback` callables:

```php
use WPFlint\Http\RestAuth;

// In register_rest_route():
'permission_callback' => RestAuth::capability( 'manage_options' )
'permission_callback' => RestAuth::logged_in()
'permission_callback' => RestAuth::public_access()
'permission_callback' => RestAuth::all_of( 'edit_posts', 'upload_files' )
'permission_callback' => RestAuth::any_of( 'edit_posts', 'edit_pages' )

// Versioned namespace builder:
RestAuth::namespace( 'my-plugin', 1 )   // 'my-plugin/v1'
RestAuth::namespace( 'my-plugin', 2 )   // 'my-plugin/v2'

// Direct boolean checks:
RestAuth::require_logged_in()
RestAuth::require_capability( 'manage_options' )
```

[Full documentation →](docs/rest-auth.md)

---

## Database

### Database: Migrations

```php
class CreateOrdersTable extends Migration {
    public function up(): void {
        $this->schema()->create( 'orders', function ( Blueprint $t ) {
            $t->big_increments( 'id' );
            $t->string( 'status' )->default( 'pending' );
            $t->decimal( 'total', 10, 2 );
            $t->timestamps();
        } );
    }
    public function down(): void { $this->schema()->drop( 'orders' ); }
}

$migrator = new Migrator( new MigrationRepository( 'my-shop' ), array(
    CreateOrdersTable::class,
) );
$migrator->run();
```

[Full documentation →](docs/migrations.md)

### Database: Schema Builder

```php
$table->big_increments( 'id' );
$table->string( 'email' );
$table->decimal( 'total', 10, 2 );
$table->boolean( 'active' );
$table->timestamps();
$table->soft_deletes();
$table->index( 'email' );
$table->unique( 'slug' );
$table->foreign( 'user_id' )->references( 'id' )->on( 'users' )->on_delete( 'CASCADE' );
```

[Full documentation →](docs/schema.md)

### Database: Query Builder

```php
QueryBuilder::table( 'orders' )
    ->where( 'status', 'pending' )
    ->where( 'total', '>', 100 )
    ->order_by( 'created_at', 'DESC' )
    ->limit( 10 )
    ->get();
```

[Full documentation →](docs/orm.md)

### Database: ORM

```php
class Order extends Model {
    protected static string $table = 'orders';
    protected array $fillable = array( 'status', 'total' );
    protected array $casts    = array( 'total' => 'float', 'meta' => 'array' );

    public function scope_pending( ModelQueryBuilder $q ): ModelQueryBuilder {
        return $q->where( 'status', 'pending' );
    }
}

$order  = Order::find( 1 );
$orders = Order::pending()->where( 'total', '>', 50 )->get_models();
$order  = Order::create( array( 'status' => 'pending', 'total' => 99.50 ) );
$order  = Order::cached( 42 );          // cached find (TTL 3600s)
```

[Full documentation →](docs/orm.md)

### Relationships

```php
class User extends Model {
    public function orders(): HasMany { return $this->has_many( Order::class ); }
    public function profile(): HasOne { return $this->has_one( Profile::class ); }
}
class Order extends Model {
    public function user(): BelongsTo { return $this->belongs_to( User::class ); }
}

$orders = $user->orders()->get_results();

// Eager loading (prevents N+1):
$users = User::where( 'active', 1 )->with( array( 'orders', 'profile' ) )->get_models();
```

[Full documentation →](docs/orm.md)

---

## WordPress UI

### Admin Menu & Pages

```php
use WPFlint\Admin\AdminPage;

add_action( 'admin_menu', function () {
    AdminPage::make( 'My Plugin', 'my-plugin' )
        ->capability( 'manage_options' )
        ->icon( 'dashicons-admin-tools' )
        ->position( 80 )
        ->render( function () {
            View::make( 'admin.dashboard' )->output();
        } )
        ->submenu( 'Settings', 'my-plugin-settings', function () {
            View::make( 'admin.settings' )->output();
        } )
        ->submenu( 'Tools', 'my-plugin-tools', function () {
            View::make( 'admin.tools' )->output();
        } )
        ->register();
} );
```

[Full documentation →](docs/admin-menu.md)

### Settings API

```php
use WPFlint\Settings\Settings;

add_action( 'admin_init', function () {
    Settings::make( 'my_plugin_options', 'my_plugin_settings' )
        ->page( 'my-plugin-settings' )
        ->section( 'general', 'General', function ( $s ) {
            $s->field( 'api_key', 'API Key' )->type( 'text' )->required();
            $s->field( 'debug',   'Debug'   )->type( 'checkbox' );
            $s->field( 'mode',    'Mode'    )->type( 'select' )
                ->options( array( 'live' => 'Live', 'test' => 'Test' ) );
        } )
        ->register();
} );

// Read saved values:
$opts    = get_option( 'my_plugin_settings', array() );
$api_key = $opts['api_key'] ?? '';
```

[Full documentation →](docs/settings.md)

### Admin Notices

```php
use WPFlint\Admin\Notice;

// Flash — shown once after redirect:
Notice::success( 'Settings saved.' )->dismissible()->flash();
Notice::error( 'Something went wrong.' )->flash();

// Persistent — shown until dismissed:
Notice::warning( 'API key missing.' )->persistent( 'my_plugin_api_key' );
Notice::dismiss( 'my_plugin_api_key' );

// Inline — inside your own admin_notices callback:
add_action( 'admin_notices', function () {
    echo Notice::info( 'Plugin activated!' )->render();
} );
```

[Full documentation →](docs/notices.md)

### Metabox Builder

```php
use WPFlint\Admin\MetaBox;

add_action( 'add_meta_boxes', function () {
    $box = MetaBox::make( 'book_details', 'Book Details' )
        ->screen( 'book' )
        ->context( 'normal' )
        ->priority( 'high' );

    $box->field( '_isbn',    'ISBN' )->type( 'text' )->description( 'e.g. 978-3-16-148410-0' );
    $box->field( '_pages',   'Pages' )->type( 'number' );
    $box->field( '_summary', 'Summary' )->type( 'textarea' );
    $box->field( '_genre',   'Genre' )->type( 'select' )
        ->options( array( 'fiction' => 'Fiction', 'non-fiction' => 'Non-Fiction' ) );
    $box->field( '_featured', 'Featured' )->type( 'checkbox' );

    $box->register();
} );

// Read saved values anywhere:
$isbn  = get_post_meta( $post->ID, '_isbn', true );
$pages = (int) get_post_meta( $post->ID, '_pages', true );
```

[Full documentation →](docs/metabox.md)

### Shortcodes

```php
use WPFlint\Shortcodes\Shortcode;

Shortcode::make( 'my_cta' )
    ->defaults( array( 'text' => 'Get Started', 'url' => '#', 'style' => 'primary' ) )
    ->render( function ( array $atts, string $content ): string {
        return sprintf(
            '<a href="%s" class="btn btn-%s">%s</a>',
            esc_url( $atts['url'] ),
            esc_attr( $atts['style'] ),
            esc_html( $atts['text'] )
        );
    } )
    ->register();

// With a View template:
Shortcode::make( 'pricing_table' )
    ->defaults( array( 'plan' => 'basic' ) )
    ->render( fn( $atts ) => View::make( 'shortcodes.pricing' )->with( $atts )->render() )
    ->register();
```

[Full documentation →](docs/shortcodes.md)

### Block Registration

```php
use WPFlint\Blocks\Block;

add_action( 'init', function () {
    Block::make( 'my-plugin/hero' )
        ->title( 'Hero Section' )
        ->category( 'design' )
        ->editor_script( 'my-plugin-blocks' )
        ->attributes( array(
            'heading' => array( 'type' => 'string', 'default' => 'Welcome' ),
            'align'   => array( 'type' => 'string', 'default' => 'center' ),
        ) )
        ->render( function ( array $attrs ): string {
            return sprintf(
                '<section class="hero hero--%s"><h1>%s</h1></section>',
                esc_attr( $attrs['align'] ),
                esc_html( $attrs['heading'] )
            );
        } )
        ->register();
} );
```

[Full documentation →](docs/blocks.md)

### Widgets

```php
use WPFlint\Widgets\AbstractWidget;

class TestimonialWidget extends AbstractWidget {
    protected string $widget_title = 'Testimonial';
    protected string $description  = 'Displays a customer quote.';

    protected function output( array $args, array $instance ): void {
        echo $args['before_widget'];
        echo '<blockquote>' . esc_html( $instance['quote'] ?? '' ) . '</blockquote>';
        echo $args['after_widget'];
    }

    protected function fields( array $instance ): void {
        echo '<p><label>Quote:<br>
            <textarea name="' . esc_attr( $this->get_field_name( 'quote' ) ) . '">'
            . esc_textarea( $instance['quote'] ?? '' ) . '</textarea></label></p>';
    }

    protected function sanitize( array $new, array $old ): array {
        return array( 'quote' => sanitize_textarea_field( $new['quote'] ?? '' ) );
    }
}

// Register in service provider boot():
TestimonialWidget::register();
```

[Full documentation →](docs/widgets.md)

---

## Templates & Mail

### Views

```php
use WPFlint\View\View;

// Set base path once (or register ViewServiceProvider):
View::set_base_path( plugin_dir_path( __FILE__ ) . 'resources/views' );

// Render to string:
$html = View::make( 'admin.settings' )
    ->with( array( 'title' => 'Settings', 'options' => $opts ) )
    ->render();

// Output directly:
View::make( 'partials.notice' )->with( 'message', 'Saved!' )->output();

// Per-instance path override:
View::make( 'email.welcome' )->from( '/custom/path/views' )->render();
```

**Template file** (`resources/views/admin/settings.php`):
```php
<div class="wrap">
    <h1><?php echo esc_html( $title ); ?></h1>
    <!-- $options is available as a local variable -->
</div>
```

[Full documentation →](docs/view.md)

### Mail

```php
use WPFlint\Mail\Mail;

// Plain text:
Mail::to( 'user@example.com' )
    ->subject( 'Welcome!' )
    ->message( 'Thanks for signing up.' )
    ->send();

// HTML:
Mail::to( 'user@example.com' )
    ->subject( 'Order Confirmed' )
    ->from( 'shop@example.com', 'My Shop' )
    ->html( '<h1>Order confirmed!</h1>' )
    ->cc( 'admin@example.com' )
    ->send();

// PHP template:
Mail::to( $user->user_email )
    ->subject( 'Order #' . $order->id )
    ->from( get_option( 'admin_email' ), get_bloginfo( 'name' ) )
    ->template( 'emails.order-confirmed', array( 'order' => $order ) )
    ->attach( '/var/www/uploads/invoice.pdf' )
    ->send();
```

[Full documentation →](docs/mail.md)

---

## Plugin Infrastructure

### Plugin Lifecycle

```php
use WPFlint\Lifecycle\Lifecycle;

Lifecycle::for( __FILE__ )
    ->on_activate( function () {
        global $wpdb;
        // Create tables, set defaults...
    } )
    ->on_deactivate( function () {
        wp_clear_scheduled_hook( 'my_plugin_daily' );
    } )
    ->on_uninstall( \MyPlugin\Uninstaller::class )  // must be a named class
    ->register();
```

[Full documentation →](docs/lifecycle.md)

### Asset Manager

```php
use WPFlint\Assets\Script;
use WPFlint\Assets\Style;

// Direct enqueueing:
Script::make( 'my-app', plugin_dir_url( __FILE__ ) . 'app.js' )
    ->deps( array( 'jquery' ) )
    ->version( '1.0' )
    ->footer()
    ->localize( 'MyApp', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) )
    ->only_on( 'is_admin' )
    ->enqueue();

Style::make( 'my-style', plugin_dir_url( __FILE__ ) . 'app.css' )
    ->version( '1.0' )
    ->only_on( fn() => is_page( 'shop' ) )
    ->enqueue();

// Via AssetManager (auto-hooked to wp_enqueue_scripts + admin_enqueue_scripts):
$manager = $app->make( \WPFlint\Assets\AssetManager::class );
$manager->script( 'my-app', '...' )->footer()->version( '1.0' );
$manager->style( 'my-style', '...' )->version( '1.0' );
```

[Full documentation →](docs/assets.md)

### Events

```php
use WPFlint\Events\Dispatcher;
use WPFlint\Facades\Event;

// Define an event:
class OrderPlaced extends \WPFlint\Events\Event {
    public function __construct( public int $order_id, public float $total ) {}
}

// Listen:
Event::listen( OrderPlaced::class, SendConfirmationEmail::class );
Event::listen( OrderPlaced::class, function ( OrderPlaced $e ) {
    error_log( 'Order placed: ' . $e->order_id );
} );

// Fire:
Event::fire( new OrderPlaced( 42, 99.50 ) );

// WordPress hook bridge:
Event::listen_wp( 'save_post', PostSaved::class );
```

[Full documentation →](docs/events.md)

### Cache

```php
use WPFlint\Facades\Cache;

Cache::put( 'key', 'value', 300 );
Cache::get( 'key', 'default' );
Cache::remember( 'all_orders', 3600, fn() => Order::all() );
Cache::forget( 'key' );
Cache::flush();

// Tags:
Cache::tags( 'orders' )->remember( 'order_list', 300, fn() => Order::all() );
Cache::tags( 'orders' )->flush();

// Bypass cache:
Cache::fresh()->remember( 'key', 3600, $callback );
```

[Full documentation →](docs/cache.md)

### Logging

```php
use WPFlint\Logging\Logger;

$logger = new Logger( 'my-plugin', WP_CONTENT_DIR . '/logs/my-plugin.log' );

$logger->info( 'Order placed', array( 'order_id' => 42 ) );
$logger->warning( 'Low stock', array( 'product' => 'Widget', 'qty' => 2 ) );
$logger->error( 'Payment failed', array( 'error' => $e->getMessage() ) );
$logger->debug( 'Cache miss', array( 'key' => 'all_orders' ) );
```

[Full documentation →](docs/logging.md)

### Queue & Jobs

```php
use WPFlint\Queue\QueueManager;

class SendWelcomeEmail extends \WPFlint\Queue\Job {
    public function __construct( private int $user_id ) {}

    public function handle(): void {
        $user = get_userdata( $this->user_id );
        Mail::to( $user->user_email )->subject( 'Welcome!' )->send();
    }
}

$queue = $app->make( QueueManager::class );
$queue->push( new SendWelcomeEmail( $user->ID ) );
$queue->push( new SendWelcomeEmail( $user->ID ), 300 ); // delay 5 min
```

[Full documentation →](docs/queue.md)

### Scheduler

```php
use WPFlint\Scheduling\Scheduler;

$scheduler = $app->make( Scheduler::class );

$scheduler->call( fn() => Cache::flush() )
    ->daily()
    ->at( '02:00' );

$scheduler->call( fn() => $migrator->run() )
    ->weekly()
    ->on( 'monday' );

$scheduler->command( 'my-plugin/sync-products' )
    ->hourly();
```

[Full documentation →](docs/scheduling.md)

---

## WP-CLI Commands

Dev-only commands, excluded from production builds via `.distignore`.

### Database

| Command | Description |
|---------|-------------|
| `wp wpflint migrate` | Run pending migrations |
| `wp wpflint migrate --rollback` | Roll back last batch |
| `wp wpflint migrate --rollback --steps=N` | Roll back N batches |
| `wp wpflint migrate --fresh` | Drop all + re-run (with confirmation) |
| `wp wpflint migrate --status` | Show migration status |
| `wp wpflint cache:clear` | Clear all application cache |
| `wp wpflint cache:clear --tag=orders` | Clear a specific cache tag |

### Code Generators

| Command | Description |
|---------|-------------|
| `wp wpflint make:migration <Name>` | Generate migration stub |
| `wp wpflint make:model <Name>` | Generate model stub |
| `wp wpflint make:model <Name> --migration` | Generate model + migration |
| `wp wpflint make:controller <Name>` | Generate AJAX controller |
| `wp wpflint make:controller <Name> --rest` | Generate REST controller |
| `wp wpflint make:middleware <Name>` | Generate middleware stub |
| `wp wpflint make:request <Name>` | Generate form request stub |
| `wp wpflint make:provider <Name>` | Generate service provider stub |
| `wp wpflint make:event <Name>` | Generate event stub |
| `wp wpflint make:facade <Name>` | Generate facade stub |

All `make:*` commands accept `--path=<dir>` to customise the output directory.

[Full documentation →](docs/console.md)

---

## Testing

WPFlint uses PHPUnit 9 with [WP_Mock](https://github.com/10up/wp_mock) and [Brain\Monkey](https://github.com/Brain-WP/BrainMonkey).

```bash
composer test    # run all tests
composer lint    # check code style
composer lint:fix # auto-fix code style
```

Test structure mirrors `src/`:

```
tests/
  bootstrap.php
  ApplicationTest.php
  Container/ContainerTest.php
  Http/RouterTest.php
  Database/ORM/ModelTest.php
  Cache/CacheManagerTest.php
  Events/DispatcherTest.php
  Admin/NoticeTest.php, MetaBoxTest.php, AdminTest.php
  Assets/AssetTest.php
  Blocks/BlockTest.php
  Lifecycle/LifecycleTest.php
  Mail/MailTest.php
  Settings/SettingsTest.php
  Shortcodes/ShortcodeTest.php
  View/ViewTest.php
  Widgets/WidgetTest.php
  Http/RestAuthTest.php
  ...
```

Writing a test:

```php
use WP_Mock;
use WP_Mock\Tools\TestCase;

class MyFeatureTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_something(): void {
        WP_Mock::userFunction( 'get_option' )->andReturn( 'value' );
        $this->assertSame( 'value', get_option( 'my_key' ) );
    }
}
```

---

## WP.org Compliance

WPFlint is designed for WordPress.org plugin directory submission:

- All database queries use `$wpdb->prepare()`
- All user input sanitized with `sanitize_*()` functions
- All output escaped with `esc_*()` functions
- AJAX handlers verify nonces (`check_ajax_referer()`) and capabilities (`current_user_can()`)
- Metabox save callbacks verify nonces, capability, and skip autosaves
- All translatable strings use `__()` or `_e()` with text domains
- No `eval()`, `exec()`, or `system()` calls
- `.distignore` excludes: `tests/`, `vendor/`, `.claude/`, `src/Console/`, `docs/`, `composer.json`

---

## Directory Structure

```
src/
  Application.php                 # Singleton bootstrap
  Container/Container.php         # PSR-11 IoC container
  Providers/ServiceProvider.php   # Base provider
  Config/Repository.php           # Dot-notation config
  Http/
    Router.php                    # AJAX + REST routing
    Controller.php                # AJAX controller base
    RestController.php            # REST controller base
    Request.php                   # Input + validation
    Response.php                  # Response builder
    Pipeline.php                  # Middleware pipeline
    RestAuth.php                  # REST auth helpers
    Middleware/                   # nonce, can, throttle
  Database/
    Schema/                       # Blueprint, Schema
    Migrations/                   # Migrator, Migration
    ORM/                          # Model, QueryBuilder, Relations
  Cache/
    CacheManager.php              # Multi-driver cache
    TaggedCache.php               # Tag-based invalidation
    Drivers/                      # Transient, ObjectCache, Array
  Events/
    Dispatcher.php                # Typed event system
    Event.php                     # Base event
  Facades/                        # Config, Cache, Event
  Lifecycle/Lifecycle.php         # Activation/deactivation/uninstall
  Admin/
    AdminPage.php                 # Menu + page builder
    Notice.php                    # Flash + persistent notices
    MetaBox.php                   # Metabox builder
    MetaBoxField.php              # Individual field renderer/saver
  Settings/
    Settings.php                  # Settings API builder
    Section.php                   # Settings section
    Field.php                     # Settings field
  Assets/
    AssetManager.php              # Collect + enqueue assets
    Script.php                    # wp_enqueue_script wrapper
    Style.php                     # wp_enqueue_style wrapper
  Shortcodes/Shortcode.php        # add_shortcode wrapper
  View/View.php                   # PHP template renderer
  Mail/Mail.php                   # wp_mail wrapper
  Blocks/Block.php                # register_block_type wrapper
  Widgets/AbstractWidget.php      # WP_Widget abstraction
  Logging/Logger.php              # File-based logger
  Queue/                          # Job queue system
  Scheduling/                     # WP-Cron scheduler
  WordPress/                      # WP core model wrappers
  Console/                        # WP-CLI commands (dev only)
```

---

## License

GPL-2.0-or-later
