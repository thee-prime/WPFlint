#!/usr/bin/env node
/**
 * WPFlint MCP Server — World-Class Edition
 *
 * Exposes the complete WPFlint framework to AI assistants via Model Context Protocol.
 *
 * Resources (read-only knowledge):
 *   wpflint://overview            — full framework guide + architecture
 *   wpflint://patterns            — key coding patterns & conventions
 *   wpflint://api/{module}        — per-module API reference
 *
 * Code-generation tools (40+):
 *   Core scaffolding: wpflint_scaffold_plugin, wpflint_make_provider, wpflint_make_migration,
 *                     wpflint_make_model, wpflint_make_controller, wpflint_make_middleware,
 *                     wpflint_make_request, wpflint_make_event, wpflint_make_listener,
 *                     wpflint_make_facade, wpflint_make_rule, wpflint_make_command,
 *                     wpflint_make_job
 *   HTTP / REST:      wpflint_rest_routes, wpflint_rest_auth, wpflint_router_ajax
 *   Admin UI:         wpflint_make_admin_page, wpflint_make_settings, wpflint_make_metabox,
 *                     wpflint_make_notice
 *   Content:          wpflint_make_post_type, wpflint_make_taxonomy, wpflint_make_meta_field,
 *                     wpflint_make_shortcode, wpflint_make_block, wpflint_make_widget
 *   Frontend:         wpflint_make_asset, wpflint_make_view
 *   System:           wpflint_make_lifecycle, wpflint_logging_usage, wpflint_schedule_usage
 *   Discovery:        wpflint_framework_overview, wpflint_module_docs
 */

import { McpServer, ResourceTemplate } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

// ===========================================================================
// KNOWLEDGE BASE — embedded framework documentation for AI consumption
// ===========================================================================

const KNOWLEDGE = {

  overview: `# WPFlint Framework — Complete Overview

WPFlint is a Laravel-inspired framework for WordPress plugins.
PHP 7.4+. Zero production dependencies. Ships as a standalone plugin or Composer package.

## Philosophy
- Container-driven: no globals, everything resolved via IoC container
- Service providers register + boot every feature
- Fluent builders for WordPress APIs (post types, metaboxes, blocks, widgets)
- Test-first: PHPUnit 9 + WP_Mock for every public method

## Architecture

\`\`\`
src/
  Application.php          # Singleton bootstrap, manages providers
  Container/Container.php  # IoC, PSR-11 compatible
  Providers/               # ServiceProvider base + core built-in providers
  Http/                    # Router, Controller, RestController, Request, Response, Middleware
  Database/Migrations/     # Migration runner + Blueprint + base Migration class
  Database/ORM/            # Model, QueryBuilder, relationships (hasMany/belongsTo/etc)
  Cache/                   # CacheManager, drivers (wp_transient, object cache, array)
  Config/Repository.php    # Dot-notation config reader
  Events/                  # Dispatcher, Event base, Listener support
  Facades/                 # Static proxies (Cache, Event, Log, etc)
  Support/                 # Helpers, traits, utilities
  Console/                 # WP-CLI commands (dev-only, excluded from .distignore)
  Logging/                 # PSR-3 Logger, channels, levels
  Queue/                   # Job base, async processing via cron
  Scheduling/              # Fluent scheduler, WP Cron integration
  Admin/                   # AdminPage, AdminMenu, Settings, MetaBox, Notices
  Assets/                  # Script and Style fluent builders
  Blocks/                  # Gutenberg Block registration builder
  Widgets/                 # AbstractWidget reducing WP_Widget boilerplate
  View/                    # Template renderer (PHP files, data passing)
  Mail/                    # Wp_mail wrapper with fluent API
  Shortcodes/              # Shortcode registration builder
  Lifecycle/               # Activation/Deactivation/Uninstall hooks
\`\`\`

## Bootstrap

\`\`\`php
// In your main plugin file
use WPFlint\\Application;

$app = Application::get_instance();
$app->register( OrderServiceProvider::class );
$app->boot();
\`\`\`

## Container

\`\`\`php
// Bind interface → concrete
$app->bind( OrderRepositoryInterface::class, EloquentOrderRepository::class );

// Singleton (resolved once)
$app->singleton( 'cache', fn( $app ) => new CacheManager( $app ) );

// Resolve (auto-resolves constructor deps)
$repo = $app->make( OrderRepositoryInterface::class );

// Has binding?
$app->has( 'cache' ); // bool
\`\`\`

## Service Providers

Every feature lives in a provider. Register in your plugin bootstrap.

\`\`\`php
class OrderServiceProvider extends ServiceProvider {

    public bool $defer = true; // lazy-load until first use

    public function register(): void {
        $this->app->singleton( OrderService::class, fn( $app ) =>
            new OrderService( $app->make( OrderRepository::class ) )
        );
    }

    public function boot(): void {
        add_action( 'init', function () {
            // register post types, hooks, etc.
        } );
    }

    public function provides(): array {
        return [ OrderService::class ];
    }
}
\`\`\`

## PHP 7.4 Rules (NEVER break)
- No named arguments (PHP 8+)
- No enums (PHP 8.1+)
- No match() expression (PHP 8+)
- No union types (PHP 8+)
- No readonly properties (PHP 8.1+)
- No str_contains / str_starts_with (PHP 8+) — use strpos() !== false
- No nullsafe operator ?-> in loops
- No fibers
- YES: typed properties, arrow functions fn() =>
- callable CANNOT be used as a property type hint — use @var docblock

## WordPress Compliance (always required)
- $wpdb->prepare() on EVERY query, no exceptions
- All tables: $wpdb->prefix . 'tablename'
- All options/transients: prefixed with plugin slug
- Strings: __() or _e() always
- Nonces: every form + AJAX
- Cap checks: before every privileged operation
- sanitize_*() on all input, esc_*() on all output
`,

  patterns: `# WPFlint Key Patterns & Conventions

## Router — AJAX

\`\`\`php
// In ServiceProvider boot():
Router::ajax( 'my-plugin/save-order', [ OrderController::class, 'store' ] )
      ->middleware( [ 'nonce:save_order', 'can:edit_posts' ] );

Router::ajax( 'my-plugin/get-orders', [ OrderController::class, 'index' ] )
      ->nopriv()
      ->middleware( [ 'throttle:60,1' ] );
\`\`\`

## Router — REST API

\`\`\`php
Router::rest( 'my-plugin/v1', function ( RestRouter $r ) {
    $r->get(    '/orders',                   [ OrderRestController::class, 'index' ] );
    $r->post(   '/orders',                   [ OrderRestController::class, 'store' ] );
    $r->get(    '/orders/(?P<id>\\d+)',       [ OrderRestController::class, 'show' ] );
    $r->put(    '/orders/(?P<id>\\d+)',       [ OrderRestController::class, 'update' ] );
    $r->delete( '/orders/(?P<id>\\d+)',       [ OrderRestController::class, 'destroy' ] );
} );
\`\`\`

## Controller — auto-resolved dependencies

\`\`\`php
class OrderController extends Controller {
    public function __construct(
        private OrderService $orders,   // auto-resolved from container
        private CacheManager $cache
    ) {}

    public function store( StoreOrderRequest $request ): Response {
        // $request already validated + sanitized before method runs
        $order = $this->orders->create( $request->validated() );
        return Response::json( $order->toArray(), 201 );
    }
}
\`\`\`

## Request — validation + authorization

\`\`\`php
class StoreOrderRequest extends Request {
    public function authorize(): bool {
        return current_user_can( 'edit_posts' );
    }
    public function rules(): array {
        return [
            'status' => 'required|in:pending,paid,cancelled',
            'total'  => 'required|numeric|min:0',
        ];
    }
    public function sanitize(): array {
        return [
            'status' => 'sanitize_text_field',
            'total'  => 'floatval',
        ];
    }
}
\`\`\`

## Response

\`\`\`php
Response::json( [ 'order' => $order ], 200 );   // wp_send_json_success
Response::error( 'Not found', 404 );            // wp_send_json_error
Response::noContent();                           // 204
\`\`\`

## Cache

\`\`\`php
Cache::tags( [ 'orders' ] )->remember( 'key', 3600, fn() => Order::all() );
Cache::tags( 'orders' )->flush();
Cache::fresh()->remember( 'key', 3600, $callback ); // bypass all tiers
\`\`\`

## Events

\`\`\`php
Event::listen( OrderPlaced::class, SendConfirmation::class );
Event::fire( new OrderPlaced( $order ) );
\`\`\`

## Model

\`\`\`php
class Order extends Model {
    protected static string $table    = 'orders';
    protected array         $fillable = [ 'status', 'total' ];
    protected array         $casts    = [ 'total' => 'float', 'meta' => 'array' ];

    public function scopePending( QueryBuilder $q ): QueryBuilder {
        return $q->where( 'status', 'pending' );
    }
}

// Usage
Order::pending()->where( 'total', '>', 100 )->get();
Order::find( $id );
Order::create( [ 'status' => 'pending', 'total' => 99.99 ] );
\`\`\`

## Migration

\`\`\`php
class CreateOrdersTable extends Migration {
    public function up(): void {
        $this->schema()->create( 'orders', function ( Blueprint $t ) {
            $t->bigIncrements( 'id' );
            $t->string( 'status' )->default( 'pending' );
            $t->decimal( 'total', 10, 2 )->default( '0.00' );
            $t->timestamps();
        } );
    }
    public function down(): void {
        $this->schema()->drop( 'orders' );
    }
}
\`\`\`

## PostType + Taxonomy + MetaField (fluent)

\`\`\`php
PostType::make( 'book' )
    ->label( 'Book', 'Books' )
    ->public()
    ->supports( [ 'title', 'editor', 'thumbnail' ] )
    ->show_in_rest()
    ->register();

Taxonomy::make( 'genre' )
    ->label( 'Genre', 'Genres' )
    ->for( [ 'book' ] )
    ->hierarchical()
    ->show_in_rest()
    ->register();

MetaField::post( 'book', '_isbn' )
    ->type( 'string' )
    ->single()
    ->show_in_rest()
    ->register();
\`\`\`
`,

  modules: {
    container: `# Container & Application

## Binding
\`\`\`php
$app->bind( Interface::class, Concrete::class );
$app->singleton( 'key', fn( $a ) => new Service() );
$app->instance( 'version', '1.0.0' );
\`\`\`

## Resolving
\`\`\`php
$svc = $app->make( Interface::class );
$app->call( [ $controller, 'method' ], [ 'extra' => $val ] );
$app->has( 'key' ); // bool
\`\`\`

## Service Providers
Register and boot features. Use \`$defer = true\` for lazy loading.
`,

    http: `# HTTP Layer — Router, Controller, Request, Response, Middleware

## Router
\`\`\`php
// AJAX
Router::ajax( 'action', [ Controller::class, 'method' ] )
      ->middleware( [ 'nonce:action_name', 'can:capability' ] )
      ->nopriv(); // allow unauthenticated

// REST
Router::rest( 'plugin/v1', function ( RestRouter $r ) {
    $r->get( '/path', [ Controller::class, 'index' ] );
    $r->post( '/path', [ Controller::class, 'store' ] );
} );
\`\`\`

## Controller (AJAX)
Constructor params auto-resolved from container.
Type-hint a Request subclass on the method and it's validated before the method runs.

## RestController (REST)
Extend for REST endpoints. Override \`get_items_permissions_check()\`, etc.
Use \`$this->respond( $data, $status )\` to return WP_REST_Response.

## Request
- \`authorize(): bool\` — checked before validation
- \`rules(): array\` — laravel-style validation rules
- \`sanitize(): array\` — field => sanitize function

## Response
\`\`\`php
Response::json( $data, 200 );
Response::error( 'msg', 404 );
Response::noContent();
\`\`\`

## Built-in Middleware
- \`nonce:{action}\` — verifies WordPress nonce
- \`can:{capability}\` — checks current_user_can()
- \`throttle:{max},{minutes}\` — rate limiting
`,

    database: `# Database — Migrations & ORM

## Blueprint column helpers
id(), bigIncrements(), string(), text(), longText(), integer(), bigInteger(),
float(), decimal(), boolean(), datetime(), date(), time(), timestamp(), timestamps(),
unsignedBigInteger(), nullableString(), json()

## QueryBuilder
\`\`\`php
Model::where( 'col', 'val' )->get();
Model::where( 'col', '>', 100 )->first();
Model::find( $id );
Model::create( $data );
$model->update( $data );
$model->delete();
Model::count();
Model::whereIn( 'id', [1,2,3] )->get();
Model::orderBy( 'created_at', 'desc' )->limit( 10 )->get();
\`\`\`

## Relationships
\`\`\`php
// in Model:
public function items() { return $this->hasMany( OrderItem::class, 'order_id' ); }
public function user()  { return $this->belongsTo( User::class, 'user_id' ); }
// usage:
$order->items()->get();
\`\`\`

## Casts
\`\`\`php
protected array $casts = [
    'total'    => 'float',
    'meta'     => 'array',
    'is_paid'  => 'bool',
    'paid_at'  => 'datetime',
];
\`\`\`
`,

    cache: `# Cache

## Drivers
- wp_transient (default), object cache, array (in-memory per request)

## Usage
\`\`\`php
use WPFlint\\Facades\\Cache;

Cache::remember( 'key', 3600, fn() => expensive() );
Cache::put( 'key', $val, 3600 );
Cache::get( 'key', $default );
Cache::forget( 'key' );
Cache::flush();

// Tags (group invalidation)
Cache::tags( [ 'orders', 'user:1' ] )->remember( 'key', 3600, $cb );
Cache::tags( 'orders' )->flush();

// Skip cache
Cache::fresh()->remember( 'key', 3600, $cb );
\`\`\`
`,

    events: `# Events & Listeners

## Event class
\`\`\`php
class OrderPlaced extends Event {
    public function __construct( public Order $order ) {}
}
\`\`\`

## Listener class
\`\`\`php
class SendConfirmation {
    public function handle( OrderPlaced $event ): void {
        // send email
    }
}
\`\`\`

## Registration (in ServiceProvider boot)
\`\`\`php
Event::listen( OrderPlaced::class, SendConfirmation::class );
// or closure:
Event::listen( OrderPlaced::class, function ( OrderPlaced $e ) { ... } );
\`\`\`

## Firing
\`\`\`php
Event::fire( new OrderPlaced( $order ) );
\`\`\`
`,

    admin: `# Admin — Pages, Settings, MetaBox, Notices

## AdminPage (fluent)
\`\`\`php
use WPFlint\\Admin\\AdminPage;

AdminPage::make( 'my-plugin', 'My Plugin Settings' )
    ->parent( 'options-general.php' ) // submenu
    ->capability( 'manage_options' )
    ->icon( 'dashicons-admin-tools' )
    ->callback( function () {
        echo '<div class="wrap"><h1>Settings</h1></div>';
    } )
    ->register();
\`\`\`

## Settings API (fluent)
\`\`\`php
use WPFlint\\Settings\\Settings;

Settings::make( 'my_plugin_options', 'My Plugin' )
    ->section( 'general', 'General', 'options-general.php' )
    ->field( 'api_key', 'API Key', 'general' )
        ->type( 'text' )
        ->description( 'Your API key.' )
    ->field( 'enable_logs', 'Enable Logging', 'general' )
        ->type( 'checkbox' )
    ->register();
\`\`\`

## MetaBox (fluent)
\`\`\`php
use WPFlint\\Admin\\MetaBox;

$box = MetaBox::make( 'product_details', 'Product Details' )
    ->screen( 'product' )
    ->context( 'normal' )
    ->priority( 'high' );

$box->field( '_sku',   'SKU' )->type( 'text' );
$box->field( '_price', 'Price' )->type( 'number' )->sanitize_with( 'floatval' );
$box->field( '_notes', 'Notes' )->type( 'textarea' );

$box->register();
\`\`\`

## Notices (fluent)
\`\`\`php
use WPFlint\\Admin\\Notice;

Notice::success( 'Settings saved!' )->dismissible()->show();
Notice::error( 'Something went wrong.' )->show();
Notice::warning( 'Review your settings.' )->show();
Notice::info( 'Plugin updated to v2.' )->dismissible()->show();
\`\`\`
`,

    blocks: `# Gutenberg Blocks

## Basic registration
\`\`\`php
use WPFlint\\Blocks\\Block;

add_action( 'init', function () {
    Block::make( 'my-plugin/hero' )
        ->title( 'Hero Section' )
        ->category( 'design' )
        ->icon( 'dashicons-cover-image' )
        ->editor_script( 'my-plugin-blocks' )
        ->style( 'my-plugin-blocks-style' )
        ->attributes( [
            'heading' => [ 'type' => 'string', 'default' => 'Welcome' ],
            'align'   => [ 'type' => 'string', 'default' => 'center' ],
        ] )
        ->render( function ( array $attrs, string $content ): string {
            return sprintf(
                '<div class="hero hero--%s"><h1>%s</h1>%s</div>',
                esc_attr( $attrs['align'] ),
                esc_html( $attrs['heading'] ),
                wp_kses_post( $content )
            );
        } )
        ->register();
} );
\`\`\`

## Static vs Dynamic
- Static: no render callback; JS handles editor + frontend
- Dynamic: provide render() callback for PHP server-side rendering
`,

    widgets: `# Widgets

## Creating
\`\`\`php
use WPFlint\\Widgets\\AbstractWidget;

class RecentPostsWidget extends AbstractWidget {
    protected string $widget_title = 'Recent Posts';
    protected string $description  = 'Configurable recent posts list.';

    protected function output( array $args, array $instance ): void {
        $count = (int) ( $instance['count'] ?? 5 );
        echo $args['before_widget'];
        $posts = get_posts( [ 'numberposts' => $count, 'post_status' => 'publish' ] );
        echo '<ul>';
        foreach ( $posts as $post ) {
            echo '<li><a href="' . esc_url( get_permalink( $post ) ) . '">'
                . esc_html( $post->post_title ) . '</a></li>';
        }
        echo '</ul>';
        echo $args['after_widget'];
    }

    protected function fields( array $instance ): void {
        $count = $instance['count'] ?? 5;
        echo '<p><label>Count: <input class="widefat" type="number"
            name="' . esc_attr( $this->get_field_name( 'count' ) ) . '"
            value="' . esc_attr( $count ) . '"></label></p>';
    }

    protected function sanitize( array $new, array $old ): array {
        return [ 'count' => max( 1, (int) $new['count'] ) ];
    }
}
\`\`\`

## Registering
\`\`\`php
// In ServiceProvider boot():
RecentPostsWidget::register();

// Or directly:
add_action( 'widgets_init', fn() => RecentPostsWidget::register() );
\`\`\`
`,

    assets: `# Assets — Scripts & Styles

## Scripts
\`\`\`php
use WPFlint\\Assets\\Script;

Script::make( 'my-plugin-app', plugin_dir_url( __FILE__ ) . 'dist/app.js' )
    ->version( '1.0.0' )
    ->deps( [ 'jquery', 'wp-element' ] )
    ->in_footer()
    ->localize( 'MyPluginData', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'my_action' ),
    ] )
    ->enqueue();

// Admin only:
Script::make( 'my-plugin-admin', plugin_dir_url( __FILE__ ) . 'dist/admin.js' )
    ->admin_only()
    ->enqueue();
\`\`\`

## Styles
\`\`\`php
use WPFlint\\Assets\\Style;

Style::make( 'my-plugin-app', plugin_dir_url( __FILE__ ) . 'dist/app.css' )
    ->version( '1.0.0' )
    ->deps( [ 'dashicons' ] )
    ->enqueue();
\`\`\`
`,

    lifecycle: `# Lifecycle Hooks

## Activation
\`\`\`php
use WPFlint\\Lifecycle\\Lifecycle;

Lifecycle::activation( __FILE__, function () {
    // Run migrations, create options, flush rewrite rules
    $migrator = $app->make( Migrator::class );
    $migrator->run();
    flush_rewrite_rules();
} );
\`\`\`

## Deactivation
\`\`\`php
Lifecycle::deactivation( __FILE__, function () {
    // Clear scheduled events
    $app->make( 'scheduler' )->unschedule_all();
    flush_rewrite_rules();
} );
\`\`\`

## Uninstall
\`\`\`php
// In uninstall.php:
Lifecycle::uninstall( function () {
    // Drop tables, delete options, remove capabilities
    $migrator->rollback_all();
    delete_option( 'my_plugin_settings' );
} );
\`\`\`
`,

    view: `# View — Template Rendering

## Render a view
\`\`\`php
use WPFlint\\View\\View;

// Set views directory (in provider register()):
View::set_path( plugin_dir_path( __FILE__ ) . 'templates' );

// Render (outputs directly)
View::render( 'orders/index', [
    'orders' => $orders,
    'title'  => __( 'Orders', 'my-plugin' ),
] );

// Get HTML string
$html = View::make( 'emails/confirmation', [ 'order' => $order ] );
\`\`\`

## Template file (templates/orders/index.php)
\`\`\`php
<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
    <h1><?php echo esc_html( $title ); ?></h1>
    <?php foreach ( $orders as $order ) : ?>
        <p><?php echo esc_html( $order->id ); ?></p>
    <?php endforeach; ?>
</div>
\`\`\`
`,

    mail: `# Mail

## Sending mail
\`\`\`php
use WPFlint\\Mail\\Mail;

Mail::to( 'user@example.com' )
    ->subject( 'Order Confirmed' )
    ->body( '<h1>Thank you for your order!</h1>' )
    ->from( 'shop@example.com', 'My Shop' )
    ->cc( 'admin@example.com' )
    ->header( 'Content-Type', 'text/html; charset=UTF-8' )
    ->send();

// Template-based email
Mail::to( $user->email )
    ->subject( 'Welcome!' )
    ->view( 'emails/welcome', [ 'user' => $user ] )
    ->send();
\`\`\`
`,

    shortcodes: `# Shortcodes

## Registering
\`\`\`php
use WPFlint\\Shortcodes\\Shortcode;

Shortcode::make( 'my_orders' )
    ->callback( function ( array $atts, string $content = '' ): string {
        $atts = shortcode_atts( [ 'limit' => 5 ], $atts, 'my_orders' );
        $orders = Order::limit( (int) $atts['limit'] )->get();
        $html   = '<ul class="my-orders">';
        foreach ( $orders as $order ) {
            $html .= '<li>' . esc_html( $order->id ) . '</li>';
        }
        return $html . '</ul>';
    } )
    ->register();
\`\`\`

## Usage in content
\`\`\`
[my_orders limit="10"]
\`\`\`
`,

    rest_auth: `# REST API Auth

## Permission callback factories
\`\`\`php
use WPFlint\\Http\\RestAuth;

register_rest_route( 'plugin/v1', '/settings', [
    'methods'             => 'GET',
    'callback'            => [ $ctrl, 'index' ],
    'permission_callback' => RestAuth::capability( 'manage_options' ),
] );

// Built-in factories:
RestAuth::logged_in()                   // any authenticated user
RestAuth::public_access()               // always true
RestAuth::capability( 'manage_options' )
RestAuth::all_of( 'edit_posts', 'upload_files' )
RestAuth::any_of( 'edit_posts', 'edit_pages' )

// Direct boolean checks (inside controllers):
RestAuth::require_logged_in()           // bool
RestAuth::require_capability( 'cap' )   // bool

// Namespace builder:
RestAuth::namespace( 'my-plugin' )      // 'my-plugin/v1'
RestAuth::namespace( 'my-plugin', 2 )   // 'my-plugin/v2'
\`\`\`
`,

    logging: `# Logging

## Setup
\`\`\`php
define( 'WPFLINT_LOG_CHANNEL', 'my-plugin' );
define( 'WPFLINT_LOG_LEVEL',   'debug' );

$app->register( WPFlint\\Logging\\LoggingServiceProvider::class );
\`\`\`

## Usage
\`\`\`php
$logger = $app->make( WPFlint\\Logging\\LoggerInterface::class );
$logger->info( 'Plugin booted.' );
$logger->warning( 'Slow query {ms}ms.', [ 'ms' => 850 ] );
$logger->error( 'Payment failed', [ 'exception' => $e ] );

// Helpers
wpflint_log( 'Order {id} placed.', [ 'id' => $orderId ] );
wpflint_dd( $var1, $var2 ); // dev only
\`\`\`
`,

    queue: `# Queue & Jobs

## Job class
\`\`\`php
class SendWelcomeEmail extends Job {
    protected string $queue        = 'emails';
    protected int    $max_attempts = 3;

    public function __construct( private User $user ) {}

    public function handle(): void {
        Mail::to( $this->user->email )->subject( 'Welcome!' )->send();
    }

    public function failed( \\Throwable $e ): void {
        Log::error( 'Welcome email failed', [ 'user' => $this->user->id ] );
    }
}
\`\`\`

## Dispatching
\`\`\`php
$app->make( 'queue' )->push( new SendWelcomeEmail( $user ) );
\`\`\`
`,

    scheduling: `# Scheduling

## Setup
\`\`\`php
$app->register( WPFlint\\Scheduling\\SchedulerServiceProvider::class );
\`\`\`

## Defining schedules (in ServiceProvider boot())
\`\`\`php
$scheduler = $this->app->make( 'scheduler' );

$scheduler->call( fn() => clean_expired_transients() )
          ->name( 'my_plugin_cleanup' )
          ->daily();

$scheduler->job( GenerateReportJob::class )
          ->name( 'my_plugin_report' )
          ->weekly();

$scheduler->call( fn() => sync_inventory() )
          ->name( 'my_plugin_inventory_sync' )
          ->every_thirty_minutes();
\`\`\`

## Available intervals
every_minute, every_five_minutes, every_ten_minutes, every_fifteen_minutes,
every_thirty_minutes, hourly, every_hours(N), twice_daily, daily, weekly, monthly
`,
  },
};

// ===========================================================================
// STUB GENERATORS
// ===========================================================================

function snakeCase(value) {
  return value
    .replace(/([a-z])([A-Z])/g, "$1_$2")
    .replace(/([A-Z]+)([A-Z][a-z])/g, "$1_$2")
    .toLowerCase();
}

function guessTableName(name) {
  return snakeCase(name.replace(/^Create/, "").replace(/Table$/, ""));
}

function migrationTimestamp() {
  const now = new Date();
  const pad = (n, w = 2) => String(n).padStart(w, "0");
  return [
    now.getUTCFullYear(),
    pad(now.getUTCMonth() + 1),
    pad(now.getUTCDate()),
    pad(now.getUTCHours()) + pad(now.getUTCMinutes()) + pad(now.getUTCSeconds()),
  ].join("_");
}

function migrationStub(name) {
  const table = guessTableName(name);
  return `<?php

declare(strict_types=1);

use WPFlint\\Database\\Migrations\\Migration;
use WPFlint\\Database\\Schema\\Blueprint;

class ${name} extends Migration {

\tpublic function up(): void {
\t\t$this->schema()->create( '${table}', function ( Blueprint $table ) {
\t\t\t$table->bigIncrements( 'id' );
\t\t\t$table->timestamps();
\t\t} );
\t}

\tpublic function down(): void {
\t\t$this->schema()->drop( '${table}' );
\t}
}
`;
}

function modelStub(name) {
  const table = snakeCase(name) + "s";
  return `<?php

declare(strict_types=1);

use WPFlint\\Database\\ORM\\Model;

class ${name} extends Model {

\tprotected static string $table = '${table}';

\tprotected array $fillable = array();

\tprotected array $casts = array();
}
`;
}

function providerStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Providers\\ServiceProvider;

class ${name} extends ServiceProvider {

\tpublic function register(): void {
\t\t//
\t}

\tpublic function boot(): void {
\t\t//
\t}
}
`;
}

function controllerStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Http\\Controller;

class ${name} extends Controller {

\tpublic function __construct() {
\t\t//
\t}
}
`;
}

function restControllerStub(name) {
  const base = snakeCase(name.replace(/Controller$/, ""));
  return `<?php

declare(strict_types=1);

use WPFlint\\Http\\RestController;

class ${name} extends RestController {

\tprotected string $namespace = 'my-plugin/v1';

\tprotected string $rest_base = '${base}';

\tpublic function index( \\WP_REST_Request $request ): \\WP_REST_Response {
\t\treturn $this->respond( array() );
\t}

\tpublic function store( \\WP_REST_Request $request ): \\WP_REST_Response {
\t\treturn $this->respond( array(), 201 );
\t}

\tpublic function get_items_permissions_check( $request ): bool {
\t\treturn current_user_can( 'read' );
\t}

\tpublic function create_item_permissions_check( $request ): bool {
\t\treturn current_user_can( 'edit_posts' );
\t}
}
`;
}

function middlewareStub(name) {
  return `<?php

declare(strict_types=1);

use Closure;
use WPFlint\\Http\\Request;
use WPFlint\\Http\\Middleware\\MiddlewareInterface;

class ${name} implements MiddlewareInterface {

\tpublic function handle( Request $request, Closure $next ) {
\t\treturn $next( $request );
\t}
}
`;
}

function requestStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Http\\Request;

class ${name} extends Request {

\tpublic function authorize(): bool {
\t\treturn false;
\t}

\tpublic function rules(): array {
\t\treturn array();
\t}

\tpublic function sanitize(): array {
\t\treturn array();
\t}
}
`;
}

function eventStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Events\\Event;

class ${name} extends Event {

\tpublic function __construct() {
\t\t//
\t}
}
`;
}

function facadeStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Facades\\Facade;

class ${name} extends Facade {

\tprotected static function get_facade_accessor(): string {
\t\treturn '';
\t}
}
`;
}

function listenerStub(name, event) {
  const typeHint = event ? `${event} $event` : "$event";
  const useEvent = event ? `\nuse ${event};\n` : "";
  return `<?php

declare(strict_types=1);
${useEvent}
class ${name} {

\t/**
\t * Handle the event.
\t *
\t * @param ${typeHint}
\t * @return void
\t */
\tpublic function handle( ${typeHint} ): void {
\t\t//
\t}
}
`;
}

function commandStub(name) {
  const commandName = snakeCase(name.replace(/Command$/, ""));
  return `<?php

declare(strict_types=1);

use WPFlint\\Console\\Command;

/**
 * ## EXAMPLES
 *
 *     wp wpflint ${commandName}
 */
class ${name} extends Command {

\t/**
\t * Execute the command.
\t *
\t * @param array $args       Positional arguments.
\t * @param array $assoc_args Associative arguments.
\t * @return void
\t */
\tpublic function __invoke( array $args, array $assoc_args ): void {
\t\t$this->info( __( 'Running ${commandName}...', 'text-domain' ) );

\t\t// TODO: implement command logic.

\t\t$this->success( __( 'Done.', 'text-domain' ) );
\t}
}
`;
}

function ruleStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Validation\\Rules\\RuleInterface;

class ${name} implements RuleInterface {

\tpublic function passes( $value ): bool {
\t\t// TODO: implement rule logic.
\t\treturn true;
\t}

\tpublic function message(): string {
\t\treturn __( 'The :attribute field is invalid.', 'text-domain' );
\t}
}
`;
}

function jobStub(name, queue, tries) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Queue\\Job;

class ${name} extends Job {

\tprotected string $queue        = '${queue}';
\tprotected int    $max_attempts = ${tries};

\tpublic function handle(): void {
\t\t// TODO: implement job logic.
\t}

\tpublic function failed( \\Throwable $exception ): void {
\t\t// TODO: alert / compensate on permanent failure.
\t}
}
`;
}

function adminPageStub(slug, title, parent, capability) {
  const cbSlug = snakeCase(slug) + "_render_page";
  const parentLine = parent ? `->parent( '${parent}' )` : "// ->parent( 'options-general.php' ) // uncomment for submenu";
  return `<?php

declare(strict_types=1);

use WPFlint\\Admin\\AdminPage;

/**
 * Register admin page: ${title}
 * Call from a ServiceProvider boot() or add_action( 'admin_menu', ... ).
 */
function ${cbSlug}(): void {
\t?>
\t<div class="wrap">
\t\t<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
\t\t<p><?php esc_html_e( 'Page content goes here.', 'text-domain' ); ?></p>
\t</div>
\t<?php
}

AdminPage::make( '${slug}', '${title}' )
\t${parentLine}
\t->capability( '${capability}' )
\t->callback( '${cbSlug}' )
\t->register();
`;
}

function settingsStub(optionGroup, pageTitle, sections, fields) {
  let sectionCode = "";
  let fieldCode = "";
  sections.forEach((sec) => {
    sectionCode += `\n    ->section( '${sec}', '${sec.charAt(0).toUpperCase() + sec.slice(1)}', 'options-general.php' )`;
  });
  fields.forEach((f) => {
    const sec = sections[0] || "general";
    fieldCode += `\n    ->field( '${f}', '${f.charAt(0).toUpperCase() + f.slice(1)}', '${sec}' )
        ->type( 'text' )
        ->description( '' )`;
  });
  return `<?php

declare(strict_types=1);

use WPFlint\\Settings\\Settings;

/**
 * Register plugin settings.
 * Call from a ServiceProvider boot() on the admin_init hook.
 */
add_action( 'admin_init', function () {
\tSettings::make( '${optionGroup}', '${pageTitle}' )${sectionCode}${fieldCode}
\t    ->register();
} );

// Reading saved values:
// $options = get_option( '${optionGroup}', array() );
// $api_key = $options['api_key'] ?? '';
`;
}

function metaboxStub(id, title, screen, fields) {
  let fieldLines = "";
  fields.forEach((f) => {
    fieldLines += `\n\t$box->field( '_${snakeCase(f)}', '${f}' )->type( 'text' );`;
  });
  return `<?php

declare(strict_types=1);

use WPFlint\\Admin\\MetaBox;

add_action( 'add_meta_boxes', function () {
\t$box = MetaBox::make( '${id}', '${title}' )
\t\t->screen( '${screen}' )
\t\t->context( 'normal' )
\t\t->priority( 'high' );
${fieldLines}
\t$box->register();
} );

// Read saved values:
// $value = get_post_meta( $post->ID, '_field_name', true );
`;
}

function shortcodeStub(tag, atts) {
  const defaults = atts.map((a) => `'${a}' => ''`).join(", ");
  const attsComment = atts.map((a) => `$atts['${a}']`).join(", ");
  return `<?php

declare(strict_types=1);

use WPFlint\\Shortcodes\\Shortcode;

Shortcode::make( '${tag}' )
\t->callback( function ( array $atts, string $content = '' ): string {
\t\t$atts = shortcode_atts(
\t\t\tarray( ${defaults} ),
\t\t\t$atts,
\t\t\t'${tag}'
\t\t);

\t\t// ${attsComment}
\t\t$html = '<div class="${tag.replace(/_/g, "-")}">';
\t\t$html .= wp_kses_post( $content );
\t\t$html .= '</div>';

\t\treturn $html;
\t} )
\t->register();

// Usage in content: [${tag}${atts.map((a) => ` ${a}=""`).join("")}]Content[/${tag}]
`;
}

function blockStub(name, title, category, dynamic) {
  const renderSection = dynamic
    ? `\t->render( function ( array $attrs, string $content ): string {
\t\t$heading = isset( $attrs['heading'] ) ? esc_html( $attrs['heading'] ) : '';
\t\treturn sprintf(
\t\t\t'<div class="${name.split("/")[1]}"><h2>%s</h2>%s</div>',
\t\t\t$heading,
\t\t\twp_kses_post( $content )
\t\t);
\t} )`
    : `\t// Static block: JS handles rendering. Remove ->render() for static blocks.`;

  return `<?php

declare(strict_types=1);

use WPFlint\\Blocks\\Block;

add_action( 'init', function () {
\tBlock::make( '${name}' )
\t\t->title( '${title}' )
\t\t->category( '${category}' )
\t\t->editor_script( '${name.split("/")[0]}-blocks' )
\t\t->attributes( array(
\t\t\t'heading' => array( 'type' => 'string', 'default' => '' ),
\t\t\t'align'   => array( 'type' => 'string', 'default' => 'left' ),
\t\t) )
${renderSection}
\t\t->register();
} );
`;
}

function widgetStub(name, title, description) {
  const fields = [
    { key: "title", label: "Title" },
    { key: "count", label: "Count", type: "number" },
  ];

  const outputFields = fields
    .map((f) =>
      f.type === "number"
        ? `\t\t$${f.key} = (int) ( $instance['${f.key}'] ?? 5 );`
        : `\t\t$${f.key} = $instance['${f.key}'] ?? '';`
    )
    .join("\n");

  const formFields = fields
    .map((f) =>
      f.type === "number"
        ? `\t\t<p>
\t\t\t<label for="<?php echo esc_attr( $this->get_field_id( '${f.key}' ) ); ?>">
\t\t\t\t<?php esc_html_e( '${f.label}', 'text-domain' ); ?>
\t\t\t</label>
\t\t\t<input class="widefat" type="number" min="1"
\t\t\t\tid="<?php echo esc_attr( $this->get_field_id( '${f.key}' ) ); ?>"
\t\t\t\tname="<?php echo esc_attr( $this->get_field_name( '${f.key}' ) ); ?>"
\t\t\t\tvalue="<?php echo esc_attr( $${f.key} ); ?>">
\t\t</p>`
        : `\t\t<p>
\t\t\t<label for="<?php echo esc_attr( $this->get_field_id( '${f.key}' ) ); ?>">
\t\t\t\t<?php esc_html_e( '${f.label}', 'text-domain' ); ?>
\t\t\t</label>
\t\t\t<input class="widefat" type="text"
\t\t\t\tid="<?php echo esc_attr( $this->get_field_id( '${f.key}' ) ); ?>"
\t\t\t\tname="<?php echo esc_attr( $this->get_field_name( '${f.key}' ) ); ?>"
\t\t\t\tvalue="<?php echo esc_attr( $${f.key} ); ?>">
\t\t</p>`
    )
    .join("\n");

  const sanitizeFields = fields
    .map((f) =>
      f.type === "number"
        ? `\t\t\t'${f.key}' => max( 1, (int) ( $new_instance['${f.key}'] ?? 5 ) ),`
        : `\t\t\t'${f.key}' => sanitize_text_field( $new_instance['${f.key}'] ?? '' ),`
    )
    .join("\n");

  return `<?php

declare(strict_types=1);

use WPFlint\\Widgets\\AbstractWidget;

class ${name} extends AbstractWidget {

\tprotected string $widget_title = '${title}';
\tprotected string $description  = '${description}';

\tprotected function output( array $args, array $instance ): void {
${outputFields}

\t\t// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
\t\techo $args['before_widget'];
\t\tif ( $title ) {
\t\t\t// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
\t\t\techo $args['before_title'] . esc_html( $title ) . $args['after_title'];
\t\t}

\t\t// TODO: widget output here.

\t\t// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
\t\techo $args['after_widget'];
\t}

\tprotected function fields( array $instance ): void {
${formFields}
\t}

\tprotected function sanitize( array $new_instance, array $old_instance ): array {
\t\treturn array(
${sanitizeFields}
\t\t);
\t}
}

// Register: call from ServiceProvider boot() or widgets_init hook.
// ${name}::register();
`;
}

function assetStub(handle, url, type, deps, version, footer, localizeKey, localizeData) {
  if (type === "style") {
    const depsLine = deps.length ? `\n\t->deps( array( '${deps.join("', '")}' ) )` : "";
    return `<?php

declare(strict_types=1);

use WPFlint\\Assets\\Style;

Style::make( '${handle}', ${url} )
\t->version( '${version}' )${depsLine}
\t->enqueue();
`;
  }

  const depsLine = deps.length ? `\n\t->deps( array( '${deps.join("', '")}' ) )` : "";
  const footerLine = footer ? "\n\t->in_footer()" : "";
  const localizeLine = localizeKey
    ? `\n\t->localize( '${localizeKey}', array(
\t\t${localizeData}
\t) )`
    : "";

  return `<?php

declare(strict_types=1);

use WPFlint\\Assets\\Script;

Script::make( '${handle}', ${url} )
\t->version( '${version}' )${depsLine}${footerLine}${localizeLine}
\t->enqueue();
`;
}

function lifecycleStub(pluginFile, hooks) {
  let code = `<?php

declare(strict_types=1);

use WPFlint\\Lifecycle\\Lifecycle;

/**
 * Plugin lifecycle hooks.
 * Include this file from your main plugin file or bootstrap.
 */
`;

  if (hooks.includes("activation")) {
    code += `
Lifecycle::activation( ${pluginFile}, function () {
\t// Run on plugin activation.
\t// e.g. create tables, set default options, flush_rewrite_rules()
} );
`;
  }

  if (hooks.includes("deactivation")) {
    code += `
Lifecycle::deactivation( ${pluginFile}, function () {
\t// Run on plugin deactivation.
\t// e.g. unschedule events, flush_rewrite_rules()
} );
`;
  }

  if (hooks.includes("uninstall")) {
    code += `
// Add to uninstall.php:
Lifecycle::uninstall( function () {
\t// Run on plugin deletion.
\t// e.g. drop tables, delete_option(), remove capabilities
} );
`;
  }

  return code;
}

function noticeStub(message, type, dismissible) {
  const dismissSuffix = dismissible ? ".dismissible()" : "";
  return `<?php

declare(strict_types=1);

use WPFlint\\Admin\\Notice;

/**
 * Show an admin notice.
 * Call from a ServiceProvider boot() or inside an admin_notices hook.
 */
add_action( 'admin_notices', function () {
\tNotice::${type}( '${message}' )${dismissSuffix}->show();
} );

// Available types: success(), error(), warning(), info()
// Chain: ->dismissible() to make it closeable
`;
}

function viewStub(viewName, variables) {
  const varLines = variables.map((v) => `\t\t'${v}' => $${v},`).join("\n");
  const phpVars = variables.map((v) => `// $${v} is available`).join("\n");
  return `<?php // In your controller or callback:

declare(strict_types=1);

use WPFlint\\View\\View;

// Set the views directory once (in your service provider register()):
// View::set_path( plugin_dir_path( __FILE__ ) . 'templates' );

// Render the view:
View::render( '${viewName}', array(
${varLines}
) );

// Or get the HTML string:
// $html = View::make( '${viewName}', array( ... ) );

/* ---------- templates/${viewName}.php ---------- */
?>
<?php defined( 'ABSPATH' ) || exit; ?>
<?php
${phpVars}
?>
<div class="${viewName.replace(/[/\\]/g, "-")}">
\t<!-- Your template HTML here -->
</div>
`;
}

function restRoutesStub(namespace, version, routes) {
  let routeLines = "";
  routes.forEach((r) => {
    const method = r.method || "get";
    const path = r.path || "/" + snakeCase(r.resource || "items");
    const ctrl = r.controller || "MyController";
    const action = r.action || "index";
    const perm = r.permission || "logged_in";
    let permCode;
    switch (perm) {
      case "public":
        permCode = "RestAuth::public_access()";
        break;
      case "manage_options":
        permCode = "RestAuth::capability( 'manage_options' )";
        break;
      default:
        permCode = "RestAuth::logged_in()";
    }
    routeLines += `\t$r->${method}( '${path}', array( ${ctrl}::class, '${action}' ), ${permCode} );\n`;
  });

  return `<?php

declare(strict_types=1);

use WPFlint\\Http\\Router;
use WPFlint\\Http\\RestAuth;

// Register REST routes. Call from ServiceProvider boot():
add_action( 'rest_api_init', function () {
\tRouter::rest( RestAuth::namespace( '${namespace}', ${version} ), function ( $r ) {
${routeLines}\t} );
} );
`;
}

function restAuthStub(namespace, version, routes) {
  let examples = "";
  routes.forEach((r) => {
    examples += `\n\t// ${r.description || "Route"}\n`;
    examples += `\t'permission_callback' => RestAuth::${r.auth || "logged_in"}(),\n`;
  });

  return `<?php

declare(strict_types=1);

use WPFlint\\Http\\RestAuth;

$ns = RestAuth::namespace( '${namespace}', ${version} ); // '${namespace}/v${version}'

// Permission callback factories:
// RestAuth::logged_in()                         — any authenticated user
// RestAuth::public_access()                     — always true
// RestAuth::capability( 'manage_options' )      — single cap
// RestAuth::all_of( 'edit_posts', 'publish_posts' ) — all caps required
// RestAuth::any_of( 'edit_posts', 'edit_pages' )    — any cap sufficient

// Direct boolean checks (inside custom callbacks/controllers):
// RestAuth::require_logged_in()        // bool
// RestAuth::require_capability( $cap ) // bool

register_rest_route( $ns, '/example', array(
\t'methods'             => 'GET',
\t'callback'            => function ( \\WP_REST_Request $request ) {
\t\treturn rest_ensure_response( array( 'ok' => true ) );
\t},
\t'permission_callback' => RestAuth::logged_in(),
) );
`;
}

function routerAjaxStub(action, controllerClass, controllerMethod, middleware, nopriv) {
  const noPrivLine = nopriv ? "\n\t->nopriv()" : "";
  const mwLine =
    middleware.length
      ? `\n\t->middleware( array( '${middleware.join("', '")}' ) )`
      : "";
  return `<?php

declare(strict_types=1);

use WPFlint\\Http\\Router;

// Register AJAX handler. Call from ServiceProvider boot():
Router::ajax( '${action}', array( ${controllerClass}::class, '${controllerMethod}' ) )${noPrivLine}${mwLine};

// Usage from JS (jQuery example):
// jQuery.post( ajaxurl, {
//     action: '${action}',
//     nonce:  MyPluginData.nonce,
//     // ... other data
// }, function ( response ) { console.log( response ); } );
`;
}

function scaffoldPlugin(slug, namespace) {
  const mainFile = `<?php
/**
 * Plugin Name: ${slug}
 * Description: A WPFlint-powered plugin.
 * Version:     1.0.0
 * Text Domain: ${slug}
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
\texit;
}

require_once __DIR__ . '/vendor/autoload.php';

use WPFlint\\Application;

$app = Application::get_instance();
$app->boot();
`;

  const composerJson = JSON.stringify(
    {
      name: `vendor/${slug}`,
      description: `${slug} plugin`,
      type: "wordpress-plugin",
      require: { php: ">=7.4" },
      autoload: { "psr-4": { [`${namespace}\\`]: "app/" } },
    },
    null,
    2
  );

  const configApp = `<?php

declare(strict_types=1);

return array(
\t'name'    => '${slug}',
\t'version' => '1.0.0',
);
`;

  const mainProvider = `<?php

declare(strict_types=1);

namespace ${namespace}\\Providers;

use WPFlint\\Providers\\ServiceProvider;

class AppServiceProvider extends ServiceProvider {

\tpublic function register(): void {
\t\t//
\t}

\tpublic function boot(): void {
\t\t//
\t}
}
`;

  return {
    [`${slug}.php`]: mainFile,
    "composer.json": composerJson,
    "config/app.php": configApp,
    [`app/Providers/AppServiceProvider.php`]: mainProvider,
    "app/Models/.gitkeep": "",
    "database/migrations/.gitkeep": "",
    "templates/.gitkeep": "",
  };
}

// ===========================================================================
// MCP SERVER
// ===========================================================================

const server = new McpServer({
  name: "wpflint",
  version: "2.0.0",
  description: "WPFlint framework tools — full code generation + embedded API docs for AI assistants",
});

// ---------------------------------------------------------------------------
// RESOURCES — read-only knowledge base
// ---------------------------------------------------------------------------

server.resource(
  "wpflint-overview",
  "wpflint://overview",
  async (uri) => ({
    contents: [{
      uri: uri.href,
      text: KNOWLEDGE.overview,
      mimeType: "text/markdown",
    }],
  })
);

server.resource(
  "wpflint-patterns",
  "wpflint://patterns",
  async (uri) => ({
    contents: [{
      uri: uri.href,
      text: KNOWLEDGE.patterns,
      mimeType: "text/markdown",
    }],
  })
);

server.resource(
  "wpflint-api-module",
  new ResourceTemplate("wpflint://api/{module}", { list: undefined }),
  async (uri, { module }) => {
    const key = module.replace(/-/g, "_");
    const doc = KNOWLEDGE.modules[key];
    if (!doc) {
      const available = Object.keys(KNOWLEDGE.modules).join(", ");
      return {
        contents: [{
          uri: uri.href,
          text: `Module '${module}' not found. Available: ${available}`,
          mimeType: "text/plain",
        }],
      };
    }
    return {
      contents: [{
        uri: uri.href,
        text: doc,
        mimeType: "text/markdown",
      }],
    };
  }
);

// ---------------------------------------------------------------------------
// TOOLS — code generators
// ---------------------------------------------------------------------------

// ── Core scaffolding ────────────────────────────────────────────────────────

server.tool(
  "wpflint_scaffold_plugin",
  "Scaffold a new WPFlint plugin. Returns all starter files: main plugin file, composer.json, config, AppServiceProvider, and placeholder directories.",
  {
    slug: z.string().describe("Plugin slug, e.g. my-shop"),
    namespace: z.string().optional().default("App").describe("Root PHP namespace for the plugin, e.g. MyShop"),
  },
  async ({ slug, namespace }) => {
    const files = scaffoldPlugin(slug, namespace);
    let text = `## Scaffolded WPFlint Plugin: ${slug}\n\n`;
    for (const [path, content] of Object.entries(files)) {
      if (content === "") {
        text += `**\`${path}\`** _(empty placeholder — create this directory)_\n\n`;
      } else {
        const lang = path.endsWith(".json") ? "json" : "php";
        text += `**\`${path}\`**\n\n\`\`\`${lang}\n${content}\`\`\`\n\n`;
      }
    }
    text += `\n## Next steps\n1. \`composer install\` to pull in WPFlint\n2. Register your providers in \`${slug}.php\` via \`$app->register()\`\n3. Run \`wp plugin activate ${slug}\` to activate\n`;
    return { content: [{ type: "text", text }] };
  }
);

server.tool(
  "wpflint_make_provider",
  "Generate a WPFlint service provider stub with register() and boot() methods.",
  {
    name: z.string().describe("Provider class name in PascalCase, e.g. OrderServiceProvider"),
  },
  async ({ name }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Providers/${name}.php\`\n\n\`\`\`php\n${providerStub(name)}\`\`\`\n\n**Register in bootstrap:**\n\`\`\`php\n$app->register( ${name}::class );\n\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_make_migration",
  "Generate a WPFlint database migration stub. Filename gets a timestamp prefix automatically.",
  {
    name: z.string().describe("Migration class name in PascalCase, e.g. CreateOrdersTable"),
  },
  async ({ name }) => {
    const filename = `${migrationTimestamp()}_${snakeCase(name)}.php`;
    return {
      content: [{
        type: "text",
        text: `**File:** \`database/migrations/${filename}\`\n\n\`\`\`php\n${migrationStub(name)}\`\`\``,
      }],
    };
  }
);

server.tool(
  "wpflint_make_model",
  "Generate a WPFlint ORM model stub. Optionally generates a companion migration.",
  {
    name: z.string().describe("Model class name in PascalCase, e.g. Order"),
    migration: z.boolean().optional().default(false).describe("Also generate a companion migration file"),
    fillable: z.array(z.string()).optional().describe("Fillable field names, e.g. ['status', 'total']"),
    casts: z.record(z.string()).optional().describe("Casts map, e.g. {total: 'float', meta: 'array'}"),
  },
  async ({ name, migration, fillable, casts }) => {
    const table = snakeCase(name) + "s";
    const fillableLine = fillable && fillable.length
      ? `array( '${fillable.join("', '")}' )`
      : "array()";
    const castLines = casts && Object.keys(casts).length
      ? "array(\n\t\t'" + Object.entries(casts).map(([k, v]) => `${k}' => '${v}`).join("',\n\t\t'") + "',\n\t)"
      : "array()";

    const content = `<?php

declare(strict_types=1);

use WPFlint\\Database\\ORM\\Model;

class ${name} extends Model {

\tprotected static string $table = '${table}';

\tprotected array $fillable = ${fillableLine};

\tprotected array $casts = ${castLines};
}
`;

    let text = `**File:** \`app/Models/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``;

    if (migration) {
      const migrationName = `Create${name}sTable`;
      const filename = `${migrationTimestamp()}_${snakeCase(migrationName)}.php`;
      text += `\n\n---\n\n**File:** \`database/migrations/${filename}\`\n\n\`\`\`php\n${migrationStub(migrationName)}\`\`\``;
    }

    return { content: [{ type: "text", text }] };
  }
);

server.tool(
  "wpflint_make_controller",
  "Generate a WPFlint controller. Use rest:true for a REST API controller with permission checks.",
  {
    name: z.string().describe("Controller class name in PascalCase, e.g. OrderController"),
    rest: z.boolean().optional().default(false).describe("Generate a REST API controller extending RestController"),
  },
  async ({ name, rest }) => {
    const content = rest ? restControllerStub(name) : controllerStub(name);
    const dir = "app/Http/Controllers";
    return {
      content: [{
        type: "text",
        text: `**File:** \`${dir}/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
      }],
    };
  }
);

server.tool(
  "wpflint_make_middleware",
  "Generate a WPFlint middleware stub implementing MiddlewareInterface with a handle() method.",
  {
    name: z.string().describe("Middleware class name in PascalCase, e.g. EnsureStoreIsOpen"),
  },
  async ({ name }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Http/Middleware/${name}.php\`\n\n\`\`\`php\n${middlewareStub(name)}\`\`\`\n\n**Register alias (in ServiceProvider boot()):**\n\`\`\`php\n$app->make( Router::class )->aliasMiddleware( 'store.open', ${name}::class );\n\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_make_request",
  "Generate a WPFlint form request stub with authorize(), rules(), and sanitize() methods.",
  {
    name: z.string().describe("Request class name in PascalCase, e.g. StoreOrderRequest"),
    rules: z.record(z.string()).optional().describe("Validation rules map, e.g. {status: 'required|in:pending,paid'}"),
    authorize_cap: z.string().optional().describe("Capability to check in authorize(), e.g. edit_posts"),
  },
  async ({ name, rules, authorize_cap }) => {
    let rulesCode = "array()";
    if (rules && Object.keys(rules).length) {
      const lines = Object.entries(rules).map(([k, v]) => `\t\t\t'${k}' => '${v}',`).join("\n");
      rulesCode = `array(\n${lines}\n\t\t)`;
    }
    const authCode = authorize_cap
      ? `return current_user_can( '${authorize_cap}' );`
      : "return false;";

    const content = `<?php

declare(strict_types=1);

use WPFlint\\Http\\Request;

class ${name} extends Request {

\tpublic function authorize(): bool {
\t\t${authCode}
\t}

\tpublic function rules(): array {
\t\treturn ${rulesCode};
\t}

\tpublic function sanitize(): array {
\t\treturn array();
\t}
}
`;
    return {
      content: [{
        type: "text",
        text: `**File:** \`app/Http/Requests/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
      }],
    };
  }
);

server.tool(
  "wpflint_make_event",
  "Generate a WPFlint event class stub extending Event.",
  {
    name: z.string().describe("Event class name in PascalCase, e.g. OrderPlaced"),
    properties: z.array(z.string()).optional().describe("Constructor property names, e.g. ['order', 'user']"),
  },
  async ({ name, properties }) => {
    const props = properties && properties.length ? properties : [];
    const constructorLines = props.map((p) => `\t\t/** @var mixed */\n\t\tpublic $${p}`).join(";\n") + (props.length ? ";\n\n" : "");
    const paramLines = props.map((p) => `$${p}`).join(", ");
    const assignLines = props.map((p) => `\t\t$this->${p} = $${p};`).join("\n");

    const content = props.length
      ? `<?php

declare(strict_types=1);

use WPFlint\\Events\\Event;

class ${name} extends Event {

${constructorLines}\tpublic function __construct( ${paramLines} ) {
${assignLines}
\t}
}
`
      : eventStub(name);

    return {
      content: [{
        type: "text",
        text: `**File:** \`app/Events/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
      }],
    };
  }
);

server.tool(
  "wpflint_make_listener",
  "Generate a WPFlint event listener stub with a handle() method type-hinted to the event class.",
  {
    name: z.string().describe("Listener class name in PascalCase, e.g. SendOrderConfirmation"),
    event: z.string().optional().default("").describe("Event class to type-hint in handle(), e.g. OrderPlaced"),
  },
  async ({ name, event }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Listeners/${name}.php\`\n\n\`\`\`php\n${listenerStub(name, event)}\`\`\`\n\n**Register in ServiceProvider boot():**\n\`\`\`php\nEvent::listen( ${event || "YourEvent"}::class, ${name}::class );\n\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_make_facade",
  "Generate a WPFlint facade stub extending Facade.",
  {
    name: z.string().describe("Facade class name in PascalCase, e.g. OrderFacade"),
    accessor: z.string().optional().default("").describe("Container binding key returned by get_facade_accessor(), e.g. 'order'"),
  },
  async ({ name, accessor }) => {
    const content = `<?php

declare(strict_types=1);

use WPFlint\\Facades\\Facade;

class ${name} extends Facade {

\tprotected static function get_facade_accessor(): string {
\t\treturn '${accessor}';
\t}
}
`;
    return {
      content: [{
        type: "text",
        text: `**File:** \`app/Facades/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
      }],
    };
  }
);

server.tool(
  "wpflint_make_rule",
  "Generate a custom validation rule stub implementing RuleInterface.",
  {
    name: z.string().describe("Rule class name in PascalCase, e.g. UniqueEmail"),
    message: z.string().optional().default("The :attribute field is invalid.").describe("Validation error message"),
  },
  async ({ name, message }) => {
    const content = `<?php

declare(strict_types=1);

use WPFlint\\Validation\\Rules\\RuleInterface;

class ${name} implements RuleInterface {

\tpublic function passes( $value ): bool {
\t\t// TODO: implement rule logic.
\t\treturn true;
\t}

\tpublic function message(): string {
\t\treturn __( '${message}', 'text-domain' );
\t}
}
`;
    return {
      content: [{
        type: "text",
        text: `**File:** \`app/Rules/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
      }],
    };
  }
);

server.tool(
  "wpflint_make_command",
  "Generate a custom WP-CLI command stub extending WPFlint Command.",
  {
    name: z.string().describe("Command class name in PascalCase, e.g. SyncInventoryCommand"),
  },
  async ({ name }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Console/${name}.php\`\n\n\`\`\`php\n${commandStub(name)}\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_make_job",
  "Generate a WPFlint async job stub for the queue system.",
  {
    name: z.string().describe("Job class name in PascalCase, e.g. SendWelcomeEmail"),
    queue: z.string().optional().default("default").describe("Queue name"),
    tries: z.number().optional().default(3).describe("Max retry attempts before marking as failed"),
  },
  async ({ name, queue, tries }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Jobs/${name}.php\`\n\n\`\`\`php\n${jobStub(name, queue, tries)}\`\`\`\n\n**Dispatch:**\n\`\`\`php\n$app->make( 'queue' )->push( new ${name}() );\n\`\`\``,
    }],
  })
);

// ── HTTP / REST ─────────────────────────────────────────────────────────────

server.tool(
  "wpflint_router_ajax",
  "Generate AJAX route registration code using WPFlint Router::ajax(). Includes JS usage example.",
  {
    action: z.string().describe("AJAX action name, e.g. my_plugin/save_order"),
    controller: z.string().optional().default("MyController").describe("Controller class name"),
    method: z.string().optional().default("store").describe("Controller method name"),
    middleware: z.array(z.string()).optional().default([]).describe("Middleware list, e.g. ['nonce:save_order','can:edit_posts']"),
    nopriv: z.boolean().optional().default(false).describe("Allow unauthenticated (non-logged-in) users"),
  },
  async ({ action, controller, method, middleware, nopriv }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Providers/RouteServiceProvider.php\` or inside \`boot()\`\n\n\`\`\`php\n${routerAjaxStub(action, controller, method, middleware, nopriv)}\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_rest_routes",
  "Generate REST API route registration code using WPFlint Router::rest() with RestAuth permission callbacks.",
  {
    namespace: z.string().describe("Plugin namespace slug, e.g. my-plugin"),
    version: z.number().optional().default(1).describe("API version number"),
    routes: z.array(z.object({
      method: z.enum(["get", "post", "put", "patch", "delete"]).optional().default("get"),
      path: z.string().describe("Route path, e.g. /orders or /orders/(?P<id>\\d+)"),
      controller: z.string().optional().default("MyController"),
      action: z.string().optional().default("index"),
      permission: z.enum(["public", "logged_in", "manage_options"]).optional().default("logged_in"),
    })).describe("Route definitions"),
  },
  async ({ namespace, version, routes }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Providers/RouteServiceProvider.php\`\n\n\`\`\`php\n${restRoutesStub(namespace, version, routes)}\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_rest_auth",
  "Generate REST API auth usage examples using WPFlint RestAuth factory methods.",
  {
    namespace: z.string().optional().default("my-plugin").describe("Plugin namespace slug"),
    version: z.number().optional().default(1).describe("API version number"),
  },
  async ({ namespace, version }) => ({
    content: [{
      type: "text",
      text: `\`\`\`php\n${restAuthStub(namespace, version, [])}\`\`\`\n\n## Quick Reference\n\n| Method | Returns | Description |\n|--------|---------|-------------|\n| \`capability( $cap )\` | \`callable\` | Single capability |\n| \`logged_in()\` | \`callable\` | Any authenticated user |\n| \`public_access()\` | \`callable\` | Always true |\n| \`all_of( ...$caps )\` | \`callable\` | ALL caps required |\n| \`any_of( ...$caps )\` | \`callable\` | ANY cap sufficient |\n| \`require_logged_in()\` | \`bool\` | Direct check |\n| \`require_capability( $cap )\` | \`bool\` | Direct check |\n| \`namespace( $plugin, $v )\` | \`string\` | Builds \`plugin/vN\` |`,
    }],
  })
);

// ── Admin UI ────────────────────────────────────────────────────────────────

server.tool(
  "wpflint_make_admin_page",
  "Generate a WPFlint AdminPage registration stub. Supports top-level and submenu pages.",
  {
    slug: z.string().describe("Page slug, e.g. my-plugin-settings"),
    title: z.string().describe("Menu title, e.g. My Plugin Settings"),
    parent: z.string().optional().default("").describe("Parent menu slug for submenu, e.g. options-general.php. Leave empty for top-level."),
    capability: z.string().optional().default("manage_options").describe("Required capability"),
    icon: z.string().optional().default("").describe("Dashicon class for top-level pages, e.g. dashicons-admin-tools"),
  },
  async ({ slug, title, parent, capability, icon }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Admin/Pages/${slug}.php\` or inside a ServiceProvider \`boot()\`\n\n\`\`\`php\n${adminPageStub(slug, title, parent, capability)}\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_make_settings",
  "Generate a WPFlint Settings API registration stub with sections and fields.",
  {
    option_group: z.string().describe("Option group name (stored as a single option), e.g. my_plugin_options"),
    page_title: z.string().optional().default("My Plugin Settings").describe("Settings page title"),
    sections: z.array(z.string()).optional().default(["general"]).describe("Section slugs, e.g. ['general', 'advanced']"),
    fields: z.array(z.string()).optional().default(["api_key"]).describe("Field key names, e.g. ['api_key', 'enable_logs']"),
  },
  async ({ option_group, page_title, sections, fields }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Providers/SettingsServiceProvider.php\` or inside \`boot()\`\n\n\`\`\`php\n${settingsStub(option_group, page_title, sections, fields)}\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_make_metabox",
  "Generate a WPFlint MetaBox registration stub with fields. All fields are auto-saved with nonce verification.",
  {
    id: z.string().describe("Metabox ID, e.g. product_details"),
    title: z.string().describe("Metabox title, e.g. Product Details"),
    screen: z.string().optional().default("post").describe("Post type slug, e.g. product, post, page"),
    fields: z.array(z.object({
      id: z.string().describe("Field key (meta key), e.g. _price"),
      label: z.string().describe("Field label"),
      type: z.enum(["text", "number", "textarea", "checkbox", "select", "email", "url"]).optional().default("text"),
      description: z.string().optional().default(""),
    })).optional().default([]).describe("Field definitions"),
  },
  async ({ id, title, screen, fields }) => {
    let fieldLines = "";
    fields.forEach((f) => {
      const descLine = f.description ? `\n\t\t\t->description( '${f.description}' )` : "";
      fieldLines += `\n\t$box->field( '${f.id}', '${f.label}' )->type( '${f.type}' )${descLine};`;
    });
    if (!fieldLines) {
      fieldLines = "\n\t$box->field( '_example', 'Example Field' )->type( 'text' );";
    }

    const content = `<?php

declare(strict_types=1);

use WPFlint\\Admin\\MetaBox;

add_action( 'add_meta_boxes', function () {
\t$box = MetaBox::make( '${id}', '${title}' )
\t\t->screen( '${screen}' )
\t\t->context( 'normal' )
\t\t->priority( 'high' );
${fieldLines}
\t$box->register();
} );

// Reading values:
// $value = get_post_meta( $post->ID, '_example', true );
`;
    return {
      content: [{
        type: "text",
        text: `**File:** \`app/Admin/MetaBoxes/${id}.php\` or inside a ServiceProvider \`boot()\`\n\n\`\`\`php\n${content}\`\`\``,
      }],
    };
  }
);

server.tool(
  "wpflint_make_notice",
  "Generate a WPFlint admin notice snippet. Supports success, error, warning, and info types.",
  {
    message: z.string().describe("Notice message text"),
    type: z.enum(["success", "error", "warning", "info"]).optional().default("success").describe("Notice type"),
    dismissible: z.boolean().optional().default(true).describe("Whether the notice can be dismissed"),
  },
  async ({ message, type, dismissible }) => ({
    content: [{
      type: "text",
      text: `\`\`\`php\n${noticeStub(message, type, dismissible)}\`\`\``,
    }],
  })
);

// ── Content types ───────────────────────────────────────────────────────────

server.tool(
  "wpflint_make_post_type",
  "Generate a fluent PostType registration snippet for WPFlint.",
  {
    slug: z.string().describe("Post type slug, e.g. book"),
    singular: z.string().describe("Singular label, e.g. Book"),
    plural: z.string().optional().describe("Plural label (defaults to singular + s)"),
    public: z.boolean().optional().default(true),
    show_in_rest: z.boolean().optional().default(true),
    supports: z.array(z.string()).optional().describe("Supported features, e.g. ['title','editor','thumbnail']"),
    icon: z.string().optional().describe("Dashicon class, e.g. dashicons-book-alt"),
    hierarchical: z.boolean().optional().default(false),
  },
  async ({ slug, singular, plural, public: pub, show_in_rest, supports, icon, hierarchical }) => {
    const p = plural || singular + "s";
    const feats = supports && supports.length ? `\n        ->supports( array( '${supports.join("', '")}' ) )` : "";
    const ico = icon ? `\n        ->icon( '${icon}' )` : "";
    const rest = show_in_rest ? "\n        ->show_in_rest()" : "";
    const pub_ = pub ? "\n        ->public()" : "";
    const hier = hierarchical ? "\n        ->hierarchical()" : "";
    const text = `use WPFlint\\PostTypes\\PostType;\n\nPostType::make( '${slug}' )\n        ->label( '${singular}', '${p}' )${pub_}${feats}${hier}${ico}${rest}\n        ->register();`;
    return { content: [{ type: "text", text: `\`\`\`php\n${text}\n\`\`\`` }] };
  }
);

server.tool(
  "wpflint_make_taxonomy",
  "Generate a fluent Taxonomy registration snippet for WPFlint.",
  {
    slug: z.string().describe("Taxonomy slug, e.g. genre"),
    singular: z.string().describe("Singular label, e.g. Genre"),
    plural: z.string().optional().describe("Plural label (defaults to singular + s)"),
    post_types: z.array(z.string()).describe("Post type slugs to attach, e.g. ['book']"),
    hierarchical: z.boolean().optional().default(false),
    show_in_rest: z.boolean().optional().default(true),
  },
  async ({ slug, singular, plural, post_types, hierarchical, show_in_rest }) => {
    const p = plural || singular + "s";
    const pts = post_types.map((t) => `'${t}'`).join(", ");
    const hier = hierarchical ? "\n        ->hierarchical()" : "";
    const rest = show_in_rest ? "\n        ->show_in_rest()" : "";
    const text = `use WPFlint\\Taxonomies\\Taxonomy;\n\nTaxonomy::make( '${slug}' )\n        ->label( '${singular}', '${p}' )\n        ->for( array( ${pts} ) )${hier}${rest}\n        ->register();`;
    return { content: [{ type: "text", text: `\`\`\`php\n${text}\n\`\`\`` }] };
  }
);

server.tool(
  "wpflint_make_meta_field",
  "Generate a fluent MetaField registration snippet for WPFlint post, term, user, or comment meta.",
  {
    object_type: z.enum(["post", "term", "user", "comment"]).describe("Meta object type"),
    subtype: z.string().optional().default("").describe("Post type or taxonomy slug (omit for user/comment)"),
    key: z.string().describe("Meta key, e.g. _price"),
    type: z.string().optional().default("string").describe("Value type: string, boolean, integer, number, array, object"),
    single: z.boolean().optional().default(true),
    show_in_rest: z.boolean().optional().default(false),
  },
  async ({ object_type, subtype, key, type, single, show_in_rest }) => {
    let factory;
    if (object_type === "post") factory = `MetaField::post( '${subtype}', '${key}' )`;
    else if (object_type === "term") factory = `MetaField::term( '${subtype}', '${key}' )`;
    else if (object_type === "user") factory = `MetaField::user( '${key}' )`;
    else factory = `MetaField::comment( '${key}' )`;
    const typ = `\n        ->type( '${type}' )`;
    const sing = single ? "\n        ->single()" : "";
    const rest = show_in_rest ? "\n        ->show_in_rest()" : "";
    const text = `use WPFlint\\Meta\\MetaField;\n\n${factory}${typ}${sing}${rest}\n        ->register();`;
    return { content: [{ type: "text", text: `\`\`\`php\n${text}\n\`\`\`` }] };
  }
);

server.tool(
  "wpflint_make_shortcode",
  "Generate a WPFlint Shortcode registration stub with attribute defaults and output scaffolding.",
  {
    tag: z.string().describe("Shortcode tag, e.g. my_orders"),
    atts: z.array(z.string()).optional().default([]).describe("Attribute names, e.g. ['limit', 'status']"),
  },
  async ({ tag, atts }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Shortcodes/${tag}.php\` or in ServiceProvider \`boot()\`\n\n\`\`\`php\n${shortcodeStub(tag, atts)}\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_make_block",
  "Generate a Gutenberg block registration stub using WPFlint Block builder. Supports both dynamic (PHP render) and static blocks.",
  {
    name: z.string().describe("Block name in namespace/block-name format, e.g. my-plugin/hero"),
    title: z.string().describe("Human-readable block title, e.g. Hero Section"),
    category: z.enum(["text", "media", "design", "widgets", "theme", "embed"]).optional().default("design"),
    dynamic: z.boolean().optional().default(true).describe("True for PHP server-side rendering, false for static JS-only block"),
    attributes: z.array(z.string()).optional().describe("Attribute names, e.g. ['heading', 'align']"),
  },
  async ({ name, title, category, dynamic }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Blocks/${name.split("/")[1]}.php\` or in ServiceProvider \`boot()\`\n\n\`\`\`php\n${blockStub(name, title, category, dynamic)}\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_make_widget",
  "Generate a WPFlint AbstractWidget subclass stub with output(), fields(), and sanitize() methods.",
  {
    name: z.string().describe("Widget class name in PascalCase, e.g. RecentPostsWidget"),
    title: z.string().describe("Widget title shown in the Widgets admin screen"),
    description: z.string().optional().default("A custom widget.").describe("Short widget description"),
  },
  async ({ name, title, description }) => ({
    content: [{
      type: "text",
      text: `**File:** \`app/Widgets/${name}.php\`\n\n\`\`\`php\n${widgetStub(name, title, description)}\`\`\`\n\n**Register in ServiceProvider boot():**\n\`\`\`php\n${name}::register();\n\`\`\``,
    }],
  })
);

// ── Frontend ─────────────────────────────────────────────────────────────────

server.tool(
  "wpflint_make_asset",
  "Generate Script or Style enqueue code using WPFlint Asset builders.",
  {
    handle: z.string().describe("Asset handle, e.g. my-plugin-app"),
    src: z.string().describe("Asset source path or PHP expression, e.g. plugin_dir_url(__FILE__) . 'dist/app.js'"),
    type: z.enum(["script", "style"]).optional().default("script"),
    deps: z.array(z.string()).optional().default([]).describe("Dependency handles, e.g. ['jquery', 'wp-element']"),
    version: z.string().optional().default("1.0.0"),
    in_footer: z.boolean().optional().default(true).describe("Enqueue in footer (scripts only)"),
    localize_key: z.string().optional().default("").describe("JS object name for wp_localize_script, e.g. MyPluginData"),
    localize_data: z.string().optional().default("'ajax_url' => admin_url( 'admin-ajax.php' ),\n\t\t'nonce'    => wp_create_nonce( 'my_action' ),").describe("PHP key => value pairs for localize data"),
  },
  async ({ handle, src, type, deps, version, in_footer, localize_key, localize_data }) => {
    const url = src.includes("(") ? src : `'${src}'`;
    return {
      content: [{
        type: "text",
        text: `\`\`\`php\n${assetStub(handle, url, type, deps, version, in_footer, localize_key, localize_data)}\`\`\``,
      }],
    };
  }
);

server.tool(
  "wpflint_make_view",
  "Generate View::render() usage code and a starter PHP template file.",
  {
    view_name: z.string().describe("View path relative to templates dir, e.g. orders/index"),
    variables: z.array(z.string()).optional().default([]).describe("Variable names to pass to the view, e.g. ['orders', 'title']"),
  },
  async ({ view_name, variables }) => ({
    content: [{
      type: "text",
      text: `\`\`\`php\n${viewStub(view_name, variables)}\`\`\``,
    }],
  })
);

// ── System ───────────────────────────────────────────────────────────────────

server.tool(
  "wpflint_make_lifecycle",
  "Generate plugin lifecycle hook code for activation, deactivation, and uninstall.",
  {
    plugin_file: z.string().optional().default("__FILE__").describe("PHP expression for the plugin main file, typically __FILE__"),
    hooks: z.array(z.enum(["activation", "deactivation", "uninstall"])).optional().default(["activation", "deactivation", "uninstall"]).describe("Which lifecycle hooks to generate"),
  },
  async ({ plugin_file, hooks }) => ({
    content: [{
      type: "text",
      text: `\`\`\`php\n${lifecycleStub(plugin_file, hooks)}\`\`\``,
    }],
  })
);

server.tool(
  "wpflint_logging_usage",
  "Show WPFlint Logger setup and usage examples with constants, provider registration, and all log levels.",
  {
    channel: z.string().optional().default("my-plugin").describe("Log channel name (plugin slug)"),
    min_level: z.enum(["debug", "info", "notice", "warning", "error", "critical", "alert", "emergency"]).optional().default("debug"),
  },
  async ({ channel, min_level }) => ({
    content: [{
      type: "text",
      text: KNOWLEDGE.modules.logging.replace("my-plugin", channel).replace("'debug'", `'${min_level}'`),
    }],
  })
);

server.tool(
  "wpflint_schedule_usage",
  "Show WPFlint Scheduler setup and schedule definition examples with all available intervals.",
  {
    plugin_slug: z.string().optional().default("my-plugin").describe("Plugin slug for examples"),
  },
  async ({ plugin_slug }) => ({
    content: [{
      type: "text",
      text: KNOWLEDGE.modules.scheduling.replace(/my_plugin/g, plugin_slug.replace(/-/g, "_")).replace(/my-plugin/g, plugin_slug),
    }],
  })
);

// ── Discovery ────────────────────────────────────────────────────────────────

server.tool(
  "wpflint_framework_overview",
  "Get the complete WPFlint framework overview: architecture, philosophy, directory map, bootstrap pattern, PHP 7.4 rules, and WordPress compliance requirements. Read this first when starting a new plugin.",
  {},
  async () => ({
    content: [{ type: "text", text: KNOWLEDGE.overview }],
  })
);

server.tool(
  "wpflint_module_docs",
  "Get detailed API documentation for a specific WPFlint module. Use this to understand a module before generating code for it.",
  {
    module: z.enum([
      "container", "http", "database", "cache", "events",
      "admin", "blocks", "widgets", "assets", "lifecycle",
      "view", "mail", "shortcodes", "rest_auth", "logging",
      "queue", "scheduling",
    ]).describe("Module name to get docs for"),
  },
  async ({ module }) => {
    const doc = KNOWLEDGE.modules[module];
    if (!doc) {
      return { content: [{ type: "text", text: `No documentation found for module: ${module}` }] };
    }
    return { content: [{ type: "text", text: doc }] };
  }
);

// ---------------------------------------------------------------------------
// Start
// ---------------------------------------------------------------------------

const transport = new StdioServerTransport();
await server.connect(transport);
