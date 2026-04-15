# Scheduler

WPFlint ships a fluent task scheduler that wraps WordPress Cron. Define your recurring tasks once in code; the framework registers and manages the WP-Cron events automatically.

## Setup

```php
use WPFlint\Scheduling\SchedulerServiceProvider;

$app->register( SchedulerServiceProvider::class );
```

This:
- Binds `'scheduler'` (`Scheduler`) in the container.
- Registers custom cron intervals via the `cron_schedules` filter.
- Hooks `init` to register all your events with WP-Cron (idempotent — safe on every request).

## Defining a schedule

Define events in a service provider's `boot()` method:

```php
use WPFlint\Scheduling\Scheduler;

class AppServiceProvider extends ServiceProvider {
    public function boot(): void {
        $scheduler = $this->app->make( 'scheduler' );

        $scheduler->call( fn() => clean_expired_transients() )
                  ->name( 'my_plugin_cleanup' )
                  ->daily();

        $scheduler->call( fn() => generate_weekly_report() )
                  ->name( 'my_plugin_weekly_report' )
                  ->weekly();

        $scheduler->job( SendDailyDigestJob::class )
                  ->name( 'my_plugin_daily_digest' )
                  ->dailyAt_equivalent()
                  ->twice_daily();
    }
}
```

Or via the global helper:

```php
wpflint_schedule( fn() => sync_inventory() )
    ->name( 'my_plugin_inventory_sync' )
    ->every_thirty_minutes();

// Get the scheduler itself
$scheduler = wpflint_schedule();
```

## Available intervals

| Method | Interval |
|---|---|
| `->every_minute()` | Every 60 seconds |
| `->every_five_minutes()` | Every 5 minutes |
| `->every_ten_minutes()` | Every 10 minutes |
| `->every_fifteen_minutes()` | Every 15 minutes |
| `->every_thirty_minutes()` | Every 30 minutes |
| `->hourly()` | Every hour (WP built-in) |
| `->every_hours(N)` | Every N hours (2–23) |
| `->twice_daily()` | Every 12 hours (WP built-in) |
| `->daily()` | Every 24 hours (WP built-in) |
| `->weekly()` | Every 7 days |
| `->monthly()` | Every 30 days |
| `->cron('my_key')` | Any custom WP-Cron interval key |

All custom intervals (`wpflint_every_minute`, `wpflint_weekly`, etc.) are registered automatically by `SchedulerServiceProvider`.

## Scheduling a job

Push a job onto the queue each time the cron fires (requires `QueueServiceProvider` to be registered):

```php
$scheduler->job( GenerateSitemapJob::class )
          ->name( 'my_plugin_sitemap' )
          ->weekly();
```

If no `QueueManager` is bound, the job's `handle()` is called directly (synchronous fallback).

## Event options

```php
$scheduler->call( fn() => my_task() )
          ->name( 'my_hook' )           // WP action hook name (required for uniqueness)
          ->description( 'My task.' )   // human-readable label
          ->daily()
          ->skip();                      // disable this event (won't register)
```

`->name()` is required when you register multiple events — without it, auto-generated names are based on the interval (one per interval). Explicitly naming events prevents hook collisions.

## Conditional scheduling

```php
$event = $scheduler->call( fn() => expensive_sync() )->name( 'sync' )->hourly();

// Disable in staging
if ( defined( 'WP_STAGING' ) && WP_STAGING ) {
    $event->skip();
}
```

## Plugin deactivation

Unschedule all events on plugin deactivation to keep the cron table clean:

```php
register_deactivation_hook( __FILE__, function () use ( $app ) {
    $app->make( 'scheduler' )->unschedule_all();
} );
```

## Running events manually

```php
// Execute callback directly (bypasses cron scheduling — useful in tests)
$event = $scheduler->call( fn() => do_task() )->name( 'task' )->hourly();
$event->run();
```

## Custom interval prefix (multi-plugin)

```php
if ( ! function_exists( 'zplane_schedule' ) ) {
    function zplane_schedule( ?callable $callback = null ) {
        return \WPFlint\Support\scheduler()->call( $callback );
    }
}
```

Generate with:

```bash
wp wpflint make:helper --prefix=zplane
```
