<?php
/**
 * PSR-11 ContainerInterface.
 *
 * @package WPFlint\Container
 */

declare(strict_types=1);

namespace WPFlint\Container;

/**
 * PSR-11 ContainerInterface (inlined to avoid Composer dependency).
 */
interface ContainerInterface {

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return mixed Entry.
	 *
	 * @throws NotFoundExceptionInterface  No entry was found for this identifier.
	 * @throws ContainerExceptionInterface Error while retrieving the entry.
	 */
	public function get( string $id );

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool;
}
