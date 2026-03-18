# Database ORM

WPFlint provides a Laravel-inspired Active Record ORM built on `$wpdb`. All queries use `$wpdb->prepare()` for parameter binding.

## QueryBuilder

Fluent SQL builder that compiles and executes queries via `$wpdb`.

```php
use WPFlint\Database\ORM\QueryBuilder;

// Create from unprefixed table name.
$query = QueryBuilder::table( 'orders' );
```

### Selecting

```php
$query->select( array( 'id', 'status', 'total' ) );
$query->add_select( 'created_at' );
$query->distinct();
```

### WHERE clauses

```php
$query->where( 'status', 'pending' );           // = implied
$query->where( 'total', '>', 100 );             // explicit operator
$query->or_where( 'status', 'paid' );
$query->where_in( 'id', array( 1, 2, 3 ) );
$query->where_not_in( 'id', array( 4, 5 ) );
$query->where_null( 'deleted_at' );
$query->where_not_null( 'paid_at' );
$query->where_between( 'total', 10, 100 );
$query->where_not_between( 'total', 0, 5 );
$query->where_like( 'name', '%john%' );
$query->where_raw( 'total > %d', array( 50 ) );
```

### Ordering / Grouping

```php
$query->order_by( 'created_at', 'DESC' );
$query->latest();                         // created_at DESC
$query->oldest();                         // created_at ASC
$query->group_by( 'status' );
$query->having( 'COUNT(*)', '>', 5 );
```

### Limit / Offset

```php
$query->limit( 10 )->offset( 20 );
$query->take( 10, 20 );                  // shorthand
```

### Joins

```php
$query->join( 'users', 'orders.user_id', '=', 'users.id' );
$query->left_join( 'profiles', 'users.id', '=', 'profiles.user_id' );
$query->right_join( 'logs', 'orders.id', '=', 'logs.order_id' );
```

### Retrieval

```php
$rows  = $query->get();                  // array of assoc arrays
$row   = $query->first();                // single assoc array or null
$row   = $query->find( 42 );             // find by primary key
$value = $query->value( 'status' );       // single column value
$ids   = $query->pluck( 'id' );           // array of values
$map   = $query->pluck( 'name', 'id' );   // keyed array
$exists = $query->exists();
$empty  = $query->doesnt_exist();
```

### Aggregates

```php
$count = $query->count();
$max   = $query->max( 'total' );
$min   = $query->min( 'total' );
$avg   = $query->avg( 'total' );
$sum   = $query->sum( 'total' );
```

### Pagination / Chunking

```php
$page = $query->paginate( 15, 1 );
// Returns: ['data' => [...], 'total' => 100, 'per_page' => 15, 'current_page' => 1, 'last_page' => 7]

$query->chunk( 100, function ( array $rows ) {
    // Process batch. Return false to stop.
} );
```

### Insert / Update / Delete

```php
$id = $query->insert( array( 'status' => 'pending', 'total' => 99.50 ) );
$query->insert_many( array( $row1, $row2 ) );

$query->where( 'id', 1 )->update( array( 'status' => 'paid' ) );
$query->where( 'id', 1 )->increment( 'views' );
$query->where( 'id', 1 )->decrement( 'stock', 3 );
$query->where( 'id', 1 )->delete();
```

## Model

Abstract base class for Active Record models.

### Defining Models

```php
use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;

class Order extends Model {
    protected static string $table = 'orders';        // optional, auto-inferred
    protected static string $primary_key = 'id';       // default
    protected static bool $timestamps = true;           // default

    protected array $fillable = array( 'status', 'total', 'meta' );
    protected array $hidden = array( 'internal_notes' );
    protected array $casts = array(
        'total' => 'float',
        'meta'  => 'array',
        'active' => 'boolean',
    );

    // Scopes
    public function scope_pending( ModelQueryBuilder $q ): ModelQueryBuilder {
        return $q->where( 'status', 'pending' );
    }

    public function scope_with_min_total( ModelQueryBuilder $q, int $min ): ModelQueryBuilder {
        return $q->where( 'total', '>=', $min );
    }
}
```

### Table Name Inference

If `$table` is not set, it's inferred from the class name:
- `Order` → `orders`
- `UserOrder` → `user_orders`
- `Category` → `categories`

### Static Methods

```php
Order::find( 1 );                                        // Model or null
Order::find_or_fail( 1 );                                // Model or throws ModelNotFoundException
Order::all();                                             // array of Models
Order::where( 'status', 'pending' )->get_models();       // filtered
Order::where_in( 'id', array( 1, 2, 3 ) )->get_models();
Order::create( array( 'status' => 'pending', 'total' => 50 ) );
Order::first_or_create( array( 'status' => 'pending' ), array( 'total' => 0 ) );
Order::first_or_new( array( 'status' => 'pending' ) );
Order::update_or_create( array( 'email' => 'x@y.com' ), array( 'name' => 'X' ) );
Order::destroy( 1 );
Order::query();                                           // raw QueryBuilder
```

### Scopes

```php
Order::pending()->get_models();
Order::with_min_total( 100 )->get_models();
```

### Instance Methods

```php
$order = new Order( array( 'status' => 'pending', 'total' => 50 ) );
$order->save();              // INSERT (auto-sets created_at, updated_at)

$order->status = 'paid';
$order->save();              // UPDATE (only dirty attributes + updated_at)

$order->delete();            // DELETE
$order->fresh();             // reload from DB

$order->fill( array( 'total' => 100 ) );
$order->is_dirty();          // true
$order->is_dirty( 'total' ); // true
$order->get_dirty();         // ['total' => 100]

$order->to_array();          // excludes $hidden, casts values
$order->to_json();
```

### Casting

Supported cast types: `int`/`integer`, `float`/`double`, `string`, `bool`/`boolean`, `array`/`json`, `datetime`.

Array/JSON casts automatically `json_encode` on save and `json_decode` on read.

## Relationships

### Defining Relationships

```php
class User extends Model {
    protected static string $table = 'users';

    public function profile(): HasOne {
        return $this->has_one( Profile::class, 'user_id', 'id' );
    }

    public function orders(): HasMany {
        return $this->has_many( Order::class, 'user_id', 'id' );
    }
}

class Order extends Model {
    protected static string $table = 'orders';

    public function user(): BelongsTo {
        return $this->belongs_to( User::class, 'user_id', 'id' );
    }
}
```

Foreign and local keys are auto-inferred if omitted:
- `has_one`/`has_many`: foreign key = `{parent_snake}_id`, local key = primary key
- `belongs_to`: foreign key = `{related_snake}_id`, local key = related primary key

### Lazy Loading

```php
$user = User::find( 1 );
$profile = $user->profile()->get_results();   // single Model or null
$orders  = $user->orders()->get_results();    // array of Models
$user    = $order->user()->get_results();     // single Model or null
```

### Eager Loading

Uses `WHERE IN` batching for efficient N+1 prevention:

```php
$users = User::where( 'active', 1 )
    ->with( array( 'orders', 'profile' ) )
    ->get_models();

// Each user now has ->orders and ->profile loaded.
foreach ( $users as $user ) {
    $user->orders;   // already loaded, no extra query
    $user->profile;  // already loaded, no extra query
}
```

### Relation Serialization

Loaded relations are included in `to_array()` / `to_json()`:

```php
$user = User::where( 'id', 1 )->with( array( 'orders' ) )->first_model();
$user->to_array();
// ['id' => 1, 'name' => 'John', 'orders' => [['id' => 1, 'status' => 'pending'], ...]]
```

## RawExpression

For developer-controlled SQL fragments (not user input):

```php
use WPFlint\Database\ORM\RawExpression;

$query->update( array( 'views' => new RawExpression( 'views + 1' ) ) );
```

The `increment()` and `decrement()` methods use this internally.

## Security

- Every parameterized query uses `$wpdb->prepare()`
- Table and column names are developer-controlled (not user input)
- Mass assignment is guarded via `$fillable`
- Hidden attributes are excluded from serialization
