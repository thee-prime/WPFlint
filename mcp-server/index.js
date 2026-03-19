#!/usr/bin/env node
/**
 * WPFlint MCP Server
 *
 * Exposes scaffolding tools for WPFlint projects via Model Context Protocol.
 * Tools: wpflint_make_migration, wpflint_make_model, wpflint_make_provider, wpflint_make_controller,
 *        wpflint_make_middleware, wpflint_make_request, wpflint_make_event, wpflint_make_facade,
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
