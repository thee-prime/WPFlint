# Database Migrations

WPFlint provides a Laravel-inspired migration system for managing database schema changes. Migrations are version-controlled, reversible, and scoped per plugin so multiple plugins sharing WPFlint don't collide.

## Creating a Migration

Extend `WPFlint\Database\Migrations\Migration` and implement `up()` and `down()`:

```php
<?php

declare(strict_types=1);

use WPFlint\Database\Migrations\Migration;

class CreateOrdersTable extends Migration {

    public function up(): void {
        $this->schema()->create( 'orders', function ( $table ) {
            $table->big_increments( 'id' );
            $table->string( 'status' )->default( 'pending' );
            $table->decimal( 'total' );
            $table->timestamps();
        } );
    }

    public function down(): void {
        $this->schema()->drop( 'orders' );
    }
}
```

The `schema()` helper returns a `WPFlint\Database\Schema\Schema` instance that provides `create()`, `drop()`, `has_table()`, and `add_column()` methods.

## Migration Repository

`MigrationRepository` tracks which migrations have been run in the `{prefix}wpflint_migrations` table. Each record is scoped by `plugin_slug`.

```php
use WPFlint\Database\Migrations\MigrationRepository;

$repository = new MigrationRepository( 'my-plugin' );

// Create the tracking table (first run).
$repository->create_repository();

// Check if tracking table exists.
$repository->repository_exists(); // bool

// Get names of migrations already run.
$repository->get_ran(); // ['CreateUsersTable', 'CreateOrdersTable']

// Get last batch number.
$repository->get_last_batch_number(); // int

// Get migrations from the last batch (for rollback).
$repository->get_last_batch(); // array of objects

// Log a migration as run.
$repository->log( 'CreateOrdersTable', 1 );

// Delete a migration record.
$repository->delete( 'CreateOrdersTable' );

// Get all records for this plugin.
$repository->get_all(); // array of objects
```

### Table Schema

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Primary key |
| `migration` | VARCHAR(255) | Migration class name |
| `plugin_slug` | VARCHAR(100) | Scoping key for multi-plugin support |
| `batch` | INT | Groups migrations for rollback |
| `created_at` | DATETIME | When the migration was run |
| `updated_at` | DATETIME | Timestamp (from Blueprint::timestamps()) |

## Migrator

`Migrator` orchestrates running, rolling back, and inspecting migrations.

```php
use WPFlint\Database\Migrations\Migrator;
use WPFlint\Database\Migrations\MigrationRepository;

$repository = new MigrationRepository( 'my-plugin' );
$migrator   = new Migrator( $repository, array(
    CreateUsersTable::class,
    CreateOrdersTable::class,
    CreateProductsTable::class,
) );

// Run pending migrations.
$ran = $migrator->run(); // ['CreateOrdersTable', 'CreateProductsTable']

// Rollback the last batch.
$rolled_back = $migrator->rollback(); // ['CreateProductsTable', 'CreateOrdersTable']

// Rollback multiple batches.
$rolled_back = $migrator->rollback( 2 );

// Fresh: drop all and re-run (WP-CLI only).
$ran = $migrator->fresh();

// Get status of all migrations.
$status = $migrator->get_status();
// [['migration' => 'CreateUsersTable', 'ran' => true], ...]

// Get only pending migrations.
$pending = $migrator->get_pending();
// ['CreateOrdersTable', 'CreateProductsTable']
```

### Fresh Guard

The `fresh()` method is destructive and will only execute in a WP-CLI context. If called outside WP-CLI, it throws a `RuntimeException`. This prevents accidental data loss in web requests.

## WP-CLI Commands

The `MigrateCommand` provides CLI access (dev-only, excluded from production builds):

```bash
# Run pending migrations
wp wpflint migrate

# Rollback the last batch
wp wpflint migrate --rollback

# Rollback multiple batches
wp wpflint migrate --rollback --steps=3

# Drop all tables and re-run (with confirmation prompt)
wp wpflint migrate --fresh

# Show migration status
wp wpflint migrate --status

# Generate a migration stub file
wp wpflint migrate --make=CreateOrdersTable
```

### Registering the Command

In a service provider:

```php
use WPFlint\Console\MigrateCommand;
use WPFlint\Database\Migrations\Migrator;
use WPFlint\Database\Migrations\MigrationRepository;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    $repository = new MigrationRepository( 'my-plugin' );
    $migrator   = new Migrator( $repository, array(
        CreateUsersTable::class,
        CreateOrdersTable::class,
    ) );

    \WP_CLI::add_command( 'wpflint migrate', new MigrateCommand( $migrator, $repository ) );
}
```

## Integration with Service Providers

A typical `MigrationServiceProvider` might look like:

```php
class MigrationServiceProvider extends ServiceProvider {

    public function register(): void {
        $this->app->singleton( MigrationRepository::class, function ( $app ) {
            return new MigrationRepository( $app->make( 'config' )->get( 'app.slug' ) );
        } );

        $this->app->singleton( Migrator::class, function ( $app ) {
            return new Migrator(
                $app->make( MigrationRepository::class ),
                $app->make( 'config' )->get( 'database.migrations', array() )
            );
        } );
    }

    public function boot(): void {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            $command = new MigrateCommand(
                $this->app->make( Migrator::class ),
                $this->app->make( MigrationRepository::class )
            );
            \WP_CLI::add_command( 'wpflint migrate', $command );
        }
    }
}
```

## Security Notes

- All queries use `$wpdb->prepare()` for parameterized values
- Table names are developer-controlled (not user input) and use `$wpdb->prefix`
- The `fresh()` command is guarded against non-CLI execution
- The `--fresh` CLI command requires explicit confirmation before proceeding
