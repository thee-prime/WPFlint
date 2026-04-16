<?php
/**
 * REST API authentication and authorisation helpers.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

/**
 * Factory methods that return permission_callback callables for REST routes,
 * plus direct boolean checks for custom auth logic.
 *
 * Usage — register a REST route that requires the 'manage_options' capability:
 *
 *     register_rest_route( 'my-plugin/v1', '/settings', [
 *         'methods'             => 'GET',
 *         'callback'            => [ $controller, 'index' ],
 *         'permission_callback' => RestAuth::capability( 'manage_options' ),
 *     ] );
 *
 *     // Public endpoint:
 *     'permission_callback' => RestAuth::public_access(),
 *
 *     // Any logged-in user:
 *     'permission_callback' => RestAuth::logged_in(),
 *
 *     // Multiple caps (all required):
 *     'permission_callback' => RestAuth::all_of( 'edit_posts', 'upload_files' ),
 *
 *     // Any one of several caps:
 *     'permission_callback' => RestAuth::any_of( 'edit_posts', 'edit_pages' ),
 */
class RestAuth {

	// ---------------------------------------------------------------
	// Permission-callback factories
	// ---------------------------------------------------------------

	/**
	 * Return a permission_callback that requires the given capability.
	 *
	 * @param string $cap WordPress capability slug.
	 * @return callable Returns bool: true when the current user has the capability.
	 */
	public static function capability( string $cap ): callable {
		return static function () use ( $cap ): bool {
			return current_user_can( $cap );
		};
	}

	/**
	 * Return a permission_callback that requires the user to be logged in.
	 *
	 * @return callable Returns bool: true when a user is authenticated.
	 */
	public static function logged_in(): callable {
		return static function (): bool {
			return is_user_logged_in();
		};
	}

	/**
	 * Return a permission_callback that allows any request (public endpoint).
	 *
	 * @return callable Always returns true.
	 */
	public static function public_access(): callable {
		return static function (): bool {
			return true;
		};
	}

	/**
	 * Return a permission_callback that requires ALL listed capabilities.
	 *
	 * @param string ...$caps One or more capability slugs (all must be held).
	 * @return callable Returns true only when the user holds every capability.
	 */
	public static function all_of( string ...$caps ): callable {
		return static function () use ( $caps ): bool {
			foreach ( $caps as $cap ) {
				if ( ! current_user_can( $cap ) ) {
					return false;
				}
			}
			return true;
		};
	}

	/**
	 * Return a permission_callback that requires AT LEAST ONE of the capabilities.
	 *
	 * @param string ...$caps One or more capability slugs (at least one must be held).
	 * @return callable Returns true when the user holds at least one capability.
	 */
	public static function any_of( string ...$caps ): callable {
		return static function () use ( $caps ): bool {
			foreach ( $caps as $cap ) {
				if ( current_user_can( $cap ) ) {
					return true;
				}
			}
			return false;
		};
	}

	// ---------------------------------------------------------------
	// Direct boolean checks
	// ---------------------------------------------------------------

	/**
	 * Check whether the current request is authenticated (user is logged in).
	 *
	 * @return bool
	 */
	public static function require_logged_in(): bool {
		return is_user_logged_in();
	}

	/**
	 * Check whether the current user holds a given capability.
	 *
	 * @param string $cap WordPress capability slug.
	 * @return bool
	 */
	public static function require_capability( string $cap ): bool {
		return current_user_can( $cap );
	}

	/**
	 * Build a versioned REST namespace string.
	 *
	 * Combines a plugin slug and version number into the standard
	 * 'my-plugin/v1' format used by register_rest_route().
	 *
	 * @param string $plugin  Plugin slug (e.g. 'my-plugin').
	 * @param int    $version API version number (e.g. 1).
	 * @return string Namespace string, e.g. 'my-plugin/v1'.
	 */
	public static function namespace( string $plugin, int $version = 1 ): string {
		return $plugin . '/v' . $version;
	}
}
