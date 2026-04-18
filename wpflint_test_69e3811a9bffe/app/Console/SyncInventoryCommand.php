<?php

declare(strict_types=1);

use WPFlint\Console\Command;

/**
 * ## EXAMPLES
 *
 *     wp wpflint sync_inventory
 */
class SyncInventoryCommand extends Command {

	/**
	 * Execute the command.
	 *
	 * ## OPTIONS
	 *
	 * [--flag=<value>]
	 * : Example optional flag.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$this->info( __( 'Running sync_inventory...', 'text-domain' ) );

		// TODO: implement command logic.

		$this->success( __( 'Done.', 'text-domain' ) );
	}
}
