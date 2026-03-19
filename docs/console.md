# Console Commands

Dev-only WP-CLI commands. Excluded from production via `.distignore`.

## Available Commands

### migrate

Run database migrations.

```bash
wp wpflint migrate                    # Run pending migrations
wp wpflint migrate --rollback         # Roll back last batch
wp wpflint migrate --rollback --steps=2  # Roll back 2 batches
wp wpflint migrate --fresh            # Drop all tables, re-run all
wp wpflint migrate --status           # Show migration status
```

### make:migration

Generate a migration stub.

```bash
wp wpflint make:migration CreateOrdersTable
wp wpflint make:migration CreateOrdersTable --path=database/migrations
```

Table name is guessed from the class name: `CreateOrdersTable` → `orders`.

### make:model

Generate a model stub.

```bash
wp wpflint make:model Order
wp wpflint make:model Order --path=app/Models
wp wpflint make:model Order --migration   # Also generate migration
```

### make:provider

Generate a service provider stub.

```bash
wp wpflint make:provider OrderServiceProvider
wp wpflint make:provider OrderServiceProvider --path=app/Providers
```

### make:controller

Generate a controller stub.

```bash
wp wpflint make:controller OrderController
wp wpflint make:controller OrderController --rest    # REST API controller
wp wpflint make:controller OrderController --path=app/Http/Controllers
```

Default generates a class extending `Controller`. With `--rest`, generates a `RestController` with `$namespace`, `$rest_base`, `index()`, and `store()` methods.

### make:middleware

Generate a middleware stub.

```bash
wp wpflint make:middleware EnsureStoreIsOpen
wp wpflint make:middleware EnsureStoreIsOpen --path=app/Http/Middleware
```

Generates a class implementing `MiddlewareInterface` with a `handle()` method.

### make:request

Generate a form request stub.

```bash
wp wpflint make:request StoreOrderRequest
wp wpflint make:request StoreOrderRequest --path=app/Http/Requests
```

Generates a class extending `Request` with `authorize()`, `rules()`, and `sanitize()` methods.

### make:event

Generate an event stub.

```bash
wp wpflint make:event OrderPlaced
wp wpflint make:event OrderPlaced --path=app/Events
```

Generates a class extending `Event` with an empty constructor.

### make:facade

Generate a facade stub.

```bash
wp wpflint make:facade Order
wp wpflint make:facade Order --path=app/Facades
```

Generates a class extending `Facade` with a `get_facade_accessor()` method.

### cache:clear

Clear the application cache.

```bash
wp wpflint cache:clear
wp wpflint cache:clear --tag=orders
```

## Base Command Class

All commands extend `WPFlint\Console\Command` which provides:

| Method | Description |
|--------|-------------|
| `info($msg)` | Print informational line |
| `success($msg)` | Print success message |
| `error($msg)` | Print error and exit |
| `warning($msg)` | Print warning |
| `confirm($msg)` | Prompt for confirmation |
| `table($headers, $rows)` | Display tabular data |
| `progress($msg, $count)` | Create progress bar |
| `snake_case($value)` | Convert PascalCase to snake_case |
| `write_file($path, $content)` | Write file with directory creation |

## MCP Server

A Node.js MCP server in `mcp-server/` exposes the same scaffolding tools for AI-assisted development:

- `wpflint_make_migration` — Generate migration stub
- `wpflint_make_model` — Generate model stub (with optional migration)
- `wpflint_make_provider` — Generate provider stub
- `wpflint_make_controller` — Generate controller stub (with optional `--rest`)
- `wpflint_make_middleware` — Generate middleware stub
- `wpflint_make_request` — Generate form request stub
- `wpflint_make_event` — Generate event stub
- `wpflint_make_facade` — Generate facade stub
- `wpflint_scaffold_plugin` — Scaffold a full plugin structure

### Usage

```bash
cd mcp-server && npm install && npm start
```

Add to your Claude Code MCP config:

```json
{
  "mcpServers": {
    "wpflint": {
      "command": "node",
      "args": ["/path/to/wpflint/mcp-server/index.js"]
    }
  }
}
```
