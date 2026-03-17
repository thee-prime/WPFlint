# Schema — Database Table Management

The Schema module provides a fluent API for creating, dropping, and inspecting database tables. It generates `dbDelta()`-compatible SQL and handles WordPress table prefixing automatically.

## Classes

| Class | Purpose |
|-------|---------|
| `Schema` | Orchestrator — create/drop/has_table/add_column via `$wpdb` |
| `Blueprint` | Table definition builder — columns, indexes, foreign keys |
| `ColumnDefinition` | Fluent value object for a single column |
| `ForeignKeyDefinition` | Fluent builder for foreign key constraints |

## Creating Tables

```php
use WPFlint\Database\Schema\Schema;

$schema = new Schema();

$schema->create('orders', function ($table) {
    $table->big_increments('id');
    $table->string('status', 50)->default('pending');
    $table->decimal('total', 10, 2);
    $table->integer('user_id');
    $table->text('notes')->nullable();
    $table->boolean('is_paid')->default(false);
    $table->timestamps();    // created_at, updated_at (nullable DATETIME)
    $table->soft_deletes();  // deleted_at (nullable DATETIME)

    // Indexes
    $table->index('status');
    $table->unique('order_number');
    $table->index(array('status', 'is_paid'));

    // Foreign keys
    $table->foreign('user_id')->references('id')->on('wp_users');
});
```

The table name is automatically prefixed with `$wpdb->prefix`. The generated SQL uses the `dbDelta()` two-space formatting convention.

## Column Types

| Method | SQL Type | Notes |
|--------|----------|-------|
| `big_increments($col)` | `BIGINT UNSIGNED` | Auto-increment, sets PRIMARY KEY |
| `string($col, $len=255)` | `VARCHAR($len)` | |
| `integer($col)` | `INT` | |
| `decimal($col, $p=8, $s=2)` | `DECIMAL($p,$s)` | |
| `boolean($col)` | `TINYINT(1)` | |
| `text($col)` | `TEXT` | |
| `timestamps()` | Two `DATETIME NULL` columns | `created_at`, `updated_at` |
| `soft_deletes()` | `DATETIME NULL` | `deleted_at` |

## Column Modifiers

Chain modifiers after any column method:

```php
$table->string('email')->nullable()->default(null);
$table->integer('quantity')->default(0);
$table->boolean('active')->default(true);
```

| Modifier | Effect |
|----------|--------|
| `nullable()` | Column allows NULL (omits NOT NULL) |
| `default($value)` | Sets DEFAULT — strings quoted, numbers unquoted, booleans become 0/1, null becomes NULL |

## Indexes

```php
$table->index('email');                          // KEY idx_email (email)
$table->index(array('first', 'last'));           // KEY idx_first_last (first,last)
$table->unique('email');                         // UNIQUE KEY uniq_email (email)
```

## Foreign Keys

```php
$table->foreign('user_id')
    ->references('id')
    ->on('wp_users')
    ->on_delete('SET NULL')   // default: CASCADE
    ->on_update('RESTRICT');  // default: CASCADE
```

## Dropping Tables

```php
$schema->drop('orders');  // DROP TABLE IF EXISTS {prefix}orders
```

## Checking Table Existence

```php
if ($schema->has_table('orders')) {
    // Table exists
}
```

Uses `$wpdb->prepare()` with `SHOW TABLES LIKE %s`.

## Adding Columns

```php
$schema->add_column('orders', 'tracking_number', 'VARCHAR(100) NULL');
```

## Generated SQL Example

```sql
CREATE TABLE wp_orders (
id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
status  VARCHAR(50) NOT NULL DEFAULT 'pending',
total  DECIMAL(10,2) NOT NULL,
created_at  DATETIME NULL,
updated_at  DATETIME NULL,
PRIMARY KEY  (id),
KEY idx_status (status)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Note the two spaces between column names and types, and two spaces before `(id)` in PRIMARY KEY — both required by `dbDelta()`.
