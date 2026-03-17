<?php
/**
 * Not-found exception for the container.
 *
 * @package WPFlint\Container
 */

declare(strict_types=1);

namespace WPFlint\Container;

use Exception;

/**
 * Exception thrown when a requested entry is not found in the container.
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface {

}
