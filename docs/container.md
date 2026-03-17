# Container

PSR-11 compliant IoC container with auto-resolution, singletons, and contextual bindings.

## Quick start

```php
use WPFlint\Container\Container;

$container = new Container();
```

## Binding

```php
// Simple binding (new instance each time).
$container->bind(LoggerInterface::class, FileLogger::class);

// Singleton (same instance every time).
$container->singleton(CacheManager::class, function ($app) {
    return new CacheManager($app);
});

// Register an existing instance.
$container->instance('config', $configObject);
```

## Resolving

```php
// Resolve with auto-wiring.
$logger = $container->make(LoggerInterface::class);

// PSR-11 get() is an alias for make().
$logger = $container->get(LoggerInterface::class);

// Check if a binding exists.
$container->has(LoggerInterface::class); // true
```

## Auto-resolution

Constructor dependencies are resolved automatically via reflection:

```php
class OrderService {
    public function __construct(LoggerInterface $logger, CacheManager $cache) {}
}

// Both $logger and $cache are resolved from the container.
$service = $container->make(OrderService::class);
```

Parameters with default values are used when no type-hint is available.

## Contextual bindings

Give different implementations to different consumers:

```php
$container->when(OrderService::class)
    ->needs(LoggerInterface::class)
    ->give(OrderLogger::class);

$container->when(PaymentService::class)
    ->needs(LoggerInterface::class)
    ->give(PaymentLogger::class);
```

Closures work too:

```php
$container->when(OrderService::class)
    ->needs(LoggerInterface::class)
    ->give(function ($app) {
        return new FileLogger('/var/log/orders.log');
    });
```

## Removing bindings

```php
$container->forget(LoggerInterface::class);
```

## Error handling

| Exception | When |
|---|---|
| `NotFoundException` | Class does not exist |
| `ContainerException` | Class not instantiable, circular dependency, or unresolvable primitive parameter |

Circular dependencies are detected automatically:

```php
// A requires B, B requires A → ContainerException thrown.
```

## Files

| File | Purpose |
|---|---|
| `src/Container/ContainerInterface.php` | PSR-11 interface (inlined) |
| `src/Container/ContainerExceptionInterface.php` | PSR-11 exception interface |
| `src/Container/NotFoundExceptionInterface.php` | PSR-11 not-found interface |
| `src/Container/ContainerException.php` | General container exception |
| `src/Container/NotFoundException.php` | Entry not found exception |
| `src/Container/ContextualBindingBuilder.php` | Fluent builder for `when()->needs()->give()` |
| `src/Container/Container.php` | Main container implementation |
