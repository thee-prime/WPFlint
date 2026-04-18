<?php
/**
 * WP-CLI command to clear the cache.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

use WPFlint\Application;
use WPFlint\Cache\CacheManager;

/**
 * Clears the application cache.
 *
 * ## EXAMPLES
 *
 *     wp wpflint cache:clear
 *     wp wpflint cache:clear --tag=orders
 */
class CacheClearCommand extends Command {

	/**
	 * Pre-resolved cache manager (set via constructor for testing).
	 *
	 * @var CacheManager|null
	 */
	private ?CacheManager $cache;

	/**
	 * Constructor.
	 *
	 * @param CacheManager|null $cache Optional pre-resolved instance (useful in tests).
	 */
	public function __construct( ?CacheManager $cache = null ) {
		$this->cache = $cache;
	}

	/**
	 * Clear the application cache.
	 *
	 * ## OPTIONS
	 *
	 * [--tag=<tag>]
	 * : Flush only a specific cache tag.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$cache = $this->cache ?? Application::get_instance()->make( CacheManager::class );

		if ( isset( $assoc_args['tag'] ) ) {
			$tag = $assoc_args['tag'];
			$cache->tags( $tag )->flush();
			$this->success(
				sprintf(
					/* translators: %s: cache tag name */
					__( 'Cache tag "%s" flushed.', 'wpflint' ),
					$tag
				)
			);
			return;
		}

		$cache->flush();
		$this->success( __( 'Application cache cleared.', 'wpflint' ) );
	}
}
