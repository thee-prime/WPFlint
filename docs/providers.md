# Service Providers

Service providers are the central place to register bindings and boot services.

## Creating a provider

```php
use WPFlint\Providers\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider {

    public function register(): void {
        $this->app->singleton( PaymentGateway::class, StripeGateway::class );
    }

    public function boot(): void {
        add_action( 'init', [ $this, 'register_webhooks' ] );
    }
}
```

## Deferred providers

Set `$defer = true` and implement `provides()` to lazy-load the provider only when one of its provided abstracts is resolved:

```php
class ReportServiceProvider extends ServiceProvider {

    public bool $defer = true;

    public function register(): void {
        $this->app->singleton( ReportGenerator::class );
    }

    public function provides(): array {
        return [ ReportGenerator::class ];
    }
}
```

Deferred providers are resolved on:
- First `make()` call for any abstract they provide
- `wp_loaded` hook (all remaining deferred providers boot)

## Lifecycle methods

| Method | Purpose |
|---|---|
| `register()` | Bind services into the container (abstract) |
| `boot()` | Hook registrations, post-registration logic |
| `provides()` | List of abstracts this provider registers (deferred only) |
| `is_registered()` | Check if provider has been registered |
| `is_booted()` | Check if provider has been booted |

## Files

| File | Purpose |
|---|---|
| `src/Providers/ServiceProvider.php` | Abstract base class |
| `src/Providers/FrameworkServiceProvider.php` | Core framework bindings |
