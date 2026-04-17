#!/usr/bin/env node
import { McpServer, ResourceTemplate } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";
import { readFileSync } from "fs";
import { join } from "path";

// ─── SESSION STATE ────────────────────────────────────────────────────────────
const S = { ns: "App", slug: "my-plugin", td: "my-plugin", path: "", ok: false };
const ns  = (sub) => `${S.ns}\\${sub}`;
const td  = ()    => S.td;

// ─── KNOWLEDGE BASE ───────────────────────────────────────────────────────────
const KB = {

"guide": `# WPFlint Bootstrap & Rules

## Bootstrap (main plugin file)
\`\`\`php
require_once __DIR__ . '/vendor/autoload.php';
use WPFlint\\Application;
$app = Application::get_instance( __DIR__ );
$app->register( App\\Providers\\AppServiceProvider::class );
$app->bootstrap(); // hooks plugins_loaded → register, init → boot, wp_loaded → deferred
\`\`\`

## Application (extends Container)
get_instance(string $base_path=''): static
bootstrap(): void          — hooks providers into WP lifecycle
register($provider): ServiceProvider
make(string $abstract): mixed
base_path(string $path=''): string
is_booted(): bool

## ServiceProvider
abstract register(): void  — bind into container (no WP hooks here)
boot(): void               — register hooks, actions, filters
provides(): array          — list abstracts (required when $defer=true)
public bool $defer = false — lazy: resolved only on first make()

## PHP 7.4 — NEVER USE
named args · enums · match() · union types · readonly · nullsafe ?-> in loops
str_contains/str_starts_with · fibers · callable as property type hint
USE INSTEAD: strpos()!==false · arrow fn() => · @var callable docblock

## WordPress — ALWAYS
\$wpdb->prepare() on EVERY query
\$wpdb->prefix.'tablename' for all tables
__() / _e() for all strings
wp_nonce_field() + check_admin_referer() on every form
current_user_can() before every privileged op
sanitize_*() on input · esc_*() on output`,

"api/container": `# Container + Application

## Container
bind(string $abstract, $concrete=null, bool $shared=false): void
singleton(string $abstract, $concrete=null): void
instance(string $abstract, $instance): void
make(string $abstract): mixed          — auto-resolves constructor deps
get(string $id): mixed                 — PSR-11
has(string $id): bool
forget(string $abstract): void
when(string $concrete): ContextualBindingBuilder — contextual binding

## ContextualBindingBuilder
needs(string $abstract): self
give($implementation): void

## Example
\`\`\`php
$app->singleton(OrderRepository::class, fn($a) => new EloquentOrderRepository($a->make(DB::class)));
$app->when(OrderController::class)->needs(Repository::class)->give(OrderRepository::class);
$repo = $app->make(OrderRepository::class);
\`\`\`

## Gotchas
- register() runs before WP init — never call WP functions inside register()
- $defer=true requires provides() to return the exact abstract strings
- make() on a deferred provider triggers its register() automatically`,

"api/http": `# HTTP — Router · Controller · Request · Response · Middleware

## Router (instance from container)
ajax(string $action, array $handler): Route
rest(string $namespace, Closure $callback): void
alias_middleware(string $alias, string $class): void
boot(): void  — call once after all routes registered

## Route (returned by ajax())
->middleware(array $list): self   — e.g. ['nonce:save_order','can:edit_posts','throttle:60,1']
->nopriv(): self                  — allow unauthenticated users

## RestRouter (passed to rest() closure)
get/post/put/patch/delete(string $route, $callback, $permission_callback=null): self

## Controller
Constructor deps auto-resolved from container.
Method type-hinting a Request subclass triggers authorize()→validate() before the method runs.

## RestController
protected string $namespace = '';
protected string $rest_base  = '';
protected function respond($data=null, int $status=200): \WP_REST_Response

## Request
static capture(): self
input(string $key, $default=null): mixed   — dot notation
all(): array · only(array $keys): array · except(array $keys): array
has(string $key): bool
file(string $key): array|null
validated(): array · errors(): array
authorize(): bool   — override; checked before validate()
rules(): array      — override; laravel-style 'required|string|max:255'
sanitize(): array   — override; ['field'=>'sanitize_text_field']
validate(): bool    — called automatically by router

## Response
static json($data=null, int $status=200): self
static error(string $message, int $status=400): self
static no_content(): self
with_header(string $name, string $value): self
send_ajax(): void · to_rest(): \WP_REST_Response

## Built-in Middleware
nonce:{action}          — check_admin_referer()
can:{capability}        — current_user_can()
throttle:{max},{minutes}

## MiddlewareInterface
handle(Request $request, Closure $next): mixed

## Wire-up
\`\`\`php
// ServiceProvider boot():
$router = $this->app->make(Router::class);
$router->ajax('my-plugin/save', [OrderController::class, 'store'])
       ->middleware(['nonce:save_order','can:edit_posts']);
$router->rest(RestAuth::namespace('my-plugin'), function($r) {
    $r->get('/orders', [OrderRestController::class, 'index'], RestAuth::logged_in());
    $r->post('/orders', [OrderRestController::class, 'store'], RestAuth::capability('edit_posts'));
});
$router->boot();
\`\`\``,

"api/rest-auth": `# RestAuth — Permission Callbacks + Namespace Builder

static capability(string $cap): callable
static logged_in(): callable
static public_access(): callable
static all_of(string ...$caps): callable
static any_of(string ...$caps): callable
static require_logged_in(): bool        — direct check inside controller
static require_capability(string $cap): bool
static namespace(string $plugin, int $version=1): string  — 'plugin/v1'

## Example
\`\`\`php
use WPFlint\\Http\\RestAuth;
$ns = RestAuth::namespace('my-plugin'); // 'my-plugin/v1'
register_rest_route($ns, '/orders', [
    'methods'             => 'GET',
    'callback'            => [$ctrl, 'index'],
    'permission_callback' => RestAuth::capability('manage_options'),
]);
// Or via Router:
$r->get('/orders', [Ctrl::class, 'index'], RestAuth::all_of('edit_posts','publish_posts'));
\`\`\``,

"api/database": `# Database — Migration · Blueprint · Model · QueryBuilder

## Migration
abstract up(): void · abstract down(): void
$this->schema(): Schema
Schema::create(string $table, Closure $blueprint): void
Schema::drop(string $table): void
Schema::drop_if_exists(string $table): void

## Blueprint columns
big_increments(col) · string(col, len=255) · integer(col) · decimal(col, precision=8, scale=2)
boolean(col) · text(col) · timestamps() · soft_deletes()
index($cols) · unique($cols) · foreign(col): ForeignKeyDefinition

## ColumnDefinition chains (apply after any column method)
->nullable(): self          — allows NULL; e.g. $t->string('note')->nullable()
->default($value): self     — set default; e.g. $t->integer('sort')->default(0)
Example: $t->string('status',50)->nullable()->default('active')

## Model (Active Record)
protected static string $table = '';          — unprefixed; auto-inferred if empty
protected static string $primary_key = 'id';
protected static bool   $timestamps  = true;
protected array $fillable = [];
protected array $hidden   = [];
protected array $casts    = [];               — int|float|string|bool|array|json|datetime

## Static finders
find($id): ?static · find_or_fail($id): static
all(): array · where(...): ModelQueryBuilder
create(array $data): static · first_or_create(array $attrs, array $vals=[]): static
first_or_new(array $attrs, array $vals=[]): static   — find or build WITHOUT saving
update_or_create(array $attrs, array $vals=[]): static
destroy($id): bool                   — DELETE by primary key
cached($id, int $ttl=3600): ?static  — find() with transient cache
fresh_find($id): ?static             — find() bypassing all cache
count(): int

## Instance methods
save(): bool · delete(): bool · fresh(): static
fill(array $attrs): self · get_attribute(string $key) · set_attribute(string $key, $val)
get_dirty(): array · is_dirty(?string $key): bool
to_array(): array · to_json(int $opts=0): string
has_one(string $related, string $fk='', string $lk=''): HasOne
has_many(string $related, string $fk='', string $lk=''): HasMany
belongs_to(string $related, string $fk='', string $lk=''): BelongsTo

## QueryBuilder
select(array $cols): self   — specify columns; default *
distinct(): self
where(col, op=null, val=null) · or_where · where_in · where_not_in · where_null · where_not_null
where_between · where_not_between · where_like · where_raw(sql, params=[])
join · left_join · right_join(table, first, op, second)
order_by(col, dir='ASC') · latest(col='created_at') · oldest(col='created_at')
group_by(col) · having(col, op, val) · limit(n) · offset(n) · take(n, start=0)
get(): array · first(): ?array · find($id, col='id'): ?array
value(col) · pluck(col, key=null): array
exists(): bool · doesnt_exist(): bool
count/max/min/avg/sum(col)
paginate(per_page=15, page=1): array   — returns {data, total, per_page, current_page, last_page}
chunk(size, Closure $cb): void
insert(array $data)
insert_many(array $rows): int          — bulk insert; returns inserted count
update(array $data): int|false         — UPDATE matching rows; returns affected count
delete(): int|false                    — DELETE matching rows; returns affected count
increment(col, amount=1) · decrement(col, amount=1)

## ModelQueryBuilder (returned by Model::where(), scopes, all chained Model calls)
Inherits all QueryBuilder methods PLUS:
with(array $relations): self   — eager-load named relations; prevents N+1
get(): Model[]                 — returns hydrated Model instances (not raw arrays)
first_model(): ?Model          — returns first hydrated Model
// Example: Order::where('status','pending')->with(['items','user'])->get();
// Note: update()/delete() work here too — Order::where('status','old')->delete();

## Example
\`\`\`php
// Migration (database/migrations/2024_01_01_000000_create_orders_table.php)
class CreateOrdersTable extends Migration {
    public function up(): void {
        $this->schema()->create('orders', function(Blueprint $t) {
            $t->big_increments('id');
            $t->string('status')->default('pending');
            $t->decimal('total', 10, 2)->default('0.00');
            $t->timestamps();
        });
    }
    public function down(): void { $this->schema()->drop('orders'); }
}

// Model
class Order extends Model {
    protected static string \$table    = 'orders';
    protected array         \$fillable = ['status','total'];
    protected array         \$casts    = ['total'=>'float'];
    public function scope_pending(ModelQueryBuilder \$q): ModelQueryBuilder {
        return \$q->where('status','pending');
    }
    public function items(): HasMany { return \$this->has_many(OrderItem::class,'order_id'); }
}

// Usage
Order::pending()->where('total','>',100)->order_by('created_at','desc')->get();
Order::find(5);
Order::create(['status'=>'pending','total'=>49.99]);
\$order->items()->get();
\`\`\``,

"api/cache": `# Cache — CacheManager · TaggedCache

## CacheManager
get(string $key, $default=null): mixed
put(string $key, $value, int $ttl=0): bool   — ttl=0 means no expiry
forget(string $key): bool
has(string $key): bool
flush(): bool
remember(string $key, int $ttl, callable $cb): mixed
tags($tags): TaggedCache          — string or string[]
fresh(): self                     — bypass all cache tiers

## TaggedCache
remember/get/put/forget/flush — same signatures as CacheManager

## Facades
use WPFlint\\Facades\\Cache;
Cache::remember('key', 3600, fn() => Order::all());
Cache::tags(['orders'])->flush();
Cache::fresh()->remember('key', 3600, \$cb);

## Helpers
wpflint_cache(): CacheManager
wpflint_cache(['orders','user:1']): TaggedCache`,

"api/events": `# Events — Dispatcher · Event · Listeners

## Dispatcher
listen(string $event_class, $listener): void   — listener: class name or Closure
fire(Event $event): Event
forget(string $event_class): void
has_listeners(string $event_class): bool
listen_wp(string $hook, string $event_class, int $priority=10, int $args=1): void  — bridge WP hooks

## Event base class
Extend it. No required methods. Add public properties for payload.

## Wire-up
\`\`\`php
// ServiceProvider boot():
use WPFlint\\Facades\\Event;
Event::listen(OrderPlaced::class, SendConfirmation::class);
Event::listen(OrderPlaced::class, function(OrderPlaced \$e) { ... });

// Fire:
wpflint_event(new OrderPlaced(\$order));
// or:
Event::fire(new OrderPlaced(\$order));
\`\`\``,

"api/admin": `# Admin — AdminPage · Settings · MetaBox · Notice

## AdminPage
static make(string $page_title, string $menu_slug): self
title(string $menu_title): self       — separate menu label
capability(string $cap): self
icon(string $dashicon): self
position(int $pos): self
render(callable $cb): self            — callback renders page HTML
submenu(string $title, string $slug, callable $render=null): self
register(): void                      — top-level page
register_as_submenu(string $parent_slug): void  — e.g. 'options-general.php'

## Settings
static make(string $option_group, string $option_name): self
page(string $page_slug): self
sanitize(callable $cb): self
section(string $id, string $title, callable $cb): self  — cb receives Section
register(): void
// Read: get_option('option_name', [])
// Form: settings_fields('group'); do_settings_sections('page'); submit_button();

## Section
field(string $id, string $label): Field
description(string $text): self

## Field
type(string $t): self      — text|textarea|checkbox|select|number|email|url|password
default($v): self
description(string $t): self
required(bool $r=true): self
options(array $opts): self  — for select: ['val'=>'Label']

## MetaBox
static make(string $id, string $title): self
screen($screen): self       — string or array of post type slugs
context(string $ctx): self  — normal|side|advanced
priority(string $p): self   — high|default|low|core
field(string $id, string $label): MetaBoxField
register(): void             — hooks add_meta_boxes + save_post (nonce, autosave, cap all handled)
// Read: get_post_meta(\$post->ID, '_key', true);

## MetaBoxField
type(string $t): self        — text|number|textarea|checkbox|select|email|url
description(string $t): self
default_value($v): self
options(array $opts): self
sanitize_with(callable $cb): self

## Notice
static success/error/warning/info(string $message): self
dismissible(bool $d=true): self
render(): string
flash(): void                — show once on next page load
static display_flash(): void — hook to admin_notices
persistent(string $key): void
static display_persistent(string $key): void
static dismiss(string $key): void`,

"api/registration": `# Registration — PostType · Taxonomy · MetaField

## PostType
static make(string $slug): self
label(string $singular, string $plural=''): self
public(bool $p=true): self
exclude_from_search(bool $e=true): self
show_in_rest(bool $s=true): self
rest_base(string $base): self
hierarchical(bool $h=true): self
supports(array $features): self     — title|editor|thumbnail|excerpt|comments|revisions|custom-fields
icon(string $dashicon): self
menu_position(int $pos): self
has_archive($archive=true): self
taxonomies(array $slugs): self
capability_type(string $type, bool $map=true): self
args(array $raw): self              — pass any raw register_post_type arg
register(): void · unregister(): void · registered(): bool

## Taxonomy
static make(string $slug): self
label(string $singular, string $plural=''): self
for($post_types): self              — string or array
public(bool $p=true): self
show_in_rest(bool $s=true): self
rest_base(string $base): self
show_admin_column(bool $s=true): self
show_tagcloud(bool $s=true): self
hierarchical(bool $h=true): self
rewrite($rewrite): self
args(array $raw): self
register(): void · unregister(): void · registered(): bool

## MetaField
static post(string $post_type, string $key): self
static term(string $taxonomy, string $key): self
static user(string $key): self
static comment(string $key): self
type(string $t): self      — string|boolean|integer|number|array|object
single(bool $s=true): self
default($v): self
description(string $d): self
sanitize(callable $cb): self
auth_callback(callable $cb): self
show_in_rest($schema=true): self
args(array $raw): self
register(): void · unregister(): void
// Read:  get_post_meta(\$id,'_key',true)
// Write: update_post_meta(\$id,'_key',\$val)`,

"api/content": `# Content — Block · AbstractWidget · Shortcode

## Block
static make(string $name): self     — 'namespace/block-name'
title(string $t): self
category(string $c): self           — text|media|design|widgets|theme|embed
icon(string $i): self               — dashicon or SVG
description(string $d): self
keywords(array $kw): self
attributes(array $attrs): self      — block.json schema: ['key'=>['type'=>'string','default'=>'']]
editor_script(string $handle): self
script(string $handle): self
style(string $handle): self
editor_style(string $handle): self
render(callable $cb): self          — fn(array \$attrs, string \$content): string — dynamic block
to_args(): array
register(): void                    — call inside add_action('init',...)

## AbstractWidget
Extend and implement:
protected string \$widget_title = '';
protected string \$description  = '';
protected abstract output(array \$args, array \$instance): void
protected abstract fields(array \$instance): void
protected sanitize(array \$new, array \$old): array   — optional; default sanitize_text_field all strings
static register(): void             — hooks register_widget() onto widgets_init
final widget/form/update — do not override; implement output/fields/sanitize
id_base auto-derived: RecentPostsWidget → recent_posts_widget

## Shortcode
static make(string $tag): self
defaults(array $defaults): self     — shortcode_atts defaults
render(callable $cb): self          — fn(array \$atts, string \$content=''): string
register(): void · unregister(): void`,

"api/assets": `# Assets — Script · Style

## Common (Asset base)
deps(array $deps): self
version(string $v): self
only_on(callable $condition): self  — e.g. fn() => is_admin()
enqueue(): void · register_asset(): void

## Script (extends Asset)
static make(string $handle, string $src): self
footer(bool $in_footer=true): self
localize(string $object_name, array $data): self   — wp_localize_script

## Style (extends Asset)
static make(string $handle, string $src): self
media(string $media): self   — 'all'|'screen'|'print'

## Example
\`\`\`php
Script::make('my-plugin-app', plugin_dir_url(__FILE__).'dist/app.js')
    ->version('1.0.0')
    ->deps(['jquery'])
    ->footer()
    ->localize('MyPlugin', ['ajax_url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('my_action')])
    ->enqueue();
Style::make('my-plugin-app', plugin_dir_url(__FILE__).'dist/app.css')
    ->version('1.0.0')->enqueue();
\`\`\``,

"api/view-mail": `# View + Mail

## View
static set_base_path(string $path): void
static get_base_path(): string
static make(string $template, string $base_path=''): self
from(string $path): self
with($key, $value=null): self    — key=string or assoc array
render(): string                  — returns HTML
output(): void                    — echoes HTML
// Template file receives all with() data as local variables.
// Call set_base_path() once in provider register().

## Example
\`\`\`php
// Provider register():
View::set_base_path(plugin_dir_path(__FILE__).'templates');
// Controller:
View::make('orders/index')->with('orders',\$orders)->with('title','Orders')->output();
// Template: templates/orders/index.php
// Variables available: \$orders, \$title
\`\`\`

## Mail
static to($email): self      — string or array
subject(string \$s): self
message(string \$m): self    — plain text
html(string \$h): self       — HTML body
template(string \$tpl, array \$data=[]): self   — renders View, sets HTML body
from(string \$email, string \$name=''): self
cc($email): self · bcc(\$email): self
header(string \$header): self
attach(string \$path): self
send(): bool

## Example
\`\`\`php
Mail::to(\$user->user_email)
    ->subject('Order Confirmed')
    ->html('<h1>Thanks!</h1>')
    ->from('shop@example.com','My Shop')
    ->send();
// Template:
Mail::to(\$email)->subject('Welcome')->template('emails/welcome',['user'=>\$user])->send();
\`\`\``,

"api/lifecycle": `# Lifecycle

## Lifecycle
static for(string $plugin_file): self
on_activate(callable $cb): self
on_deactivate(callable $cb): self
on_uninstall(string $class_name): self   — class must have public static uninstall(): void
register(): void

## Example
\`\`\`php
// In main plugin file:
Lifecycle::for(__FILE__)
    ->on_activate(function() use (\$app) {
        \$app->make(Migrator::class)->run();
        flush_rewrite_rules();
    })
    ->on_deactivate(function() use (\$app) {
        \$app->make('scheduler')->unschedule_all();
        flush_rewrite_rules();
    })
    ->on_uninstall(UninstallHandler::class)
    ->register();

// UninstallHandler:
class UninstallHandler {
    public static function uninstall(): void {
        \$app->make(Migrator::class)->rollback_all();
        delete_option('my_plugin_settings');
    }
}
\`\`\``,

"api/logging": `# Logging

## LoggerInterface (PSR-3)
emergency/alert/critical/error/warning/notice/info/debug(string \$msg, array \$ctx=[]): void
log(string \$level, string \$msg, array \$ctx=[]): void
// Context supports {placeholder} interpolation: \$logger->info('User {id}',['id'=>5])

## Setup
\`\`\`php
define('WPFLINT_LOG_CHANNEL','my-plugin');
define('WPFLINT_LOG_LEVEL','debug');
\$app->register(WPFlint\\Logging\\LoggingServiceProvider::class);
\`\`\`

## Helpers (global, no import)
wpflint_log(string \$msg, array \$ctx=[], string \$level='debug'): void
wpflint_dd(...\$values): void   — dump and die (dev only)
// Resolve: \$logger = \$app->make(WPFlint\\Logging\\LoggerInterface::class);`,

"api/queue": `# Queue — Job · QueueManager

## Job (abstract)
protected string \$queue         = 'default';
protected int    \$max_attempts  = 3;
protected int    \$delay_seconds = 0;
abstract handle(): void
failed(\\Throwable \$exception): void   — called after all retries exhausted
on_queue(string \$queue): self
delay(int \$seconds): self
tries(int \$n): self
get_queue(): string · get_max_attempts(): int · get_delay(): int

## QueueManager
dispatch(Job \$job): int
pending_count(string \$queue='default'): int
total_count(string \$queue='default'): int
clear(string \$queue='default'): int
get_failed(?string \$queue=null): array   — list failed job records
retry(int \$failed_id): bool             — requeue a failed job by ID

## Dispatch
wpflint_dispatch(new SendWelcomeEmail(\$user_id));   — global helper
// or: \$app->make(QueueManager::class)->dispatch(new SendWelcomeEmail(\$user_id));`,

"api/scheduling": `# Scheduling — Scheduler · ScheduleEvent

## Scheduler
call(callable \$cb): ScheduleEvent
job(string \$job_class): ScheduleEvent
register(): void        — registers all events with WP Cron
unschedule_all(): void  — call on deactivation

## ScheduleEvent
name(string \$hook): self       — unique cron hook name (required)
description(string \$d): self
every_minute/every_five_minutes/every_ten_minutes/every_fifteen_minutes(): self
every_thirty_minutes/hourly(): self
every_hours(int \$n): self
twice_daily/daily/weekly/monthly(): self
cron(string \$interval): self   — WP cron interval key
skip(): self                     — disable without removing
register(): void · unschedule(): void

## Wire-up
\`\`\`php
// ServiceProvider boot():
\$s = \$this->app->make('scheduler');
\$s->call(fn() => clean_expired_transients())->name('myplugin_cleanup')->daily();
\$s->job(GenerateReportJob::class)->name('myplugin_report')->weekly();
\$s->register();
// Helper:
wpflint_schedule(fn() => sync_inventory())->name('myplugin_sync')->every_thirty_minutes();
\`\`\``,

"api/facades": `# Facades

## Facade base
Extend Facade, implement get_facade_accessor(): string → return container binding key.
Static calls forwarded to the resolved instance via __callStatic.

## Built-in facades (use WPFlint\\Facades\\*)
Cache   → CacheManager      Cache::remember('k',3600,fn()=>...)
Config  → Config\\Repository Config::get('app.name')
Event   → Events\\Dispatcher Event::fire(new MyEvent())

## Custom
\`\`\`php
// 1. Register binding:
\$app->singleton('orders', fn(\$a) => new OrderService(\$a->make(OrderRepository::class)));
// 2. Create facade:
class Orders extends Facade {
    protected static function get_facade_accessor(): string { return 'orders'; }
}
// 3. Use: Orders::create(['status'=>'pending']);
\`\`\``,

"api/validation": `# Validation — Validator · RuleInterface

## Validator
static make(array \$data, array \$rules, array \$msgs=[], array \$attrs=[]): ValidationResult
static extend(string \$name, \$callback): void   — register custom rule

## ValidationResult
fails(): bool · passes(): bool
errors(): array   — keyed by field
validated(): array

## Built-in rules (pipe-delimited)
required · required_if:field,val · required_unless:field,val
nullable · sometimes · bail
string · integer · numeric · boolean · array · json
min:n · max:n · between:min,max · size:n · digits:n
email · url · ip · uuid
regex:pattern
alpha · alpha_num · alpha_dash
in:a,b,c · not_in:a,b,c
same:field · different:field · confirmed

## Custom rule via RuleInterface
passes(\$value): bool
message(): string   — use :attribute placeholder

## Extend with closure
\`\`\`php
Validator::extend('uppercase', function(\$field, \$value, \$fail) {
    if (\$value !== strtoupper(\$value)) \$fail("The {\$field} must be uppercase.");
});
\`\`\``,

"api/config": `# Config\\Repository

get(string \$key, \$default=null): mixed    — dot notation 'app.name'
set(string \$key, \$value): void
has(string \$key): bool
all(): array
push(string \$key, \$value): void
static env(string \$key, \$default=null): mixed

## Config files: config/*.php, return array.
## Access: wpflint_config('app.name') helper or Config::get('app.name') facade.
## Loaded from base_path/config/ automatically by ConfigServiceProvider.`,

"api/wordpress": `# WordPress Models (ORM-first — extend WPFlint\\Database\\ORM\\Model)

All models use \$wpdb->prefix automatically. Use like any custom Model.

## Post  (table: posts)
Scopes: published() · draft() · type(\$type) · status(\$status)
Relations: author(): BelongsTo(User) · meta(): HasMany(PostMeta) · comments(): HasMany(Comment) · parent_post(): BelongsTo(Post)
\`\`\`php
Post::published()->type('product')->order_by('post_date','desc')->get();
Post::find(5)->meta()->where('meta_key','_price')->first();
\`\`\`

## User  (table: users)
Scopes: role(\$role)
Relations: posts(): HasMany(Post) · meta(): HasMany(UserMeta) · comments(): HasMany(Comment)
\`\`\`php
User::role('administrator')->get();
User::find(get_current_user_id())->meta()->where('meta_key','billing_address')->first();
\`\`\`

## Option  (table: options)
Scopes: autoloaded() · not_autoloaded()
\`\`\`php
// Prefer get_option() for single values.
// Use model for bulk queries:
Option::autoloaded()->where('option_name','LIKE','my_plugin_%')->get();
\`\`\`

## Term  (table: terms)
Relations: taxonomies(): HasMany(TermTaxonomy) · taxonomy(): HasOne(TermTaxonomy)

## Comment  (table: comments)
Scopes: approved() · pending() · spam() · type(\$type)
Relations: post(): BelongsTo(Post) · user(): BelongsTo(User) · meta(): HasMany(CommentMeta)

## PostMeta / UserMeta / CommentMeta  (meta tables)
Standard Model CRUD on wp_postmeta, wp_usermeta, wp_commentmeta.

## TermTaxonomy / TermRelationship  (taxonomy tables)
Standard Model access to wp_term_taxonomy, wp_term_relationships.

## Gotcha: \$table must be unprefixed — the model prepends \$wpdb->prefix automatically.`,

"api/console": `# WP-CLI Commands (src/Console/ — dev only, excluded from dist)

## Command base
Extend Command, implement __invoke(array \$args, array \$assoc_args): void
info/success/warning/error(string \$msg): void
line(string \$msg): void
confirm(string \$question): bool
ask(string \$question): string

## Built-in commands (wp wpflint ...)
make:migration  <Name>               — generates migration stub
make:model      <Name> [--migration] — generates model (+ optional migration)
make:provider   <Name>
make:controller <Name> [--rest]
make:middleware <Name>
make:request    <Name>
make:event      <Name>
make:listener   <Name> [--event=EventClass]
make:facade     <Name>
make:rule       <Name>
make:command    <Name>
make:helper     <Name>
migrate         [--rollback] [--fresh] [--status]
cache:clear     [--tag=tagname]

## Register custom command
\`\`\`php
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('my-plugin sync', App\\Console\\SyncCommand::class);
}
\`\`\``,

"patterns": `# Key Patterns

## Full AJAX flow
\`\`\`php
// 1. Request (app/Http/Requests/SaveOrderRequest.php)
class SaveOrderRequest extends Request {
    public function authorize(): bool { return current_user_can('edit_posts'); }
    public function rules(): array { return ['status'=>'required|in:pending,paid','total'=>'required|numeric|min:0']; }
    public function sanitize(): array { return ['status'=>'sanitize_text_field','total'=>'floatval']; }
}
// 2. Controller (app/Http/Controllers/OrderController.php)
class OrderController extends Controller {
    public function __construct(private OrderService \$orders) {}
    public function store(SaveOrderRequest \$req): Response {
        \$order = \$this->orders->create(\$req->validated());
        return Response::json(\$order->to_array(), 201);
    }
}
// 3. Route (ServiceProvider boot()):
\$router->ajax('my-plugin/save-order',[OrderController::class,'store'])
       ->middleware(['nonce:save_order','can:edit_posts']);
\$router->boot();
// 4. JS:
// jQuery.post(ajaxurl,{action:'my-plugin/save-order',nonce:MyPlugin.nonce,...},cb);
\`\`\`

## Event-driven flow
\`\`\`php
class OrderPlaced extends Event { public function __construct(public Order \$order) {} }
class SendConfirmation { public function handle(OrderPlaced \$e): void { /* mail */ } }
Event::listen(OrderPlaced::class, SendConfirmation::class);
Event::fire(new OrderPlaced(\$order)); // or wpflint_event(new OrderPlaced(\$order))
\`\`\`

## Cache-aside
\`\`\`php
\$orders = Cache::tags(['orders','user:'.\$uid])->remember('user_orders_'.\$uid, 3600, fn() =>
    Order::where('user_id',\$uid)->get()
);
// Invalidate on save:
Cache::tags('orders')->flush();
\`\`\`

## Deferred provider
\`\`\`php
class ReportServiceProvider extends ServiceProvider {
    public bool \$defer = true;
    public function register(): void { \$this->app->singleton(ReportService::class, fn(\$a) => new ReportService()); }
    public function provides(): array { return [ReportService::class]; }
}
\`\`\``,

"constraints": `# PHP 7.4 Constraints — full list

## FORBIDDEN
named arguments:       foo(name: 'val')          → foo('val')
enums:                 enum Status { case A; }    → use class constants
match expression:      match(\$x) { 1 => 'a' }   → use switch/ternary
union types:           int|string \$x             → @param int|string docblock
nullsafe operator:     \$obj?->method()           → \$obj ? \$obj->method() : null
readonly properties:   readonly string \$name     → regular property
str_contains():        str_contains(\$h,\$n)       → strpos(\$h,\$n)!==false
str_starts_with():     str_starts_with(\$h,\$n)   → strpos(\$h,\$n)===0
str_ends_with():       str_ends_with(\$h,\$n)     → substr(\$h,-strlen(\$n))===\$n
fibers:                (PHP 8.1) — not available
callable property type: protected callable \$fn   → /** @var callable */ protected \$fn

## ALLOWED
Typed properties:     protected string \$name = '';
Arrow functions:      \$fn = fn(\$x) => \$x * 2;
Null coalescing:      \$val = \$arr['key'] ?? 'default';
Spread operator:      fn(...\$args) — OK in function calls
Declare strict_types: always add declare(strict_types=1);

## WordPress Security (no exceptions)
\$wpdb->prepare(\$sql, ...\$args) — every query with variable input
sanitize_text_field()   for text input
sanitize_textarea_field() for multiline
absint() / intval()     for integers
sanitize_email()        for email
esc_url()               for URLs
esc_html() / esc_attr() / esc_js() on all output
wp_kses_post()          for trusted HTML
check_admin_referer(\$action) or check_ajax_referer(\$action) in AJAX/forms
current_user_can(\$cap) before every privileged operation`,

"directory": `# Class Directory

WPFlint\\Application                    — singleton bootstrap, extends Container
WPFlint\\Container\\Container            — IoC container, PSR-11
WPFlint\\Providers\\ServiceProvider      — abstract base, register/boot/provides
WPFlint\\Http\\Router                    — ajax() rest() alias_middleware() boot()
WPFlint\\Http\\RestRouter                — get/post/put/patch/delete() — used inside rest() closure
WPFlint\\Http\\Route                     — returned by ajax(); middleware() nopriv()
WPFlint\\Http\\Controller                — base AJAX controller
WPFlint\\Http\\RestController            — base REST controller; respond()
WPFlint\\Http\\Request                   — capture() input() all() validated() authorize() rules() sanitize()
WPFlint\\Http\\Response                  — json() error() no_content()
WPFlint\\Http\\RestAuth                  — capability() logged_in() public_access() all_of() any_of() namespace()
WPFlint\\Http\\Middleware\\MiddlewareInterface — handle(Request, Closure): mixed
WPFlint\\Http\\Middleware\\VerifyNonce    — built-in: nonce:{action}
WPFlint\\Http\\Middleware\\CheckCapability — built-in: can:{cap}
WPFlint\\Http\\Middleware\\ThrottleRequests — built-in: throttle:{max},{mins}
WPFlint\\Database\\Migrations\\Migration  — abstract up()/down(), \$this->schema()
WPFlint\\Database\\Migrations\\Migrator   — run() rollback_all()
WPFlint\\Database\\Schema\\Blueprint      — big_increments/string/integer/etc, timestamps()
WPFlint\\Database\\Schema\\Schema         — create/drop/drop_if_exists()
WPFlint\\Database\\ORM\\Model             — Active Record: find/create/save/delete/destroy/cached/update_or_create/first_or_new
WPFlint\\Database\\ORM\\QueryBuilder      — raw query builder: select/where/update/delete/insert/increment/paginate/chunk
WPFlint\\Cache\\CacheManager             — get/put/remember/tags/fresh/flush
WPFlint\\Cache\\TaggedCache              — scoped cache by tag
WPFlint\\Events\\Dispatcher              — listen/fire/forget
WPFlint\\Events\\Event                   — base event class
WPFlint\\Admin\\AdminPage               — make() title() capability() render() register()
WPFlint\\Admin\\MetaBox                 — make() screen() field() register()
WPFlint\\Admin\\MetaBoxField            — type() description() default_value() sanitize_with()
WPFlint\\Admin\\Notice                  — success/error/warning/info() dismissible() flash() persistent()
WPFlint\\Settings\\Settings             — make() page() section() register()
WPFlint\\Settings\\Section              — field(): Field
WPFlint\\Settings\\Field                — type() default() required() options()
WPFlint\\Registration\\PostType         — make() label() supports() show_in_rest() register()
WPFlint\\Registration\\Taxonomy         — make() label() for() hierarchical() register()
WPFlint\\Registration\\MetaField        — post/term/user/comment() type() single() show_in_rest() register()
WPFlint\\Blocks\\Block                  — make() title() attributes() render() register()
WPFlint\\Widgets\\AbstractWidget        — output() fields() sanitize() static register()
WPFlint\\Shortcodes\\Shortcode          — make() defaults() render() register()
WPFlint\\Assets\\Script                 — make() deps() footer() localize() enqueue()
WPFlint\\Assets\\Style                  — make() deps() media() enqueue()
WPFlint\\View\\View                     — set_base_path() make() with() render() output()
WPFlint\\Mail\\Mail                     — to() subject() html() template() from() send()
WPFlint\\Lifecycle\\Lifecycle           — for() on_activate() on_deactivate() on_uninstall() register()
WPFlint\\Logging\\LoggerInterface       — PSR-3: debug/info/notice/warning/error/critical/alert/emergency()
WPFlint\\Queue\\Job                     — abstract handle(); failed(); on_queue(); delay(); tries()
WPFlint\\Queue\\QueueManager            — dispatch() pending_count() clear() get_failed() retry()
WPFlint\\Scheduling\\Scheduler          — call() job() register() unschedule_all()
WPFlint\\Scheduling\\ScheduleEvent      — name() daily() weekly() hourly() every_hours() cron()
WPFlint\\Facades\\Facade                — base; get_facade_accessor(): string
WPFlint\\Facades\\Cache                 — → CacheManager
WPFlint\\Facades\\Config                — → Config\\Repository
WPFlint\\Facades\\Event                 — → Events\\Dispatcher
WPFlint\\Config\\Repository             — get/set/has/all() dot-notation
WPFlint\\Validation\\Validator          — static make() extend()
WPFlint\\Validation\\Rules\\RuleInterface — passes() message()
WPFlint\\WordPress\\Post/User/Option/Term/Comment/PostMeta/UserMeta/CommentMeta/TermTaxonomy/TermRelationship — WordPress ORM models
Helpers: wpflint_app() wpflint_config() wpflint_env() wpflint_event() wpflint_cache()
         wpflint_schedule() wpflint_dispatch() wpflint_log() wpflint_dd()`

};

// ─── HELPERS ──────────────────────────────────────────────────────────────────
function snake(v) {
  return v.replace(/([a-z])([A-Z])/g,'$1_$2').replace(/([A-Z]+)([A-Z][a-z])/g,'$1_$2').toLowerCase();
}
function tableFromName(n) { return snake(n.replace(/^Create/,'').replace(/Table$/,'')); }
function timestamp() {
  const d=new Date(),p=(n,w=2)=>String(n).padStart(w,'0');
  return `${d.getUTCFullYear()}_${p(d.getUTCMonth()+1)}_${p(d.getUTCDate())}_${p(d.getUTCHours())}${p(d.getUTCMinutes())}${p(d.getUTCSeconds())}`;
}

// ─── PHP GENERATORS ───────────────────────────────────────────────────────────
function php(lines) { return lines.join('\n'); }

function genProvider(name) {
  return php([
    `<?php`,`// file: app/Providers/${name}.php`,`// wire: $app->register( ${name}::class );`,
    `declare(strict_types=1);`,`namespace ${ns('Providers')};`,
    `use WPFlint\\Providers\\ServiceProvider;`,``,
    `class ${name} extends ServiceProvider {`,
    `\tpublic function register(): void {}`,
    `\tpublic function boot(): void {}`,`}`,
  ]);
}

function genMigration(name) {
  const table = tableFromName(name);
  return php([
    `<?php`,`// file: database/migrations/${timestamp()}_${snake(name)}.php`,
    `declare(strict_types=1);`,
    `use WPFlint\\Database\\Migrations\\Migration;`,
    `use WPFlint\\Database\\Schema\\Blueprint;`,``,
    `class ${name} extends Migration {`,
    `\tpublic function up(): void {`,
    `\t\t$this->schema()->create( '${table}', function ( Blueprint $t ) {`,
    `\t\t\t$t->big_increments( 'id' );`,
    `\t\t\t$t->timestamps();`,
    `\t\t} );`,`\t}`,
    `\tpublic function down(): void { $this->schema()->drop( '${table}' ); }`,`}`,
  ]);
}

function genModel(name, fillable=[], casts={}, withMigration=false) {
  const table  = snake(name)+'s';
  const fill   = fillable.length ? `array( '${fillable.join("', '")}' )` : `array()`;
  const castLines = Object.keys(casts).length
    ? `array(\n\t\t'${Object.entries(casts).map(([k,v])=>`${k}' => '${v}`).join("',\n\t\t'")}',\n\t)`
    : `array()`;
  let out = php([
    `<?php`,`// file: app/Models/${name}.php`,
    `declare(strict_types=1);`,`namespace ${ns('Models')};`,
    `use WPFlint\\Database\\ORM\\Model;`,``,
    `class ${name} extends Model {`,
    `\tprotected static string $table    = '${table}';`,
    `\tprotected array         $fillable = ${fill};`,
    `\tprotected array         $casts    = ${castLines};`,`}`,
  ]);
  if (withMigration) {
    out += '\n\n---\n\n' + genMigration(`Create${name}sTable`);
  }
  return out;
}

function genController(name, rest=false) {
  const base = snake(name.replace(/Controller$/, ''));
  if (!rest) return php([
    `<?php`,`// file: app/Http/Controllers/${name}.php`,
    `declare(strict_types=1);`,`namespace ${ns('Http\\Controllers')};`,
    `use WPFlint\\Http\\Controller;`,``,
    `class ${name} extends Controller {`,
    `\tpublic function __construct() {}`,`}`,
  ]);
  return php([
    `<?php`,`// file: app/Http/Controllers/${name}.php`,
    `declare(strict_types=1);`,`namespace ${ns('Http\\Controllers')};`,
    `use WPFlint\\Http\\RestController;`,`use WPFlint\\Http\\RestAuth;`,``,
    `class ${name} extends RestController {`,
    `\tprotected string $namespace = '${S.slug}/v1';`,
    `\tprotected string $rest_base  = '${base}';`,``,
    `\tpublic function index( \\WP_REST_Request $request ): \\WP_REST_Response {`,
    `\t\treturn $this->respond( array() );`,`\t}`,``,
    `\tpublic function store( \\WP_REST_Request $request ): \\WP_REST_Response {`,
    `\t\treturn $this->respond( array(), 201 );`,`\t}`,``,
    `\tpublic function get_items_permissions_check( $request ): bool {`,
    `\t\treturn current_user_can( 'read' );`,`\t}`,``,
    `\tpublic function create_item_permissions_check( $request ): bool {`,
    `\t\treturn current_user_can( 'edit_posts' );`,`\t}`,`}`,
  ]);
}

function genMiddleware(name) {
  return php([
    `<?php`,`// file: app/Http/Middleware/${name}.php`,
    `// wire: $router->alias_middleware('${snake(name)}', ${name}::class);`,
    `declare(strict_types=1);`,`namespace ${ns('Http\\Middleware')};`,
    `use Closure;`,`use WPFlint\\Http\\Request;`,`use WPFlint\\Http\\Middleware\\MiddlewareInterface;`,``,
    `class ${name} implements MiddlewareInterface {`,
    `\tpublic function handle( Request $request, Closure $next ) {`,
    `\t\treturn $next( $request );`,`\t}`,`}`,
  ]);
}

function genRequest(name, cap='', rules={}) {
  const auth   = cap ? `return current_user_can( '${cap}' );` : `return false;`;
  const rlines = Object.keys(rules).length
    ? `array(\n\t\t\t'${Object.entries(rules).map(([k,v])=>`${k}' => '${v}`).join("',\n\t\t\t'")}',\n\t\t)`
    : `array()`;
  return php([
    `<?php`,`// file: app/Http/Requests/${name}.php`,
    `declare(strict_types=1);`,`namespace ${ns('Http\\Requests')};`,
    `use WPFlint\\Http\\Request;`,``,
    `class ${name} extends Request {`,
    `\tpublic function authorize(): bool { ${auth} }`,``,
    `\tpublic function rules(): array {`,`\t\treturn ${rlines};`,`\t}`,``,
    `\tpublic function sanitize(): array {`,`\t\treturn array();`,`\t}`,`}`,
  ]);
}

function genEvent(name, props=[]) {
  const params = props.map(p=>`$${p}`).join(', ');
  const assigns = props.map(p=>`\t\t$this->${p} = $${p};`).join('\n');
  const pubProps = props.map(p=>`\t/** @var mixed */ public $${p};`).join('\n');
  return php([
    `<?php`,`// file: app/Events/${name}.php`,
    `declare(strict_types=1);`,`namespace ${ns('Events')};`,
    `use WPFlint\\Events\\Event;`,``,
    `class ${name} extends Event {`,
    ...(props.length ? [pubProps,`\tpublic function __construct( ${params} ) {`,assigns,`\t}`] : [`\tpublic function __construct() {}`]),
    `}`,
  ]);
}

function genListener(name, eventClass='') {
  const hint = eventClass ? `${eventClass.split('\\').pop()} $event` : '$event';
  const use_  = eventClass ? `use ${eventClass};` : '';
  return php([
    `<?php`,`// file: app/Listeners/${name}.php`,
    ...(eventClass ? [`// wire: Event::listen( ${eventClass}::class, ${name}::class );`] : []),
    `declare(strict_types=1);`,`namespace ${ns('Listeners')};`,
    ...(use_ ? [use_] : []),``,
    `class ${name} {`,
    `\tpublic function handle( ${hint} ): void {}`,`}`,
  ]);
}

function genFacade(name, accessor='') {
  return php([
    `<?php`,`// file: app/Facades/${name}.php`,
    `declare(strict_types=1);`,`namespace ${ns('Facades')};`,
    `use WPFlint\\Facades\\Facade;`,``,
    `class ${name} extends Facade {`,
    `\tprotected static function get_facade_accessor(): string { return '${accessor}'; }`,`}`,
  ]);
}

function genRule(name, message='The :attribute field is invalid.') {
  return php([
    `<?php`,`// file: app/Rules/${name}.php`,
    `declare(strict_types=1);`,`namespace ${ns('Rules')};`,
    `use WPFlint\\Validation\\Rules\\RuleInterface;`,``,
    `class ${name} implements RuleInterface {`,
    `\tpublic function passes( $value ): bool { return true; }`,
    `\tpublic function message(): string { return __( '${message}', '${td()}' ); }`,`}`,
  ]);
}

function genCommand(name) {
  const cmd = snake(name.replace(/Command$/,''));
  return php([
    `<?php`,`// file: app/Console/${name}.php`,
    `// wire: WP_CLI::add_command( '${S.slug} ${cmd}', ${name}::class );`,
    `declare(strict_types=1);`,`namespace ${ns('Console')};`,
    `use WPFlint\\Console\\Command;`,``,
    `/** ## EXAMPLES\n *     wp ${S.slug} ${cmd} */`,
    `class ${name} extends Command {`,
    `\tpublic function __invoke( array $args, array $assoc_args ): void {`,
    `\t\t$this->success( __( 'Done.', '${td()}' ) );`,`\t}`,`}`,
  ]);
}

function genJob(name, queue='default', tries=3) {
  return php([
    `<?php`,`// file: app/Jobs/${name}.php`,
    `// dispatch: wpflint_dispatch( new ${name}() );`,
    `declare(strict_types=1);`,`namespace ${ns('Jobs')};`,
    `use WPFlint\\Queue\\Job;`,``,
    `class ${name} extends Job {`,
    `\tprotected string $queue         = '${queue}';`,
    `\tprotected int    $max_attempts  = ${tries};`,
    `\tprotected int    $delay_seconds = 0;`,``,
    `\tpublic function handle(): void {}`,
    `\tpublic function failed( \\Throwable $exception ): void {}`,`}`,
  ]);
}

// ─── ADMIN GENERATORS ────────────────────────────────────────────────────────
function genAdminPage(slug, title, parent, cap, icon) {
  const regLine = parent
    ? `->register_as_submenu( '${parent}' );`
    : `->register();`;
  const iconLine = icon ? `\n\t->icon( '${icon}' )` : '';
  return php([
    `<?php`,`// wire: call from ServiceProvider boot() inside add_action( 'admin_menu', ... )`,
    `use WPFlint\\Admin\\AdminPage;`,``,
    `AdminPage::make( '${title}', '${slug}' )`,
    `\t->capability( '${cap}' )${iconLine}`,
    `\t->render( function () {`,
    `\t\t?>`,`\t\t<div class="wrap">`,
    `\t\t\t<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>`,
    `\t\t</div>`,`\t\t<?php`,`\t} )`,
    `\t${regLine}`,
  ]);
}

function genSettings(optGroup, optName, page, sections, fields) {
  const secBlock = sections.map(sec => {
    const secFields = fields.filter(f=>!f.section||f.section===sec.id);
    const fieldLines = secFields.map(f=>`\t\t\$s->field( '${f.name}', '${f.label||f.name}' )->type( '${f.type||'text'}' )${f.required?'->required()':''};`).join('\n');
    return `\t->section( '${sec.id}', '${sec.title}', function ( \\WPFlint\\Settings\\Section \$s ) {\n${fieldLines}\n\t} )`;
  }).join('\n');
  return php([
    `<?php`,`// wire: call from ServiceProvider boot() inside add_action( 'admin_init', ... )`,
    `use WPFlint\\Settings\\Settings;`,``,
    `Settings::make( '${optGroup}', '${optName}' )`,
    `\t->page( '${page}' )`,
    secBlock,
    `\t->register();`,``,
    `// Read: $val = get_option( '${optName}', array() );`,
    `// Form: settings_fields('${optGroup}'); do_settings_sections('${page}'); submit_button();`,
  ]);
}

function genMetaBox(id, title, screen, fields) {
  const fieldLines = fields.map(f => {
    const desc = f.description ? `->description( '${f.description}' )` : '';
    return `\t$box->field( '${f.id}', '${f.label}' )->type( '${f.type||'text'}' )${desc};`;
  }).join('\n') || `\t$box->field( '_example', 'Example' )->type( 'text' );`;
  return php([
    `<?php`,`// wire: call from ServiceProvider boot()`,
    `use WPFlint\\Admin\\MetaBox;`,``,
    `add_action( 'add_meta_boxes', function () {`,
    `\t$box = MetaBox::make( '${id}', '${title}' )`,
    `\t\t->screen( '${screen}' )->context( 'normal' )->priority( 'high' );`,
    fieldLines,
    `\t$box->register();`,`} );`,
    `// Read: get_post_meta( $post->ID, '_key', true );`,
  ]);
}

function genNotice(message, type, dismissible) {
  const d = dismissible ? '->dismissible()' : '';
  return php([
    `<?php`,`use WPFlint\\Admin\\Notice;`,``,
    `// Inline (inside admin callback):`,
    `Notice::${type}( '${message}' )${d}->render();`,``,
    `// Or flash (shows once on next page load):`,
    `// Notice::${type}( '${message}' )${d}->flash();`,
    `// add_action( 'admin_notices', [ Notice::class, 'display_flash' ] );`,
  ]);
}

// ─── CONTENT GENERATORS ──────────────────────────────────────────────────────
function genPostType(slug, singular, plural, supports, icon, pub, hier, rest) {
  const p      = plural || singular+'s';
  const supL   = supports&&supports.length ? `\n\t->supports( array( '${supports.join("', '")}' ) )` : '';
  const iconL  = icon  ? `\n\t->icon( '${icon}' )` : '';
  const restL  = rest  ? `\n\t->show_in_rest()` : '';
  const pubL   = pub   ? `\n\t->public()` : '';
  const hierL  = hier  ? `\n\t->hierarchical()` : '';
  return php([
    `<?php`,`// wire: call from ServiceProvider boot() inside add_action( 'init', ... )`,
    `use WPFlint\\Registration\\PostType;`,``,
    `PostType::make( '${slug}' )`,
    `\t->label( '${singular}', '${p}' )${pubL}${supL}${hierL}${iconL}${restL}`,
    `\t->register();`,
  ]);
}

function genTaxonomy(slug, singular, plural, postTypes, hier, rest) {
  const p     = plural || singular+'s';
  const pts   = postTypes.map(t=>`'${t}'`).join(', ');
  const hierL = hier ? `\n\t->hierarchical()` : '';
  const restL = rest ? `\n\t->show_in_rest()` : '';
  return php([
    `<?php`,`// wire: call from ServiceProvider boot() inside add_action( 'init', ... )`,
    `use WPFlint\\Registration\\Taxonomy;`,``,
    `Taxonomy::make( '${slug}' )`,
    `\t->label( '${singular}', '${p}' )`,
    `\t->for( array( ${pts} ) )${hierL}${restL}`,
    `\t->register();`,
  ]);
}

function genMetaField(objectType, subtype, key, type, single, rest) {
  const factory = objectType==='post' ? `MetaField::post( '${subtype}', '${key}' )`
                : objectType==='term' ? `MetaField::term( '${subtype}', '${key}' )`
                : objectType==='user' ? `MetaField::user( '${key}' )`
                : `MetaField::comment( '${key}' )`;
  const restL   = rest   ? `\n\t->show_in_rest()` : '';
  const singleL = single ? `\n\t->single()` : '';
  return php([
    `<?php`,`// wire: call from ServiceProvider boot() inside add_action( 'init', ... )`,
    `use WPFlint\\Registration\\MetaField;`,``,
    `${factory}`,
    `\t->type( '${type}' )${singleL}${restL}`,
    `\t->register();`,
    `// Read:  get_${objectType}_meta( $id, '${key}', true );`,
    `// Write: update_${objectType}_meta( $id, '${key}', $value );`,
  ]);
}

function genShortcode(tag, atts) {
  const defaults = atts.map(a=>`'${a}' => ''`).join(', ');
  return php([
    `<?php`,`// wire: call from ServiceProvider boot()`,
    `use WPFlint\\Shortcodes\\Shortcode;`,``,
    `Shortcode::make( '${tag}' )`,
    `\t->defaults( array( ${defaults} ) )`,
    `\t->render( function ( array $atts, string $content = '' ): string {`,
    `\t\treturn '<div class="${tag.replace(/_/g,'-')}">' . wp_kses_post( $content ) . '</div>';`,
    `\t} )`,`\t->register();`,
    `// Usage: [${tag}${atts.map(a=>` ${a}=""`).join('')}]content[/${tag}]`,
  ]);
}

function genBlock(name, title, category, dynamic) {
  const blockClass = name.split('/')[1]||name;
  const renderSection = dynamic
    ? [`\t->render( function ( array $attrs, string $content ): string {`,
       `\t\treturn '<div class="${blockClass}"><h2>' . esc_html( $attrs['heading'] ?? '' ) . '</h2>' . wp_kses_post( $content ) . '</div>';`,
       `\t} )`]
    : [`\t// Static block: editor_script handles rendering. No ->render() needed.`];
  return php([
    `<?php`,`// wire: call inside add_action( 'init', ... )`,
    `use WPFlint\\Blocks\\Block;`,``,
    `add_action( 'init', function () {`,
    `\tBlock::make( '${name}' )`,
    `\t\t->title( '${title}' )`,
    `\t\t->category( '${category}' )`,
    `\t\t->editor_script( '${S.slug}-blocks' )`,
    `\t\t->attributes( array(`,
    `\t\t\t'heading' => array( 'type' => 'string', 'default' => '' ),`,
    `\t\t) )`,
    ...renderSection.map(l=>'\t'+l),
    `\t\t->register();`,`} );`,
  ]);
}

function genWidget(name, title, description) {
  return php([
    `<?php`,`// file: app/Widgets/${name}.php`,
    `// wire: ${name}::register();  (in ServiceProvider boot())`,
    `declare(strict_types=1);`,`namespace ${ns('Widgets')};`,
    `use WPFlint\\Widgets\\AbstractWidget;`,``,
    `class ${name} extends AbstractWidget {`,
    `\tprotected string $widget_title = '${title}';`,
    `\tprotected string $description  = '${description}';`,``,
    `\tprotected function output( array $args, array $instance ): void {`,
    `\t\t// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`,
    `\t\techo $args['before_widget'];`,
    `\t\t// your widget output here`,
    `\t\t// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`,
    `\t\techo $args['after_widget'];`,`\t}`,``,
    `\tprotected function fields( array $instance ): void {`,
    `\t\t$title = $instance['title'] ?? '';`,
    `\t\t?><p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">`,
    `\t\t<?php esc_html_e( 'Title', '${td()}' ); ?></label>`,
    `\t\t<input class="widefat" type="text"`,
    `\t\t\tid="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"`,
    `\t\t\tname="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"`,
    `\t\t\tvalue="<?php echo esc_attr( $title ); ?>"></p><?php`,`\t}`,``,
    `\tprotected function sanitize( array $new, array $old ): array {`,
    `\t\treturn array( 'title' => sanitize_text_field( $new['title'] ?? '' ) );`,`\t}`,`}`,
  ]);
}

// ─── HTTP GENERATORS ─────────────────────────────────────────────────────────
function genAjaxRoute(action, controller, method, middleware, nopriv) {
  const mw      = middleware.length ? `\n\t->middleware( array( '${middleware.join("', '")}' ) )` : '';
  const npLine  = nopriv ? `\n\t->nopriv()` : '';
  return php([
    `<?php`,`// wire: in ServiceProvider boot() — call $router->boot() once after all routes`,
    `use WPFlint\\Http\\Router;`,``,
    `$router = $this->app->make( Router::class );`,
    `$router->ajax( '${action}', array( ${controller}::class, '${method}' ) )${npLine}${mw};`,
    `// JS: jQuery.post(ajaxurl,{action:'${action}',nonce:wpData.nonce,...},cb);`,
  ]);
}

function genRestRoutes(namespace, version, routes) {
  const routeLines = routes.map(r=>{
    const perm = r.permission==='public'      ? `RestAuth::public_access()`
               : r.permission==='manage_options' ? `RestAuth::capability( 'manage_options' )`
               : `RestAuth::logged_in()`;
    return `\t$r->${r.method||'get'}( '${r.path}', array( ${r.controller||'MyController'}::class, '${r.action||'index'}' ), ${perm} );`;
  }).join('\n');
  return php([
    `<?php`,`// wire: in ServiceProvider boot()`,
    `use WPFlint\\Http\\Router;`,`use WPFlint\\Http\\RestAuth;`,``,
    `$router = $this->app->make( Router::class );`,
    `$router->rest( RestAuth::namespace( '${namespace}', ${version} ), function ( $r ) {`,
    routeLines,`} );`,`$router->boot();`,
  ]);
}

// ─── SYSTEM GENERATORS ───────────────────────────────────────────────────────
function genLifecycle(hooks) {
  const lines = [];
  lines.push(`<?php`,`use WPFlint\\Lifecycle\\Lifecycle;`,``,`Lifecycle::for( __FILE__ )`);
  if(hooks.includes('activation'))   lines.push(`\t->on_activate( function () use ( $app ) {\n\t\t$app->make( Migrator::class )->run();\n\t\tflush_rewrite_rules();\n\t} )`);
  if(hooks.includes('deactivation')) lines.push(`\t->on_deactivate( function () use ( $app ) {\n\t\t$app->make( 'scheduler' )->unschedule_all();\n\t\tflush_rewrite_rules();\n\t} )`);
  if(hooks.includes('uninstall'))    lines.push(`\t->on_uninstall( UninstallHandler::class ) // class needs public static uninstall(): void`);
  lines.push(`\t->register();`);
  return lines.join('\n');
}

function genView(viewName, variables) {
  const withLines = variables.map(v=>`\t->with( '${v}', \$${v} )`).join('\n');
  const varComments = variables.map(v=>`// \$${v} available`).join('\n');
  return php([
    `<?php`,`use WPFlint\\View\\View;`,``,
    `// Register once in ServiceProvider register():`,
    `// View::set_base_path( plugin_dir_path( __FILE__ ) . 'templates' );`,``,
    `View::make( '${viewName}' )`,
    ...(withLines ? [withLines] : []),
    `\t->output();`,``,
    `/* ----- templates/${viewName}.php -----`,
    `<?php defined( 'ABSPATH' ) || exit; ?>`,
    varComments,`*/`,
  ]);
}

function genMail(to, subject, bodyType, viewName) {
  const body = bodyType==='view'
    ? `\t->template( '${viewName||'emails/message'}', array( 'data' => \$data ) )`
    : `\t->html( '<h1>${subject}</h1>' )`;
  return php([
    `<?php`,`use WPFlint\\Mail\\Mail;`,``,
    `Mail::to( '${to||'user@example.com'}' )`,
    `\t->subject( '${subject||'Hello'}' )`,
    body,
    `\t->from( get_option( 'admin_email' ), get_bloginfo( 'name' ) )`,
    `\t->send();`,
  ]);
}

// ─── SCAFFOLD ────────────────────────────────────────────────────────────────
function genScaffold(slug, namespace, providers) {
  const mainFile = [
    `<?php`,`/**`,` * Plugin Name: ${slug}`,` * Text Domain: ${slug}`,` * Version:     1.0.0`,` */`,
    `declare(strict_types=1);`,
    `if ( ! defined( 'ABSPATH' ) ) { exit; }`,
    `require_once __DIR__ . '/vendor/autoload.php';`,
    `use WPFlint\\Application;`,`use WPFlint\\Lifecycle\\Lifecycle;`,
    `\$app = Application::get_instance( __DIR__ );`,
    ...providers.map(p=>`\$app->register( ${namespace}\\Providers\\${p}ServiceProvider::class );`),
    `\$app->register( ${namespace}\\Providers\\AppServiceProvider::class );`,
    `Lifecycle::for( __FILE__ )`,
    `\t->on_activate( function () use ( \$app ) { flush_rewrite_rules(); } )`,
    `\t->on_deactivate( function () { flush_rewrite_rules(); } )`,
    `\t->register();`,
    `\$app->bootstrap();`,
  ].join('\n');

  const composer = JSON.stringify({
    name: `vendor/${slug}`,
    type: 'wordpress-plugin',
    require: { php: '>=7.4' },
    autoload: { 'psr-4': { [`${namespace}\\`]: 'app/' } },
  }, null, 2);

  const appProvider = genProvider('AppServiceProvider');
  const providerFiles = providers.map(p => [p+'ServiceProvider', genProvider(p+'ServiceProvider')]);

  let out = `// === ${slug}.php ===\n${mainFile}\n\n// === composer.json ===\n${composer}\n\n// === app/Providers/AppServiceProvider.php ===\n${appProvider}`;
  providerFiles.forEach(([name, code]) => {
    out += `\n\n// === app/Providers/${name}.php ===\n${code}`;
  });
  out += `\n\n// === directories to create ===\n// app/Models/   app/Http/Controllers/   app/Http/Requests/\n// app/Events/   app/Listeners/   app/Jobs/   database/migrations/   templates/`;
  return out;
}

// ─── SEARCH INTENT MAP ───────────────────────────────────────────────────────
const SEARCH = [
  { q: ['rate limit','throttle','ajax limit'],
    a: `// Throttle an AJAX route: 60 requests per 1 minute\n$router->ajax('my-plugin/action',[Ctrl::class,'method'])\n       ->middleware(['nonce:action','can:edit_posts','throttle:60,1']);\n// Built-in: 'throttle:{max},{minutes}'` },
  { q: ['validate request','form validation','validate input'],
    a: `// Extend Request — auto-validated before controller method runs\nclass SaveOrderRequest extends Request {\n    public function authorize(): bool { return current_user_can('edit_posts'); }\n    public function rules(): array { return ['status'=>'required|in:pending,paid','total'=>'required|numeric|min:0']; }\n    public function sanitize(): array { return ['total'=>'floatval']; }\n}\n// Controller: public function store(SaveOrderRequest $req): Response { $req->validated(); }` },
  { q: ['cache query','cache database','remember'],
    a: `Cache::tags(['orders'])->remember('orders_all', 3600, fn() => Order::all());\n// Invalidate:\nCache::tags('orders')->flush();\n// Skip cache:\nCache::fresh()->remember('key', 3600, $cb);` },
  { q: ['fire event','dispatch event','trigger event'],
    a: `// Define\nclass OrderPlaced extends Event { public function __construct(public Order $order) {} }\n// Listen (ServiceProvider boot())\nEvent::listen(OrderPlaced::class, SendConfirmation::class);\n// Fire\nwpflint_event(new OrderPlaced($order));` },
  { q: ['send email','send mail','wp_mail'],
    a: `Mail::to($user->user_email)->subject('Order Confirmed')->html('<h1>Thanks!</h1>')->from(get_option('admin_email'))->send();\n// With template:\nMail::to($email)->subject('Welcome')->template('emails/welcome',['user'=>$user])->send();` },
  { q: ['schedule task','cron','recurring'],
    a: `// ServiceProvider boot():\n$s = $this->app->make('scheduler');\n$s->call(fn() => clean_expired_transients())->name('myplugin_cleanup')->daily();\n$s->register();\n// Or helper: wpflint_schedule(fn()=>sync())->name('myplugin_sync')->every_thirty_minutes();` },
  { q: ['dispatch job','queue job','async'],
    a: `// Define\nclass SendWelcomeEmail extends Job {\n    public function __construct(private int $user_id) {}\n    public function handle(): void { /* wp_mail */ }\n}\n// Dispatch:\nwpflint_dispatch(new SendWelcomeEmail($user_id));` },
  { q: ['get post','find post','query posts'],
    a: `use WPFlint\\WordPress\\Post;\nPost::find(5);                                        // by ID\nPost::published()->type('product')->order_by('post_date','desc')->get();\nPost::where('post_author', $uid)->where('post_status','publish')->first();` },
  { q: ['get user','find user','current user'],
    a: `use WPFlint\\WordPress\\User;\nUser::find(get_current_user_id());\nUser::role('editor')->get();\n// Meta:\nUser::find($id)->meta()->where('meta_key','billing_city')->first();` },
  { q: ['get option','wordpress option','site option'],
    a: `// Single value (prefer native WP):\n$val = get_option('my_plugin_key', 'default');\nupdate_option('my_plugin_key', $val);\n// Bulk / query:\nuse WPFlint\\WordPress\\Option;\nOption::autoloaded()->where('option_name','LIKE','my_plugin_%')->get();` },
  { q: ['register post type','custom post type','cpt'],
    a: `PostType::make('book')->label('Book','Books')->public()->supports(['title','editor','thumbnail'])->show_in_rest()->register();\n// Call inside add_action('init',...)` },
  { q: ['register taxonomy','custom taxonomy'],
    a: `Taxonomy::make('genre')->label('Genre','Genres')->for(['book'])->hierarchical()->show_in_rest()->register();\n// Call inside add_action('init',...)` },
  { q: ['metabox','meta box','custom fields'],
    a: `$box = MetaBox::make('book_details','Book Details')->screen('book')->context('normal');\n$box->field('_isbn','ISBN')->type('text');\n$box->field('_price','Price')->type('number')->sanitize_with('floatval');\n$box->register();\n// Inside add_action('add_meta_boxes',...);\n// Read: get_post_meta($post->ID,'_isbn',true);` },
  { q: ['rest api','rest route','rest endpoint'],
    a: `$router->rest(RestAuth::namespace('my-plugin'), function($r) {\n    $r->get('/orders', [OrderCtrl::class,'index'], RestAuth::logged_in());\n    $r->post('/orders', [OrderCtrl::class,'store'], RestAuth::capability('edit_posts'));\n    $r->delete('/orders/(?P<id>\\d+)', [OrderCtrl::class,'destroy'], RestAuth::capability('delete_posts'));\n});\n$router->boot();` },
  { q: ['permission','capability','current_user_can'],
    a: `// In Request:\npublic function authorize(): bool { return current_user_can('edit_posts'); }\n// REST route permission callback:\nRestAuth::capability('manage_options')\nRestAuth::all_of('edit_posts','publish_posts')\nRestAuth::logged_in()\nRestAuth::public_access()\n// Direct check:\nif (!RestAuth::require_capability('manage_options')) { return new WP_Error('forbidden','',['status'=>403]); }` },
  { q: ['bind service','container binding','dependency injection'],
    a: `// ServiceProvider register():\n$this->app->singleton(OrderService::class, fn($a) => new OrderService($a->make(OrderRepository::class)));\n$this->app->bind(RepositoryInterface::class, EloquentRepository::class);\n// Contextual:\n$this->app->when(OrderController::class)->needs(Cache::class)->give(fn($a)=>$a->make(CacheManager::class));` },
  { q: ['render view','template','view output'],
    a: `// Once in provider register(): View::set_base_path(plugin_dir_path(__FILE__).'templates');\n// Render:\nView::make('orders/index')->with('orders',$orders)->with('title','My Orders')->output();\n// Get HTML: $html = View::make('orders/index')->with('data',$data)->render();` },
  { q: ['admin page','menu page','settings page'],
    a: `AdminPage::make('My Plugin Settings','my-plugin-settings')\n    ->capability('manage_options')\n    ->render(function() { ?><div class="wrap"><h1>Settings</h1></div><?php })\n    ->register();\n// Submenu under Settings:\n// ->register_as_submenu('options-general.php');` },
  { q: ['admin notice','flash notice','notice'],
    a: `Notice::success('Settings saved!')->dismissible()->render();\nNotice::error('Something went wrong.')->render();\n// Flash (once on next page load):\nNotice::info('Plugin updated.')->flash();\nadd_action('admin_notices',[Notice::class,'display_flash']);` },
];

function search(query) {
  const q = query.toLowerCase();
  for (const entry of SEARCH) {
    if (entry.q.some(kw => q.includes(kw))) return entry.a;
  }
  return `// No exact match. Try: wpflint_docs with resource='api/http', 'api/database', 'api/admin', etc.\n// Or ask more specifically: "how do I validate a REST request" / "how do I register a post type"`;
}

// ─── MCP SERVER ───────────────────────────────────────────────────────────────
const server = new McpServer({ name: "wpflint", version: "3.0.0" });

// Resources
for (const [key, doc] of Object.entries(KB)) {
  server.resource(`wpflint-${key.replace(/\//g,'-')}`, `wpflint://${key}`, async(uri) => ({
    contents: [{ uri: uri.href, text: doc, mimeType: "text/markdown" }]
  }));
}

// ─── TOOL 0: wpflint_init ────────────────────────────────────────────────────
server.tool("wpflint_init",
  "Call once per session. Reads composer.json and stores namespace + slug for all generators.",
  { project_path: z.string().describe("Absolute path to plugin root containing composer.json") },
  async ({ project_path }) => {
    try {
      const pkg   = JSON.parse(readFileSync(join(project_path,'composer.json'),'utf8'));
      const psr4  = pkg?.autoload?.['psr-4'] || {};
      const nsKey = Object.keys(psr4)[0] || 'App\\';
      S.namespace = nsKey.replace(/\\+$/,'');
      const parts = (pkg.name||'vendor/my-plugin').split('/');
      S.slug      = parts[1] || parts[0] || 'my-plugin';
      S.td        = S.slug;
      S.path      = project_path;
      S.ok        = true;
    } catch(e) {
      S.namespace='App'; S.slug='my-plugin'; S.td='my-plugin'; S.path=project_path; S.ok=true;
    }
    return { content: [{ type:"text", text:
      `// WPFlint initialized\n// namespace:   ${S.namespace}\n// slug:        ${S.slug}\n// text_domain: ${S.td}\n// path:        ${S.path}`
    }]};
  }
);

// ─── TOOL 1: wpflint_make ────────────────────────────────────────────────────
server.tool("wpflint_make",
  "Generate a WPFlint class file. Types: provider migration model controller rest_controller middleware request event listener facade rule command job",
  {
    type: z.enum(['provider','migration','model','controller','rest_controller','middleware','request','event','listener','facade','rule','command','job']),
    name: z.string(),
    fillable:     z.array(z.string()).optional().default([]),
    casts:        z.record(z.string()).optional().default({}),
    with_migration: z.boolean().optional().default(false),
    rules:        z.record(z.string()).optional().default({}),
    authorize_cap:z.string().optional().default(''),
    event_class:  z.string().optional().default(''),
    properties:   z.array(z.string()).optional().default([]),
    accessor:     z.string().optional().default(''),
    queue:        z.string().optional().default('default'),
    tries:        z.number().optional().default(3),
    rest:         z.boolean().optional().default(false),
    message:      z.string().optional().default('The :attribute field is invalid.'),
  },
  async (p) => {
    let code;
    switch(p.type) {
      case 'provider':        code = genProvider(p.name); break;
      case 'migration':       code = genMigration(p.name); break;
      case 'model':           code = genModel(p.name, p.fillable, p.casts, p.with_migration); break;
      case 'controller':      code = genController(p.name, false); break;
      case 'rest_controller': code = genController(p.name, true); break;
      case 'middleware':      code = genMiddleware(p.name); break;
      case 'request':         code = genRequest(p.name, p.authorize_cap, p.rules); break;
      case 'event':           code = genEvent(p.name, p.properties); break;
      case 'listener':        code = genListener(p.name, p.event_class); break;
      case 'facade':          code = genFacade(p.name, p.accessor); break;
      case 'rule':            code = genRule(p.name, p.message); break;
      case 'command':         code = genCommand(p.name); break;
      case 'job':             code = genJob(p.name, p.queue, p.tries); break;
    }
    return { content: [{ type:"text", text: code }] };
  }
);

// ─── TOOL 2: wpflint_admin ───────────────────────────────────────────────────
server.tool("wpflint_admin",
  "Generate admin UI code. Types: page settings metabox notice",
  {
    type: z.enum(['page','settings','metabox','notice']),
    // page
    slug:       z.string().optional().default('my-plugin-settings'),
    title:      z.string().optional().default('My Plugin'),
    parent:     z.string().optional().default(''),
    capability: z.string().optional().default('manage_options'),
    icon:       z.string().optional().default(''),
    // settings
    option_group: z.string().optional().default('my_plugin_options'),
    option_name:  z.string().optional().default('my_plugin_options'),
    page_slug:    z.string().optional().default('my-plugin-settings'),
    sections: z.array(z.object({ id:z.string(), title:z.string() })).optional().default([{id:'general',title:'General'}]),
    fields:   z.array(z.object({ name:z.string(), label:z.string().optional().default(''), type:z.string().optional().default('text'), section:z.string().optional().default('general'), required:z.boolean().optional().default(false) })).optional().default([]),
    // metabox
    id:     z.string().optional().default('my_metabox'),
    screen: z.string().optional().default('post'),
    meta_fields: z.array(z.object({ id:z.string(), label:z.string(), type:z.string().optional().default('text'), description:z.string().optional().default('') })).optional().default([]),
    // notice
    message:     z.string().optional().default('Action completed.'),
    notice_type: z.enum(['success','error','warning','info']).optional().default('success'),
    dismissible: z.boolean().optional().default(true),
  },
  async (p) => {
    let code;
    switch(p.type) {
      case 'page':     code = genAdminPage(p.slug, p.title, p.parent, p.capability, p.icon); break;
      case 'settings': code = genSettings(p.option_group, p.option_name, p.page_slug, p.sections, p.fields); break;
      case 'metabox':  code = genMetaBox(p.id, p.title, p.screen, p.meta_fields); break;
      case 'notice':   code = genNotice(p.message, p.notice_type, p.dismissible); break;
    }
    return { content: [{ type:"text", text: code }] };
  }
);

// ─── TOOL 3: wpflint_content ─────────────────────────────────────────────────
server.tool("wpflint_content",
  "Generate content type and display layer code. Types: post_type taxonomy meta_field shortcode block widget",
  {
    type: z.enum(['post_type','taxonomy','meta_field','shortcode','block','widget']),
    // post_type
    slug:        z.string().optional().default('book'),
    singular:    z.string().optional().default('Book'),
    plural:      z.string().optional().default(''),
    supports:    z.array(z.string()).optional().default(['title','editor']),
    icon:        z.string().optional().default(''),
    public:      z.boolean().optional().default(true),
    hierarchical:z.boolean().optional().default(false),
    show_in_rest:z.boolean().optional().default(true),
    // taxonomy
    post_types:  z.array(z.string()).optional().default(['post']),
    // meta_field
    object_type: z.enum(['post','term','user','comment']).optional().default('post'),
    subtype:     z.string().optional().default('post'),
    key:         z.string().optional().default('_my_field'),
    meta_type:   z.string().optional().default('string'),
    single:      z.boolean().optional().default(true),
    // shortcode
    tag:         z.string().optional().default('my_shortcode'),
    atts:        z.array(z.string()).optional().default([]),
    // block
    block_name:  z.string().optional().default('my-plugin/my-block'),
    block_title: z.string().optional().default('My Block'),
    category:    z.string().optional().default('design'),
    dynamic:     z.boolean().optional().default(true),
    // widget
    widget_title:      z.string().optional().default('My Widget'),
    widget_description:z.string().optional().default('A custom widget.'),
  },
  async (p) => {
    let code;
    switch(p.type) {
      case 'post_type':  code = genPostType(p.slug, p.singular, p.plural, p.supports, p.icon, p.public, p.hierarchical, p.show_in_rest); break;
      case 'taxonomy':   code = genTaxonomy(p.slug, p.singular, p.plural, p.post_types, p.hierarchical, p.show_in_rest); break;
      case 'meta_field': code = genMetaField(p.object_type, p.subtype, p.key, p.meta_type, p.single, p.show_in_rest); break;
      case 'shortcode':  code = genShortcode(p.tag, p.atts); break;
      case 'block':      code = genBlock(p.block_name, p.block_title, p.category, p.dynamic); break;
      case 'widget':     code = genWidget(p.singular+'Widget', p.widget_title, p.widget_description); break;
    }
    return { content: [{ type:"text", text: code }] };
  }
);

// ─── TOOL 4: wpflint_http ────────────────────────────────────────────────────
server.tool("wpflint_http",
  "Generate HTTP/REST layer code. Types: ajax rest rest_auth",
  {
    type:       z.enum(['ajax','rest','rest_auth']),
    action:     z.string().optional().default('my-plugin/action'),
    controller: z.string().optional().default('MyController'),
    method:     z.string().optional().default('store'),
    middleware: z.array(z.string()).optional().default(['nonce:my_action','can:edit_posts']),
    nopriv:     z.boolean().optional().default(false),
    namespace:  z.string().optional().default(''),
    version:    z.number().optional().default(1),
    routes: z.array(z.object({
      method:     z.string().optional().default('get'),
      path:       z.string(),
      controller: z.string().optional().default('MyController'),
      action:     z.string().optional().default('index'),
      permission: z.enum(['public','logged_in','manage_options']).optional().default('logged_in'),
    })).optional().default([]),
  },
  async (p) => {
    const ns_ = p.namespace || S.slug;
    let code;
    switch(p.type) {
      case 'ajax':
        code = genAjaxRoute(p.action, p.controller, p.method, p.middleware, p.nopriv); break;
      case 'rest':
        code = genRestRoutes(ns_, p.version, p.routes.length ? p.routes : [{method:'get',path:'/items',controller:p.controller,action:'index',permission:'logged_in'}]); break;
      case 'rest_auth':
        code = [
          `use WPFlint\\Http\\RestAuth;`,``,
          `RestAuth::namespace( '${ns_}', ${p.version} )  // '${ns_}/v${p.version}'`,``,
          `// Callbacks (pass to permission_callback):`,
          `RestAuth::public_access()               // always true`,
          `RestAuth::logged_in()                   // any authenticated user`,
          `RestAuth::capability( 'manage_options' )`,
          `RestAuth::all_of( 'edit_posts', 'publish_posts' )`,
          `RestAuth::any_of( 'edit_posts', 'edit_pages' )`,``,
          `// Direct boolean checks inside controllers:`,
          `RestAuth::require_logged_in()            // bool`,
          `RestAuth::require_capability( 'manage_options' ) // bool`,
        ].join('\n'); break;
    }
    return { content: [{ type:"text", text: code }] };
  }
);

// ─── TOOL 5: wpflint_asset ───────────────────────────────────────────────────
server.tool("wpflint_asset",
  "Generate script or style enqueue code.",
  {
    type:         z.enum(['script','style']).optional().default('script'),
    handle:       z.string().describe("Asset handle"),
    src:          z.string().describe("PHP expression or URL string"),
    deps:         z.array(z.string()).optional().default([]),
    version:      z.string().optional().default('1.0.0'),
    in_footer:    z.boolean().optional().default(true),
    localize_key: z.string().optional().default(''),
    localize_data:z.string().optional().default("'ajax_url' => admin_url( 'admin-ajax.php' ),\n\t\t'nonce'    => wp_create_nonce( 'my_action' ),"),
  },
  async (p) => {
    const srcExpr = p.src.includes('(') ? p.src : `'${p.src}'`;
    const depsLine = p.deps.length ? `\n\t->deps( array( '${p.deps.join("', '")}' ) )` : '';
    let code;
    if (p.type==='style') {
      code = `use WPFlint\\Assets\\Style;\n\nStyle::make( '${p.handle}', ${srcExpr} )\n\t->version( '${p.version}' )${depsLine}\n\t->enqueue();`;
    } else {
      const footerLine   = p.in_footer ? `\n\t->footer()` : '';
      const localizeLine = p.localize_key ? `\n\t->localize( '${p.localize_key}', array(\n\t\t${p.localize_data}\n\t) )` : '';
      code = `use WPFlint\\Assets\\Script;\n\nScript::make( '${p.handle}', ${srcExpr} )\n\t->version( '${p.version}' )${depsLine}${footerLine}${localizeLine}\n\t->enqueue();`;
    }
    return { content: [{ type:"text", text: code }] };
  }
);

// ─── TOOL 6: wpflint_system ──────────────────────────────────────────────────
server.tool("wpflint_system",
  "Generate system-level code. Types: lifecycle view mail",
  {
    type:       z.enum(['lifecycle','view','mail']),
    hooks:      z.array(z.enum(['activation','deactivation','uninstall'])).optional().default(['activation','deactivation','uninstall']),
    view_name:  z.string().optional().default('pages/index'),
    variables:  z.array(z.string()).optional().default([]),
    to:         z.string().optional().default(''),
    subject:    z.string().optional().default(''),
    body_type:  z.enum(['text','view']).optional().default('text'),
    view_template: z.string().optional().default(''),
  },
  async (p) => {
    let code;
    switch(p.type) {
      case 'lifecycle': code = genLifecycle(p.hooks); break;
      case 'view':      code = genView(p.view_name, p.variables); break;
      case 'mail':      code = genMail(p.to, p.subject, p.body_type, p.view_template); break;
    }
    return { content: [{ type:"text", text: code }] };
  }
);

// ─── TOOL 7: wpflint_docs ────────────────────────────────────────────────────
server.tool("wpflint_docs",
  "Fetch framework documentation for any module. Use when you need the full API reference before generating code.",
  {
    resource: z.enum([
      'guide','constraints','directory','patterns',
      'api/container','api/http','api/rest-auth','api/database','api/cache','api/events',
      'api/admin','api/registration','api/content','api/assets','api/view-mail',
      'api/lifecycle','api/logging','api/queue','api/scheduling','api/facades',
      'api/validation','api/config','api/wordpress','api/console',
    ]).describe("Resource key to fetch"),
  },
  async ({ resource }) => {
    const doc = KB[resource];
    return { content: [{ type:"text", text: doc || `No documentation found for: ${resource}` }] };
  }
);

// ─── TOOL 8: wpflint_search ──────────────────────────────────────────────────
server.tool("wpflint_search",
  "Get the one correct WPFlint answer for a developer question. Returns a ready-to-use code snippet.",
  { query: z.string().describe("Natural language question, e.g. 'how do I cache a DB query'") },
  async ({ query }) => ({ content: [{ type:"text", text: search(query) }] })
);

// ─── TOOL 9: wpflint_scaffold ────────────────────────────────────────────────
server.tool("wpflint_scaffold",
  "Scaffold a new WPFlint plugin. Generates all starter files using the initialized namespace+slug.",
  {
    slug:      z.string().optional().default('').describe("Override slug (default: from wpflint_init)"),
    providers: z.array(z.string()).optional().default([]).describe("Extra provider name prefixes, e.g. ['Order','Payment']"),
  },
  async ({ slug, providers }) => {
    const s = slug || S.slug;
    const n = S.namespace;
    return { content: [{ type:"text", text: genScaffold(s, n, providers) }] };
  }
);

// ─── START ────────────────────────────────────────────────────────────────────
const transport = new StdioServerTransport();
await server.connect(transport);
