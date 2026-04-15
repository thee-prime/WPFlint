  Tier 1 — Without these, the framework can't build a real plugin

  1. Admin Menu & Page Builder
  Every plugin needs admin pages. Currently zero support for add_menu_page(), add_submenu_page(), add_options_page(). Needs a fluent API:
  AdminMenu::page('My Plugin', 'my-plugin')
      ->capability('manage_options')
      ->icon('dashicons-admin-tools')
      ->render(fn() => view('admin.index'))
      ->submenu('Settings', fn() => view('admin.settings'));

  2. Settings API Builder
  WordPress Settings API is notoriously verbose. A fluent builder over register_setting() / add_settings_section() / add_settings_field() is something every plugin needs:
  Settings::section('general', 'General Settings')
      ->field('api_key', 'API Key')->type('text')->required()
      ->field('debug', 'Debug mode')->type('checkbox')
      ->register('my_plugin_options');

  3. Asset Manager (Enqueue/Dequeue)
  No way to register scripts/styles with versioning, conditional loading, or dependency chains:
  Asset::script('my-plugin', 'assets/js/app.js')
      ->deps(['jquery'])
      ->version('1.0')
      ->footer()
      ->only_on(fn() => is_admin());

  4. Plugin Lifecycle Hooks
  No structured activation/deactivation/uninstall wiring. Plugin devs need:
  // In main plugin file
  $plugin->onActivate(fn() => Migrator::run());
  $plugin->onDeactivate(fn() => Scheduler::clearAll());
  $plugin->onUninstall(fn() => Installer::purge());

  ---
  Tier 2 — High value, commonly needed

  5. Shortcode Builder
  Still used in tens of thousands of plugins/themes. A fluent wrapper over add_shortcode():
  Shortcode::make('my_plugin_button')
      ->defaults(['color' => 'blue', 'size' => 'medium'])
      ->render(function(array $atts, string $content): string {
          return "<button class='{$atts['color']}'>{$content}</button>";
      });

  6. Email / Mailer
  Fluent wp_mail() wrapper with template support:
  Mailer::to($user->email)
      ->subject('Order Confirmed')
      ->template('emails.order-confirmed', ['order' => $order])
      ->send();

  7. View / Template Engine
  No way to render PHP templates cleanly. A minimal View::render('partials/settings', $data) that resolves template paths inside the plugin and passes escaped data:
  View::make('admin/settings')->with(['title' => 'Settings'])->render();

  8. Admin Notices
  Transient-backed admin notices — needed by almost every plugin:
  Notice::success('Settings saved.')->dismissible()->flash();
  Notice::error('API key invalid.')->persistent('my_plugin_api_error');

  ---
  Tier 3 — Completeness for production plugins

  9. Block Registration (Gutenberg)
  register_block_type() fluent builder. Gutenberg is now the standard editor:
  Block::make('my-plugin/pricing-table')
      ->title('Pricing Table')
      ->category('widgets')
      ->script('my-plugin-blocks')
      ->render(fn($attrs, $content) => view('blocks.pricing', $attrs))
      ->register();

  10. Metabox Builder
  Common admin UI pattern — a fluent add_meta_box() wrapper with field rendering:
  MetaBox::make('book_details', 'Book Details')
      ->for('book')
      ->context('normal')
      ->field('_isbn', 'ISBN')->type('text')
      ->field('_pages', 'Page count')->type('number')
      ->register();

  11. Widget Builder
  WP_Widget subclass generation is boilerplate-heavy. A fluent wrapper reduces that significantly.

  12. REST API Versioning + Auth helpers
  Current REST support works but lacks versioned namespace grouping and WP_REST_Authentication helpers (JWT/Application Passwords).

  ---
  Tier 4 — Nice to have

  - Pagination helper — Paginator::forQuery($wp_query) returning page links
  - File/Media Upload handler — wp_handle_upload() wrapper with validation
  - WP-CLI integration — expose migrations/cache as real wp commands (not just WP_CLI::add_command)
  - WPML/Polylang compat layer — translation-aware URL helpers
  - Table helper — WP_List_Table wrapper for admin list pages
