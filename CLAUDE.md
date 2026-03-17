# WPFlint — Claude Code context

## What this is
Laravel-inspired framework for WordPress plugins. PHP 7.4+. Zero prod dependencies.
Consumers build plugins on top of this. Ships as standalone plugin or Composer package.

## Absolute rules (never break)
- PHP 7.4 only. No: named args, enums, match(), union types, nullsafe ?-> in loops,
  readonly, fibers, str_contains/str_starts_with. YES: typed props, arrow fns fn()=>.
- $wpdb->prepare() on EVERY query. No exceptions.
- All tables: $wpdb->prefix . 'tablename'
- All options/transients: prefixed with plugin slug
- No globals. Use container.
- Strings: __() or _e() always.
- Nonces: every form + AJAX.
- Cap checks: before every privileged op.
- src/Console/ = dev only, excluded via .distignore. Never autoloaded in prod.

## Directory map
src/
  Application.php          # singleton bootstrap
  Http/                    # Router, Controller, Request, Response, Middleware
  Container/Container.php  # IoC, PSR-11
  Providers/               # ServiceProvider base + core providers
  Console/                 # WP_CLI commands (dev only)
  Database/Migrations/     # Migration runner + base class
  Database/ORM/            # Model + QueryBuilder + relationships
  Cache/                   # CacheManager, drivers
  Config/Repository.php    # dot-notation config
  Events/                  # Dispatcher + Event base
  Facades/                 # Static proxies
  Support/                 # Helpers, traits
tests/                     # mirrors src/, PHPUnit 9
docs/                      # one .md per module

## Build loop — mandatory per class
1. Write implementation
2. Write tests immediately
3. composer test — fix ALL failures
4. composer lint — fix ALL phpcs violations  
5. Update docs/{module}.md
Never defer failing tests.

## WP.org compliance checklist (run before release)
- [ ] prepare() on all queries
- [ ] sanitize_*() on all input
- [ ] esc_*() on all output
- [ ] check_ajax_referer() + current_user_can() on AJAX
- [ ] uninstall.php removes all tables + options
- [ ] .distignore excludes: tests/ vendor/ .claude/ src/Console/ docs/ composer.json
- [ ] Text domain = plugin slug everywhere
- [ ] No eval/exec/system

## Test setup
PHPUnit 9.2+. WP_Mock for WP functions. Brain\Monkey for hooks.
Bootstrap: tests/bootstrap.php — init WP_Mock, define WP constants.
Every public method needs a test. 80% coverage minimum.

## Key patterns (reference these when building)

### Container binding
$app->bind(ContractInterface::class, ConcreteClass::class);
$app->singleton('cache', fn($app) => new CacheManager($app));
$app->make(ContractInterface::class); // auto-resolves constructor deps

### Service provider
class MyProvider extends ServiceProvider {
    public bool $defer = true;
    public function register(): void { $this->app->singleton(...); }
    public function boot(): void { /* hook registrations */ }
    public function provides(): array { return [MyService::class]; }
}

### Migration
class CreateOrdersTable extends Migration {
    public function up(): void {
        $this->schema()->create('orders', function(Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('status')->default('pending');
            $t->timestamps();
        });
    }
    public function down(): void { $this->schema()->drop('orders'); }
}

### Model
class Order extends Model {
    protected static string $table = 'orders';
    protected array $fillable = ['status','total'];
    protected array $casts = ['total' => 'float', 'meta' => 'array'];
    public function scopePending(QueryBuilder $q): QueryBuilder {
        return $q->where('status','pending');
    }
}
// Usage: Order::pending()->where('total','>',100)->get();

### Cache
Cache::tags(['orders'])->remember('key', 3600, fn() => Order::all());
Cache::tags('orders')->flush();
Cache::fresh()->remember('key', 3600, $cb); // bypass all tiers

### Events
Event::listen(OrderPlaced::class, SendConfirmation::class);
Event::fire(new OrderPlaced($order));

## HTTP layer patterns

### Router registration (in a ServiceProvider boot())
// AJAX
Router::ajax('my-plugin/save-order', [OrderController::class, 'store'])
      ->middleware(['nonce:save_order', 'can:edit_posts']);
Router::ajax('my-plugin/get-orders', [OrderController::class, 'index'])
      ->nopriv()  // allow non-logged-in users
      ->middleware(['throttle:60,1']);

// REST API
Router::rest('my-plugin/v1', function(RestRouter $r) {
    $r->get('/orders',          [OrderRestController::class, 'index']);
    $r->post('/orders',         [OrderRestController::class, 'store']);
    $r->get('/orders/(?P<id>\d+)', [OrderRestController::class, 'show']);
    $r->put('/orders/(?P<id>\d+)', [OrderRestController::class, 'update']);
    $r->delete('/orders/(?P<id>\d+)', [OrderRestController::class, 'destroy']);
});

### Controller — auto-resolution
// Constructor deps resolved from container automatically.
// If a method type-hints a Request subclass, it is resolved,
// validated, and authorized BEFORE the method is called.
// If validation fails → wp_send_json_error() / WP_Error returned immediately.
class OrderController extends Controller {
    public function __construct(
        private OrderService $orders,  // resolved from container
        private CacheManager $cache
    ) {}

    public function store(StoreOrderRequest $request): Response {
        // $request already validated + sanitized here
        $order = $this->orders->create($request->validated());
        return Response::json($order->toArray(), 201);
    }
}

### Request — validation
class StoreOrderRequest extends Request {
    public function authorize(): bool {
        return current_user_can('edit_posts');
    }
    public function rules(): array {
        return [
            'status'   => 'required|in:pending,paid,cancelled',
            'total'    => 'required|numeric|min:0',
            'items'    => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.qty'        => 'required|integer|min:1',
        ];
    }
    public function sanitize(): array {
        return [
            'status' => 'sanitize_text_field',
            'total'  => 'floatval',
        ];
    }
    // Access: $request->input('status'), $request->validated(), $request->all()
    // Files:  $request->file('attachment')
}

### REST Controller
class OrderRestController extends RestController {
    protected string $namespace = 'my-plugin/v1';
    protected string $rest_base = 'orders';

    public function __construct(private OrderService $orders) {}

    public function index(WP_REST_Request $request): WP_REST_Response {
        return $this->respond($this->orders->paginate(
            $request->get_param('per_page') ?? 10,
            $request->get_param('page') ?? 1
        ));
    }
    public function get_item_permissions_check($request): bool {
        return current_user_can('read');
    }
    public function get_items_permissions_check($request): bool {
        return current_user_can('read');
    }
}

### Response helpers
Response::json(['order' => $order], 200);      // AJAX: wp_send_json_success
Response::error('Not found', 404);             // AJAX: wp_send_json_error
Response::noContent();                          // 204
// REST responses are WP_REST_Response — RestController::respond() wraps automatically

### Middleware
// Built-in: 'nonce:{action}', 'can:{capability}', 'throttle:{max},{minutes}'
// Custom: implement MiddlewareInterface — handle(Request, Closure): Response
class EnsureStoreIsOpen implements MiddlewareInterface {
    public function handle(Request $request, Closure $next): mixed {
        if (! get_option('store_open')) {
            return Response::error('Store is closed', 403);
        }
        return $next($request);
    }
}
// Register: $app->make(Router::class)->aliasMiddleware('store.open', EnsureStoreIsOpen::class);
```


