<?php
/**
 * Model not found exception.
 *
 * @package WPFlint\Database\ORM
 */

declare(strict_types=1);

namespace WPFlint\Database\ORM;

use RuntimeException;

/**
 * Thrown when a model cannot be found via find_or_fail().
 */
class ModelNotFoundException extends RuntimeException {

}
