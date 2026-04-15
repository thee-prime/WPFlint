<?php
/**
 * Service provider for the Registration module.
 *
 * @package WPFlint\Registration
 */

declare(strict_types=1);

namespace WPFlint\Registration;

use WPFlint\Providers\ServiceProvider;

/**
 * Provides PostType, Taxonomy, and MetaField builder access via the container.
 *
 * Primarily a convenience provider — the builders can also be used standalone
 * without registering this provider at all.
 *
 * Usage:
 *
 *     $app->register( RegistrationServiceProvider::class );
 *
 *     // Resolve builders from the container or use static factories directly:
 *     PostType::make( 'book' )->label( 'Book', 'Books' )->public()->register();
 *
 *     // Or define all registrations inside your own provider's boot():
 *     add_action( 'init', function() {
 *         PostType::make( 'book' )->label( 'Book', 'Books' )->public()->register();
 *         Taxonomy::make( 'genre' )->label( 'Genre', 'Genres' )->for( 'book' )->register();
 *         MetaField::post( 'book', '_price' )->type( 'number' )->single()->register();
 *     } );
 */
class RegistrationServiceProvider extends ServiceProvider {

	/**
	 * Register Registration module bindings.
	 *
	 * @return void
	 */
	public function register(): void {
		// Nothing to bind — PostType, Taxonomy, and MetaField are
		// value-object builders instantiated directly via static factories.
	}

	/**
	 * Boot: no framework-level hooks needed.
	 * Plugin authors register types in their own provider's boot() → add_action('init').
	 *
	 * @return void
	 */
	public function boot(): void {}
}
