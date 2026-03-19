# Plugin Quickstart

Build a WordPress plugin with WPFlint in 10 steps.

## 1. Scaffold the plugin

```bash
wp wpflint scaffold my-shop --namespace=MyShop
```

Or manually create the directory structure:

```
my-shop/
  my-shop.php
  composer.json
  config/app.php
  app/
    Models/
    Providers/
    Http/Controllers/
    Http/Middleware/
    Http/Requests/
    Events/
    Facades/
  database/migrations/
```

## 2. Require WPFlint

```bash
composer require wpflint/wpflint
```

## 3. Bootstrap in your main plugin file

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

$app = Application::get_instance( __DIR__ );
$app->register( MyShop\Providers\ShopServiceProvider::class );
$app->bootstrap();
```

## 4. Create a service provider

```bash
wp wpflint make:provider ShopServiceProvider
```

```php
<?php

declare(strict_types=1);

namespace MyShop\Providers;

use WPFlint\Providers\ServiceProvider;
use WPFlint\Events\Dispatcher;
use MyShop\Events\OrderPlaced;
use MyShop\Listeners\SendConfirmation;

class ShopServiceProvider extends ServiceProvider {

    public function register(): void {
        $this->app->singleton( 'orders', function () {
            return new \MyShop\Services\OrderService();
        } );
    }

    public function boot(): void {
        $dispatcher = $this->app->make( Dispatcher::class );
        $dispatcher->listen( OrderPlaced::class, SendConfirmation::class );
    }
}
```

## 5. Create a migration

```bash
wp wpflint make:migration CreateOrdersTable
```

```php
<?php

declare(strict_types=1);

use WPFlint\Database\Migrations\Migration;
use WPFlint\Database\Schema\Blueprint;

class CreateOrdersTable extends Migration {

    public function up(): void {
        $this->schema()->create( 'orders', function ( Blueprint $table ) {
            $table->big_increments( 'id' );
            $table->string( 'status' )->default( 'pending' );
            $table->decimal( 'total', 10, 2 );
            $table->timestamps();
        } );
    }

    public function down(): void {
        $this->schema()->drop( 'orders' );
    }
}
```

## 6. Run the migration

```bash
wp wpflint migrate
```

## 7. Create a model

```bash
wp wpflint make:model Order
```

```php
<?php

declare(strict_types=1);

namespace MyShop\Models;

use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;

class Order extends Model {

    protected static string $table = 'orders';

    protected array $fillable = array( 'status', 'total' );

    protected array $casts = array(
        'id'    => 'integer',
        'total' => 'float',
    );

    public function scope_pending( ModelQueryBuilder $query ): ModelQueryBuilder {
        return $query->where( 'status', '=', 'pending' );
    }
}
```

Usage:

```php
// Create
$order = Order::create( array( 'status' => 'pending', 'total' => 99.50 ) );

// Query
$pending = Order::pending()->get_models();
$order   = Order::find( 1 );
$all     = Order::all();

// Update
$order->set_attribute( 'status', 'paid' );
$order->save();

// Delete
$order->delete();
```

## 8. Create a controller + request

```bash
wp wpflint make:controller OrderController
wp wpflint make:request StoreOrderRequest
```

```php
<?php

declare(strict_types=1);

namespace MyShop\Http\Controllers;

use WPFlint\Http\Controller;
use WPFlint\Http\Response;
use MyShop\Http\Requests\StoreOrderRequest;
use MyShop\Models\Order;

class OrderController extends Controller {

    public function index(): Response {
        return Response::json( Order::all() );
    }

    public function store( StoreOrderRequest $request ): Response {
        $order = Order::create( $request->validated() );
        return Response::json( $order->to_array(), 201 );
    }
}
```

## 9. Register routes (in provider boot)

```php
use WPFlint\Http\Router;

Router::ajax( 'my-shop/orders', array( OrderController::class, 'index' ) )
    ->middleware( array( 'can:read' ) );

Router::ajax( 'my-shop/create-order', array( OrderController::class, 'store' ) )
    ->middleware( array( 'nonce:create_order', 'can:edit_posts' ) );
```

## 10. Fire events + use cache

```php
use WPFlint\Events\Event;
use WPFlint\Facades\Cache;
use WPFlint\Facades\Event as EventFacade;

// Fire an event after order creation
EventFacade::fire( new OrderPlaced( $order ) );

// Cache expensive queries
$orders = Cache::tags( array( 'orders' ) )->remember( 'pending', 3600, function () {
    return Order::pending()->get_models();
} );

// Invalidate when data changes
Cache::tags( 'orders' )->flush();
```

## WP-CLI Commands Reference

| Command | Description |
|---------|-------------|
| `wp wpflint migrate` | Run pending migrations |
| `wp wpflint migrate --rollback` | Roll back last batch |
| `wp wpflint migrate --rollback --steps=N` | Roll back N batches |
| `wp wpflint migrate --fresh` | Drop all tables, re-run all |
| `wp wpflint migrate --status` | Show migration status |
| `wp wpflint cache:clear` | Clear all cache |
| `wp wpflint cache:clear --tag=orders` | Clear tagged cache |
| `wp wpflint make:migration <Name>` | Generate migration stub |
| `wp wpflint make:model <Name>` | Generate model stub |
| `wp wpflint make:model <Name> --migration` | Model + migration |
| `wp wpflint make:provider <Name>` | Generate service provider |
| `wp wpflint make:controller <Name>` | Generate controller |
| `wp wpflint make:controller <Name> --rest` | Generate REST controller |
| `wp wpflint make:middleware <Name>` | Generate middleware |
| `wp wpflint make:request <Name>` | Generate form request |
| `wp wpflint make:event <Name>` | Generate event |
| `wp wpflint make:facade <Name>` | Generate facade |

All `make:*` commands accept `--path=<dir>` to customize the output directory.
