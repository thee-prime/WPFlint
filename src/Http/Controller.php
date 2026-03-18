<?php
/**
 * Base controller with auto-resolution.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

/**
 * Base controller class.
 *
 * Constructor dependencies are auto-resolved from the container.
 * Method parameters type-hinting a Request subclass are auto-validated.
 */
abstract class Controller {

}
