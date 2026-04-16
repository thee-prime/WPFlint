# MCP Server Setup

The WPFlint MCP server exposes the complete framework to AI assistants via the Model Context Protocol — embedded API docs, code generators, and pattern examples for every module.

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
      "args": ["/absolute/path/to/wpflint/mcp-server/index.js"]
    }
  }
}
```

Restart Claude Code to load the server.

---

## Resources (read-only knowledge base)

Resources let AI assistants read embedded framework documentation before generating code. Access them by URI.

| URI | Contents |
|-----|----------|
| `wpflint://overview` | Full framework guide: architecture, bootstrap, PHP 7.4 rules, WP compliance |
| `wpflint://patterns` | Key coding patterns: Router, Controller, Request, Model, Cache, Events |
| `wpflint://api/{module}` | Per-module API reference (see module names below) |

### Available modules for `wpflint://api/{module}`

`container`, `http`, `database`, `cache`, `events`, `admin`, `blocks`, `widgets`,
`assets`, `lifecycle`, `view`, `mail`, `shortcodes`, `rest_auth`, `logging`, `queue`, `scheduling`

---

## Tools — Code Generators

### Discovery

#### `wpflint_framework_overview`
Returns the complete framework overview. AI should call this when starting a new plugin project.

#### `wpflint_module_docs`
Returns API documentation for a specific module.

| Parameter | Type | Description |
|-----------|------|-------------|
| `module` | enum | Module name (see list above) |

---

### Core Scaffolding

#### `wpflint_scaffold_plugin`
Scaffold a new WPFlint plugin with all starter files.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `slug` | string | — | Plugin slug, e.g. `my-shop` |
| `namespace` | string | `App` | PHP root namespace |

Generates: main plugin file, `composer.json`, `config/app.php`, `AppServiceProvider`, directory placeholders.

---

#### `wpflint_make_provider`
Generate a service provider with `register()` and `boot()`.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Class name in PascalCase, e.g. `OrderServiceProvider` |

---

#### `wpflint_make_migration`
Generate a database migration stub with auto-guessed table name. Filename includes timestamp.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Class name, e.g. `CreateOrdersTable` |

---

#### `wpflint_make_model`
Generate an ORM model stub with optional companion migration.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | — | Class name, e.g. `Order` |
| `migration` | boolean | false | Also generate migration |
| `fillable` | string[] | `[]` | Fillable field names |
| `casts` | object | `{}` | Casts map, e.g. `{total: "float"}` |

---

#### `wpflint_make_controller`
Generate a controller. Use `rest: true` for REST API controllers.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | — | Class name, e.g. `OrderController` |
| `rest` | boolean | false | Extend `RestController` instead |

REST controllers include `$namespace`, `$rest_base`, `index()`, `store()`, and permission checks.

---

#### `wpflint_make_middleware`
Generate a middleware implementing `MiddlewareInterface`.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Class name, e.g. `EnsureStoreIsOpen` |

---

#### `wpflint_make_request`
Generate a form request with `authorize()`, `rules()`, and `sanitize()`.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | — | Class name, e.g. `StoreOrderRequest` |
| `rules` | object | `{}` | Validation rules map |
| `authorize_cap` | string | — | Capability for `authorize()` |

---

#### `wpflint_make_event`
Generate an event class extending `Event`.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | — | Class name, e.g. `OrderPlaced` |
| `properties` | string[] | `[]` | Constructor property names |

---

#### `wpflint_make_listener`
Generate an event listener with a typed `handle()` method.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | — | Class name, e.g. `SendOrderConfirmation` |
| `event` | string | `""` | Event class to type-hint |

---

#### `wpflint_make_facade`
Generate a facade extending `Facade`.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | — | Class name, e.g. `OrderFacade` |
| `accessor` | string | `""` | Container binding key |

---

#### `wpflint_make_rule`
Generate a custom validation rule implementing `RuleInterface`.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | — | Class name, e.g. `UniqueEmail` |
| `message` | string | `"The :attribute field is invalid."` | Validation error message |

---

#### `wpflint_make_command`
Generate a WP-CLI command extending `Command`.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Class name, e.g. `SyncInventoryCommand` |

---

#### `wpflint_make_job`
Generate an async job for the queue system.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | — | Class name, e.g. `SendWelcomeEmail` |
| `queue` | string | `default` | Queue name |
| `tries` | number | 3 | Max retries |

---

### HTTP / REST

#### `wpflint_router_ajax`
Generate AJAX route registration with middleware and JS usage example.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `action` | string | — | AJAX action name |
| `controller` | string | `MyController` | Controller class |
| `method` | string | `store` | Controller method |
| `middleware` | string[] | `[]` | Middleware list |
| `nopriv` | boolean | false | Allow unauthenticated users |

---

#### `wpflint_rest_routes`
Generate REST route registration using `Router::rest()` with `RestAuth` permission callbacks.

| Parameter | Type | Description |
|-----------|------|-------------|
| `namespace` | string | Plugin namespace slug |
| `version` | number | API version (default: 1) |
| `routes` | array | Route definitions (method, path, controller, action, permission) |

---

#### `wpflint_rest_auth`
Generate REST auth usage examples with all `RestAuth` factory methods and a quick-reference table.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `namespace` | string | `my-plugin` | Plugin namespace slug |
| `version` | number | 1 | API version |

---

### Admin UI

#### `wpflint_make_admin_page`
Generate an `AdminPage` registration stub. Supports top-level and submenu pages.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `slug` | string | — | Page slug |
| `title` | string | — | Menu title |
| `parent` | string | `""` | Parent slug (empty = top-level) |
| `capability` | string | `manage_options` | Required capability |
| `icon` | string | `""` | Dashicon for top-level pages |

---

#### `wpflint_make_settings`
Generate a Settings API registration with sections and fields.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `option_group` | string | — | Option group name |
| `page_title` | string | `My Plugin Settings` | Page title |
| `sections` | string[] | `["general"]` | Section slugs |
| `fields` | string[] | `["api_key"]` | Field key names |

---

#### `wpflint_make_metabox`
Generate a `MetaBox` registration stub with fields. Nonce verification and save hooks are automatic.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | Metabox ID |
| `title` | string | — | Metabox title |
| `screen` | string | `post` | Post type slug |
| `fields` | array | `[]` | Field definitions (id, label, type, description) |

---

#### `wpflint_make_notice`
Generate an admin notice snippet.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `message` | string | — | Notice message |
| `type` | enum | `success` | `success`, `error`, `warning`, `info` |
| `dismissible` | boolean | true | Allow user to dismiss |

---

### Content Types

#### `wpflint_make_post_type`
Generate a fluent `PostType` registration snippet.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `slug` | string | — | Post type slug |
| `singular` | string | — | Singular label |
| `plural` | string | auto | Plural label |
| `public` | boolean | true | Public post type |
| `show_in_rest` | boolean | true | Expose via REST |
| `supports` | string[] | — | Supported features |
| `icon` | string | — | Dashicon |
| `hierarchical` | boolean | false | Page-like hierarchy |

---

#### `wpflint_make_taxonomy`
Generate a fluent `Taxonomy` registration snippet.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `slug` | string | — | Taxonomy slug |
| `singular` | string | — | Singular label |
| `plural` | string | auto | Plural label |
| `post_types` | string[] | — | Post types to attach |
| `hierarchical` | boolean | false | Category-like |
| `show_in_rest` | boolean | true | Expose via REST |

---

#### `wpflint_make_meta_field`
Generate a fluent `MetaField` registration for post, term, user, or comment meta.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `object_type` | enum | — | `post`, `term`, `user`, `comment` |
| `subtype` | string | `""` | Post type or taxonomy slug |
| `key` | string | — | Meta key |
| `type` | string | `string` | Value type |
| `single` | boolean | true | Single value field |
| `show_in_rest` | boolean | false | Expose via REST |

---

#### `wpflint_make_shortcode`
Generate a `Shortcode` registration stub with attribute defaults and output scaffolding.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `tag` | string | — | Shortcode tag, e.g. `my_orders` |
| `atts` | string[] | `[]` | Attribute names |

---

#### `wpflint_make_block`
Generate a Gutenberg block registration using `Block` builder.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | — | `namespace/block-name` |
| `title` | string | — | Block title |
| `category` | enum | `design` | Block category |
| `dynamic` | boolean | true | PHP server-side render |
| `attributes` | string[] | — | Attribute names |

---

#### `wpflint_make_widget`
Generate an `AbstractWidget` subclass stub.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | — | Class name, e.g. `RecentPostsWidget` |
| `title` | string | — | Widget title |
| `description` | string | `"A custom widget."` | Widget description |

---

### Frontend

#### `wpflint_make_asset`
Generate script or style enqueue code using WPFlint Asset builders.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `handle` | string | — | Asset handle |
| `src` | string | — | Source URL or PHP expression |
| `type` | enum | `script` | `script` or `style` |
| `deps` | string[] | `[]` | Dependency handles |
| `version` | string | `1.0.0` | Asset version |
| `in_footer` | boolean | true | Enqueue in footer |
| `localize_key` | string | `""` | JS object name for localize |
| `localize_data` | string | ajax_url + nonce | PHP data array body |

---

#### `wpflint_make_view`
Generate `View::render()` usage code and a starter template file.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `view_name` | string | — | View path, e.g. `orders/index` |
| `variables` | string[] | `[]` | Variable names to pass |

---

### System

#### `wpflint_make_lifecycle`
Generate activation, deactivation, and uninstall hook code.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `plugin_file` | string | `__FILE__` | Main plugin file expression |
| `hooks` | enum[] | all three | Which hooks to generate |

---

#### `wpflint_logging_usage`
Show Logger setup and all usage examples.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `channel` | string | `my-plugin` | Log channel name |
| `min_level` | enum | `debug` | Minimum log level |

---

#### `wpflint_schedule_usage`
Show Scheduler setup and schedule definition examples.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `plugin_slug` | string | `my-plugin` | Plugin slug for examples |

---

## Typical AI Workflow

1. **Start** — call `wpflint_framework_overview` to load full context
2. **Module deep-dive** — call `wpflint_module_docs` for the specific module
3. **Generate** — call the matching `wpflint_make_*` tool with the right parameters
4. **Wire up** — place generated files in the suggested paths, register providers in bootstrap
