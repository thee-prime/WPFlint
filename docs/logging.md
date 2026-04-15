# Logging

WPFlint ships a PSR-3 compatible `Logger` that writes structured entries to the WordPress debug log (`WP_DEBUG_LOG`). It supports eight log levels, placeholder interpolation, minimum-level filtering, and automatic exception formatting.

## Setup

Register the provider in your plugin bootstrap:

```php
use WPFlint\Logging\LoggingServiceProvider;

$app->register( LoggingServiceProvider::class );
```

Configure via constants (define before `$app->bootstrap()`):

```php
define( 'WPFLINT_LOG_CHANNEL', 'my-plugin' );   // default: 'wpflint'
define( 'WPFLINT_LOG_LEVEL',   'warning' );     // default: 'debug' (all levels)
```

## Basic usage

```php
use WPFlint\Logging\LoggerInterface;

// Resolve from container
$logger = $app->make( LoggerInterface::class );

$logger->info( 'Plugin booted.' );
$logger->warning( 'Slow query detected.', [ 'ms' => 850 ] );
$logger->error( 'Payment failed', [ 'exception' => $e ] );
```

### Global helper

```php
wpflint_log( 'Order {id} placed.', [ 'id' => $order_id ] );
wpflint_log( 'Retry attempt {n}.', [ 'n' => 3 ], 'warning' );
```

## Log levels (PSR-3)

From most to least severe:

| Constant | Helper method | Severity |
|---|---|---|
| `LogLevel::EMERGENCY` | `$logger->emergency()` | 0 |
| `LogLevel::ALERT` | `$logger->alert()` | 1 |
| `LogLevel::CRITICAL` | `$logger->critical()` | 2 |
| `LogLevel::ERROR` | `$logger->error()` | 3 |
| `LogLevel::WARNING` | `$logger->warning()` | 4 |
| `LogLevel::NOTICE` | `$logger->notice()` | 5 |
| `LogLevel::INFO` | `$logger->info()` | 6 |
| `LogLevel::DEBUG` | `$logger->debug()` | 7 |

## Entry format

Each log line follows this format:

```
[2025-04-15 14:22:01] my-plugin.ERROR: Payment failed RuntimeException: disk full in /app/Payments.php:42
```

Components:
- `[timestamp]` — UTC datetime
- `channel.LEVEL` — channel name + uppercase level
- message — interpolated message text
- context tail — exception details and/or JSON-encoded extra context keys

## Placeholder interpolation

PSR-3 §1.2: `{key}` tokens in the message are replaced by matching context values (string-castable only):

```php
$logger->info( 'User {id} logged in from {ip}.', [
    'id' => get_current_user_id(),
    'ip' => $_SERVER['REMOTE_ADDR'],
] );
// → [2025-04-15 14:22:01] my-plugin.INFO: User 42 logged in from 127.0.0.1.
```

Array values are not interpolated — they appear in the JSON context tail instead.

## Exception context

Pass an exception under the `'exception'` key for automatic formatting:

```php
try {
    process_payment( $order );
} catch ( \Exception $e ) {
    $logger->error( 'Payment processing failed', [ 'exception' => $e ] );
}
// → [timestamp] my-plugin.ERROR: Payment processing failed PaymentException: card declined in /app/PaymentGateway.php:88
```

## Minimum level filtering

Only entries at or above the minimum level are written:

```php
// Suppress debug and info in production
$logger = new Logger( 'my-plugin', LogLevel::WARNING );
$logger->debug( 'suppressed' );   // dropped
$logger->warning( 'written' );    // written

// Fluent clone — original unchanged
$prod_logger = $logger->with_min_level( LogLevel::ERROR );
```

## Channel scoping

```php
$payment_logger = $logger->channel( 'payments' );
$payment_logger->info( 'Refund issued' );
// → [timestamp] payments.INFO: Refund issued
```

## NullLogger

Use `NullLogger` in tests or as a safe default when no logger is configured:

```php
use WPFlint\Logging\NullLogger;

$logger = new NullLogger(); // silently discards everything
```

## wpflint_dd() — dump and die

A development utility that dumps variables to the screen and halts execution (equivalent to Laravel's `dd()`):

```php
wpflint_dd( $order, $cart, get_option('my_plugin_settings') );
```

Output is wrapped in a styled `<pre>` block when output has not yet started, or plain text in CLI/WP-CLI contexts. **Never use in production code.**

### Customising the prefix (multi-plugin setups)

Plugin authors can define their own prefix functions that delegate to the same internals:

```php
// In my-plugin/app/helpers.php
if ( ! function_exists( 'zplane_log' ) ) {
    function zplane_log( string $message, array $context = [], string $level = 'debug' ): void {
        \WPFlint\Support\log_message( $message, $context, $level );
    }
}
if ( ! function_exists( 'zplane_dd' ) ) {
    function zplane_dd( ...$values ): void {
        \WPFlint\Support\dump_and_die( ...$values );
    }
}
```

Generate this boilerplate with:

```bash
wp wpflint make:helper --prefix=zplane
```

## Binding your own logger

Any class implementing `LoggerInterface` can be swapped in:

```php
$app->singleton( 'logger', fn() => new MyCustomLogger() );
$app->singleton( \WPFlint\Logging\LoggerInterface::class, fn() => $app->make('logger') );
```
