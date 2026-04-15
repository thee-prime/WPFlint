#!/usr/bin/env node
/**
 * WPFlint MCP Server
 *
 * Exposes scaffolding tools for WPFlint projects via Model Context Protocol.
 * Tools: wpflint_make_migration, wpflint_make_model, wpflint_make_provider, wpflint_make_controller,
 *        wpflint_make_middleware, wpflint_make_request, wpflint_make_event, wpflint_make_facade,
 *        wpflint_make_listener, wpflint_make_command, wpflint_make_rule,
 *        wpflint_logging_usage, wpflint_make_job, wpflint_schedule_usage,
 *        wpflint_scaffold_plugin
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Convert PascalCase to snake_case.
 */
function snakeCase(value) {
  return value
    .replace(/([a-z])([A-Z])/g, "$1_$2")
    .replace(/([A-Z]+)([A-Z][a-z])/g, "$1_$2")
    .toLowerCase();
}

/**
 * Guess table name from migration class name.
 * CreateOrdersTable -> orders
 * CreateUserProfilesTable -> user_profiles
 */
function guessTableName(name) {
  let stripped = name.replace(/^Create/, "").replace(/Table$/, "");
  return snakeCase(stripped);
}

/**
 * Generate a timestamp prefix for migration filenames.
 */
function migrationTimestamp() {
  const now = new Date();
  const pad = (n, w = 2) => String(n).padStart(w, "0");
  return [
    now.getUTCFullYear(),
    pad(now.getUTCMonth() + 1),
    pad(now.getUTCDate()),
    pad(now.getUTCHours()) + pad(now.getUTCMinutes()) + pad(now.getUTCSeconds()),
  ].join("_");
}

// ---------------------------------------------------------------------------
// Stub generators
// ---------------------------------------------------------------------------

function migrationStub(name) {
  const table = guessTableName(name);
  return `<?php

declare(strict_types=1);

use WPFlint\\Database\\Migrations\\Migration;
use WPFlint\\Database\\Schema\\Blueprint;

class ${name} extends Migration {

\tpublic function up(): void {
\t\t$this->schema()->create( '${table}', function ( Blueprint $table ) {
\t\t\t$table->big_increments( 'id' );
\t\t\t$table->timestamps();
\t\t} );
\t}

\tpublic function down(): void {
\t\t$this->schema()->drop( '${table}' );
\t}
}
`;
}

function modelStub(name) {
  const table = snakeCase(name) + "s";
  return `<?php

declare(strict_types=1);

use WPFlint\\Database\\ORM\\Model;

class ${name} extends Model {

\tprotected static string $table = '${table}';

\tprotected array $fillable = array();

\tprotected array $casts = array();
}
`;
}

function providerStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Providers\\ServiceProvider;

class ${name} extends ServiceProvider {

\tpublic function register(): void {
\t\t//
\t}

\tpublic function boot(): void {
\t\t//
\t}
}
`;
}

function controllerStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Http\\Controller;

class ${name} extends Controller {

\tpublic function __construct() {
\t\t//
\t}
}
`;
}

function restControllerStub(name) {
  const base = snakeCase(name.replace(/Controller$/, ""));
  return `<?php

declare(strict_types=1);

use WPFlint\\Http\\RestController;

class ${name} extends RestController {

\tprotected string $namespace = 'my-plugin/v1';

\tprotected string $rest_base = '${base}';

\tpublic function index( \\WP_REST_Request $request ): \\WP_REST_Response {
\t\treturn $this->respond( array() );
\t}

\tpublic function store( \\WP_REST_Request $request ): \\WP_REST_Response {
\t\treturn $this->respond( array(), 201 );
\t}
}
`;
}

function middlewareStub(name) {
  return `<?php

declare(strict_types=1);

use Closure;
use WPFlint\\Http\\Request;
use WPFlint\\Http\\Middleware\\MiddlewareInterface;

class ${name} implements MiddlewareInterface {

\tpublic function handle( Request $request, Closure $next ) {
\t\treturn $next( $request );
\t}
}
`;
}

function requestStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Http\\Request;

class ${name} extends Request {

\tpublic function authorize(): bool {
\t\treturn false;
\t}

\tpublic function rules(): array {
\t\treturn array();
\t}

\tpublic function sanitize(): array {
\t\treturn array();
\t}
}
`;
}

function eventStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Events\\Event;

class ${name} extends Event {

\tpublic function __construct() {
\t\t//
\t}
}
`;
}

function facadeStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Facades\\Facade;

class ${name} extends Facade {

\tprotected static function get_facade_accessor(): string {
\t\treturn '';
\t}
}
`;
}

function listenerStub(name, event) {
  const typeHint = event ? `${event} $event` : '$event';
  const useEvent = event ? `\nuse ${event};\n` : '';
  return `<?php

declare(strict_types=1);
${useEvent}
class ${name} {

\t/**
\t * Handle the event.
\t *
\t * @param ${typeHint}
\t * @return void
\t */
\tpublic function handle( ${typeHint} ): void {
\t\t//
\t}
}
`;
}

function commandStub(name) {
  const commandName = snakeCase(name.replace(/Command$/, ''));
  return `<?php

declare(strict_types=1);

use WPFlint\\Console\\Command;

/**
 * ## EXAMPLES
 *
 *     wp wpflint ${commandName}
 */
class ${name} extends Command {

\t/**
\t * Execute the command.
\t *
\t * @param array $args       Positional arguments.
\t * @param array $assoc_args Associative arguments.
\t * @return void
\t */
\tpublic function __invoke( array $args, array $assoc_args ): void {
\t\t$this->info( __( 'Running ${commandName}...', 'text-domain' ) );

\t\t// TODO: implement command logic.

\t\t$this->success( __( 'Done.', 'text-domain' ) );
\t}
}
`;
}

function ruleStub(name) {
  return `<?php

declare(strict_types=1);

use WPFlint\\Validation\\Rules\\RuleInterface;

class ${name} implements RuleInterface {

\t/**
\t * Determine if the given value passes this rule.
\t *
\t * @param mixed $value The value being validated.
\t * @return bool
\t */
\tpublic function passes( $value ): bool {
\t\t// TODO: implement rule logic.
\t\treturn true;
\t}

\t/**
\t * Return the validation error message.
\t *
\t * Use :attribute as a placeholder for the field name.
\t *
\t * @return string
\t */
\tpublic function message(): string {
\t\treturn __( 'The :attribute field is invalid.', 'text-domain' );
\t}
}
`;
}

function scaffoldPlugin(slug, namespace) {
  const mainFile = `<?php
/**
 * Plugin Name: ${slug}
 * Description: A WPFlint-powered plugin.
 * Version:     1.0.0
 * Text Domain: ${slug}
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
\texit;
}

require_once __DIR__ . '/vendor/autoload.php';

use WPFlint\\Application;

$app = Application::get_instance();
$app->boot();
`;

  const composerJson = JSON.stringify(
    {
      name: `vendor/${slug}`,
      description: `${slug} plugin`,
      type: "wordpress-plugin",
      require: {
        php: ">=7.4",
      },
      autoload: {
        "psr-4": {
          [`${namespace}\\`]: "app/",
        },
      },
    },
    null,
    2
  );

  const configApp = `<?php

declare(strict_types=1);

return array(
\t'name'    => '${slug}',
\t'version' => '1.0.0',
);
`;

  return {
    [`${slug}.php`]: mainFile,
    "composer.json": composerJson,
    "config/app.php": configApp,
    "app/Providers/.gitkeep": "",
    "app/Models/.gitkeep": "",
    "database/migrations/.gitkeep": "",
  };
}

// ---------------------------------------------------------------------------
// MCP Server
// ---------------------------------------------------------------------------

const server = new McpServer({
  name: "wpflint",
  version: "1.0.0",
});

server.tool(
  "wpflint_make_migration",
  "Generate a WPFlint migration stub. Returns the file content and suggested filename.",
  {
    name: z.string().describe("Migration class name in PascalCase, e.g. CreateOrdersTable"),
  },
  async ({ name }) => {
    const filename = `${migrationTimestamp()}_${snakeCase(name)}.php`;
    const content = migrationStub(name);
    return {
      content: [
        {
          type: "text",
          text: `**Filename:** \`database/migrations/${filename}\`\n\n\`\`\`php\n${content}\`\`\``,
        },
      ],
    };
  }
);

server.tool(
  "wpflint_make_model",
  "Generate a WPFlint model stub. Optionally generates a companion migration.",
  {
    name: z.string().describe("Model class name in PascalCase, e.g. Order"),
    migration: z.boolean().optional().default(false).describe("Also generate a migration stub"),
  },
  async ({ name, migration }) => {
    let text = "";
    const modelContent = modelStub(name);
    text += `**Filename:** \`app/Models/${name}.php\`\n\n\`\`\`php\n${modelContent}\`\`\``;

    if (migration) {
      const migrationName = `Create${name}sTable`;
      const migrationFilename = `${migrationTimestamp()}_${snakeCase(migrationName)}.php`;
      const migrationContent = migrationStub(migrationName);
      text += `\n\n---\n\n**Filename:** \`database/migrations/${migrationFilename}\`\n\n\`\`\`php\n${migrationContent}\`\`\``;
    }

    return { content: [{ type: "text", text }] };
  }
);

server.tool(
  "wpflint_make_provider",
  "Generate a WPFlint service provider stub.",
  {
    name: z.string().describe("Provider class name in PascalCase, e.g. OrderServiceProvider"),
  },
  async ({ name }) => {
    const content = providerStub(name);
    return {
      content: [
        {
          type: "text",
          text: `**Filename:** \`app/Providers/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
        },
      ],
    };
  }
);

server.tool(
  "wpflint_make_controller",
  "Generate a WPFlint controller stub. Use --rest for a REST API controller.",
  {
    name: z.string().describe("Controller class name in PascalCase, e.g. OrderController"),
    rest: z.boolean().optional().default(false).describe("Generate a REST API controller"),
  },
  async ({ name, rest }) => {
    const content = rest ? restControllerStub(name) : controllerStub(name);
    const dir = "app/Http/Controllers";
    return {
      content: [
        {
          type: "text",
          text: `**Filename:** \`${dir}/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
        },
      ],
    };
  }
);

server.tool(
  "wpflint_make_middleware",
  "Generate a WPFlint middleware stub implementing MiddlewareInterface.",
  {
    name: z.string().describe("Middleware class name in PascalCase, e.g. EnsureStoreIsOpen"),
  },
  async ({ name }) => {
    const content = middlewareStub(name);
    return {
      content: [
        {
          type: "text",
          text: `**Filename:** \`app/Http/Middleware/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
        },
      ],
    };
  }
);

server.tool(
  "wpflint_make_request",
  "Generate a WPFlint form request stub with authorize(), rules(), and sanitize().",
  {
    name: z.string().describe("Request class name in PascalCase, e.g. StoreOrderRequest"),
  },
  async ({ name }) => {
    const content = requestStub(name);
    return {
      content: [
        {
          type: "text",
          text: `**Filename:** \`app/Http/Requests/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
        },
      ],
    };
  }
);

server.tool(
  "wpflint_make_event",
  "Generate a WPFlint event stub extending Event.",
  {
    name: z.string().describe("Event class name in PascalCase, e.g. OrderPlaced"),
  },
  async ({ name }) => {
    const content = eventStub(name);
    return {
      content: [
        {
          type: "text",
          text: `**Filename:** \`app/Events/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
        },
      ],
    };
  }
);

server.tool(
  "wpflint_make_facade",
  "Generate a WPFlint facade stub extending Facade.",
  {
    name: z.string().describe("Facade class name in PascalCase, e.g. Order"),
  },
  async ({ name }) => {
    const content = facadeStub(name);
    return {
      content: [
        {
          type: "text",
          text: `**Filename:** \`app/Facades/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
        },
      ],
    };
  }
);

server.tool(
  "wpflint_make_listener",
  "Generate a WPFlint event listener stub with a handle() method.",
  {
    name: z.string().describe("Listener class name in PascalCase, e.g. SendOrderConfirmation"),
    event: z.string().optional().default("").describe("Event class to type-hint in handle(), e.g. OrderPlaced"),
  },
  async ({ name, event }) => {
    const content = listenerStub(name, event);
    return {
      content: [
        {
          type: "text",
          text: `**Filename:** \`app/Listeners/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
        },
      ],
    };
  }
);

server.tool(
  "wpflint_make_command",
  "Generate a custom WP-CLI command stub extending WPFlint Command.",
  {
    name: z.string().describe("Command class name in PascalCase, e.g. SyncInventoryCommand"),
  },
  async ({ name }) => {
    const content = commandStub(name);
    return {
      content: [
        {
          type: "text",
          text: `**Filename:** \`app/Console/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
        },
      ],
    };
  }
);

server.tool(
  "wpflint_make_rule",
  "Generate a custom validation rule stub implementing RuleInterface.",
  {
    name: z.string().describe("Rule class name in PascalCase, e.g. UniqueEmail or PhoneNumber"),
  },
  async ({ name }) => {
    const content = ruleStub(name);
    return {
      content: [
        {
          type: "text",
          text: `**Filename:** \`app/Rules/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
        },
      ],
    };
  }
);

server.tool(
  "wpflint_logging_usage",
  "Show WPFlint Logger setup and usage examples for a plugin.",
  {
    channel: z.string().optional().default("my-plugin").describe("Log channel name (plugin slug)"),
    min_level: z.enum(["debug","info","notice","warning","error","critical","alert","emergency"]).optional().default("debug").describe("Minimum log level to write"),
  },
  async ({ channel, min_level }) => {
    const text = `## WPFlint Logger — ${channel}

### constants (define before bootstrap)
\`\`\`php
define( 'WPFLINT_LOG_CHANNEL', '${channel}' );
define( 'WPFLINT_LOG_LEVEL',   '${min_level}' );
\`\`\`

### register provider
\`\`\`php
use WPFlint\\Logging\\LoggingServiceProvider;
$app->register( LoggingServiceProvider::class );
\`\`\`

### usage
\`\`\`php
use WPFlint\\Logging\\LoggerInterface;
use WPFlint\\Logging\\LogLevel;

$logger = $app->make( LoggerInterface::class );
$logger->info( 'Plugin booted.' );
$logger->warning( 'Slow query {ms}ms.', [ 'ms' => 850 ] );
$logger->error( 'Payment failed', [ 'exception' => $e ] );

// global helper
wpflint_log( 'Order {id} placed.', [ 'id' => $orderId ] );
wpflint_log( 'Auth failed', [ 'user' => $userId ], 'warning' );

// dump and die (dev only)
wpflint_dd( $someVariable, $anotherVariable );
\`\`\`

### entry format
\`[YYYY-MM-DD HH:MM:SS] ${channel}.LEVEL: Interpolated message {context_json?}\`
`;
    return { content: [{ type: "text", text }] };
  }
);

server.tool(
  "wpflint_make_job",
  "Generate a WPFlint job stub for the async queue.",
  {
    name: z.string().describe("Job class name in PascalCase, e.g. SendWelcomeEmail"),
    queue: z.string().optional().default("default").describe("Queue name"),
    tries: z.number().optional().default(3).describe("Max retry attempts"),
  },
  async ({ name, queue, tries }) => {
    const content = `<?php

declare(strict_types=1);

use WPFlint\\Queue\\Job;

class ${name} extends Job {

\tprotected string $queue        = '${queue}';
\tprotected int    $max_attempts = ${tries};

\t/**
\t * Execute the job.
\t *
\t * @return void
\t */
\tpublic function handle(): void {
\t\t// TODO: implement job logic.
\t}

\t/**
\t * Handle a job failure after all retries are exhausted.
\t *
\t * @param \\Throwable $exception The last exception thrown.
\t * @return void
\t */
\tpublic function failed( \\Throwable $exception ): void {
\t\t// TODO: alert / compensate on permanent failure.
\t}
}
`;
    return {
      content: [{
        type: "text",
        text: `**Filename:** \`app/Jobs/${name}.php\`\n\n\`\`\`php\n${content}\`\`\``,
      }],
    };
  }
);

server.tool(
  "wpflint_schedule_usage",
  "Show WPFlint Scheduler setup and usage examples.",
  {
    plugin_slug: z.string().optional().default("my-plugin").describe("Plugin slug for examples"),
  },
  async ({ plugin_slug }) => {
    const text = `## WPFlint Scheduler — ${plugin_slug}

### register provider
\`\`\`php
use WPFlint\\Scheduling\\SchedulerServiceProvider;
$app->register( SchedulerServiceProvider::class );
\`\`\`

### define schedule (in a ServiceProvider boot())
\`\`\`php
$scheduler = $this->app->make( 'scheduler' );

// Callable
$scheduler->call( fn() => clean_expired_transients() )
          ->name( '${plugin_slug}_cleanup' )
          ->daily();

// Job class (dispatched to queue)
$scheduler->job( GenerateReportJob::class )
          ->name( '${plugin_slug}_report' )
          ->weekly();

// Helper
wpflint_schedule( fn() => sync_inventory() )
    ->name( '${plugin_slug}_inventory_sync' )
    ->every_thirty_minutes();
\`\`\`

### deactivation cleanup
\`\`\`php
register_deactivation_hook( __FILE__, function() use ($app) {
    $app->make( 'scheduler' )->unschedule_all();
});
\`\`\`

### intervals
every_minute · every_five_minutes · every_ten_minutes · every_fifteen_minutes
every_thirty_minutes · hourly · every_hours(N) · twice_daily · daily · weekly · monthly
`;
    return { content: [{ type: "text", text }] };
  }
);

server.tool(
  "wpflint_scaffold_plugin",
  "Scaffold a new WPFlint plugin with directory structure, main file, composer.json, and config.",
  {
    slug: z.string().describe("Plugin slug, e.g. my-shop"),
    namespace: z.string().optional().default("App").describe("PHP namespace for the plugin"),
  },
  async ({ slug, namespace }) => {
    const files = scaffoldPlugin(slug, namespace);
    let text = "## Scaffolded files\n\n";
    for (const [path, content] of Object.entries(files)) {
      if (content === "") {
        text += `**\`${path}\`** _(empty placeholder)_\n\n`;
      } else {
        const lang = path.endsWith(".json") ? "json" : "php";
        text += `**\`${path}\`**\n\n\`\`\`${lang}\n${content}\`\`\`\n\n`;
      }
    }
    return { content: [{ type: "text", text }] };
  }
);

// ---------------------------------------------------------------------------
// Start
// ---------------------------------------------------------------------------

const transport = new StdioServerTransport();
await server.connect(transport);
