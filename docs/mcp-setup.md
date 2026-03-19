# MCP Server Setup

The WPFlint MCP server exposes scaffolding tools for AI-assisted plugin development via the Model Context Protocol.

## Installation

```bash
cd mcp-server
npm install
```

## Connect to Claude Code

Add to your Claude Code MCP config (`~/.claude/settings.json` or project `.claude/settings.json`):

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

Restart Claude Code to load the server.

## Available Tools

### wpflint_make_migration

Generate a migration stub with auto-guessed table name.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Migration class name (PascalCase) |

Example: `name: "CreateOrdersTable"` generates a migration for the `orders` table.

### wpflint_make_model

Generate a model stub with optional companion migration.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Model class name (PascalCase) |
| `migration` | boolean | Also generate a migration (default: false) |

Example: `name: "Order", migration: true` generates both `Order.php` and a `CreateOrdersTable` migration.

### wpflint_make_provider

Generate a service provider stub with `register()` and `boot()` methods.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Provider class name (PascalCase) |

Example: `name: "OrderServiceProvider"`

### wpflint_make_controller

Generate a controller stub. Use `rest: true` for a REST API controller.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Controller class name (PascalCase) |
| `rest` | boolean | Generate REST controller (default: false) |

Example: `name: "OrderController", rest: true` generates a `RestController` with `$namespace`, `$rest_base`, `index()`, and `store()`.

### wpflint_make_middleware

Generate a middleware stub implementing `MiddlewareInterface`.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Middleware class name (PascalCase) |

Example: `name: "EnsureStoreIsOpen"`

### wpflint_make_request

Generate a form request stub with `authorize()`, `rules()`, and `sanitize()`.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Request class name (PascalCase) |

Example: `name: "StoreOrderRequest"`

### wpflint_make_event

Generate an event stub extending `Event`.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Event class name (PascalCase) |

Example: `name: "OrderPlaced"`

### wpflint_make_facade

Generate a facade stub extending `Facade`.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Facade class name (PascalCase) |

Example: `name: "Order"`

### wpflint_scaffold_plugin

Scaffold a complete plugin directory structure with main file, `composer.json`, and config.

| Parameter | Type | Description |
|-----------|------|-------------|
| `slug` | string | Plugin slug (e.g. `my-shop`) |
| `namespace` | string | PHP namespace (default: `App`) |

Generates:
- `{slug}.php` — main plugin file
- `composer.json` — with PSR-4 autoloading
- `config/app.php` — basic config
- `app/Providers/.gitkeep`
- `app/Models/.gitkeep`
- `database/migrations/.gitkeep`
