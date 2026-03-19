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
	 * Cache manager.
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache;

	/**
	 * Constructor.
	 *
	 * @param CacheManager $cache Cache manager.
	 */
	public function __construct( CacheManager $cache ) {
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
		if ( isset( $assoc_args['tag'] ) ) {
			$tag = $assoc_args['tag'];
			$this->cache->tags( $tag )->flush();
			$this->success(
				sprintf(
					/* translators: %s: cache tag name */
					__( 'Cache tag "%s" flushed.', 'wpflint' ),
					$tag
				)
			);
			return;
		}

		$this->cache->flush();
		$this->success( __( 'Application cache cleared.', 'wpflint' ) );
	}
}
