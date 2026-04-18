<?php
/**
 * WP-CLI command to generate a custom validation rule stub.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Generates a new validation rule class.
 *
 * ## EXAMPLES
 *
 *     wp wpflint make:rule Uppercase
 *     wp wpflint make:rule PhoneNumber --path=app/Rules
 */
class MakeRuleCommand extends Command {

	/**
	 * Generate a validation rule file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The rule class name (PascalCase).
	 *
	 * [--path=<path>]
	 * : Directory for the rule file.
	 * ---
	 * default: app/Rules
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0];
		$path = $assoc_args['path'] ?? 'app/Rules';

		$base_dir = getcwd();
		$dir      = rtrim( $base_dir, '/' ) . '/' . ltrim( $path, '/' );
		$filepath = $dir . '/' . $name . '.php';

		$stub = $this->get_stub( $name );
		$this->write_file( $filepath, $stub );
	}

	/**
	 * Get the rule stub content.
	 *
	 * @param string $name Rule class name.
	 * @return string
	 */
	private function get_stub( string $name ): string {
		return <<<PHP
<?php

declare(strict_types=1);

use WPFlint\\Validation\\Rules\\RuleInterface;

class {$name} implements RuleInterface {

	/**
	 * Determine if the given value passes this rule.
	 *
	 * @param mixed \$value The value being validated.
	 * @return bool
	 */
	public function passes( \$value ): bool {
		// TODO: implement rule logic.
		return true;
	}

	/**
	 * Return the validation error message.
	 *
	 * Use :attribute as a placeholder for the field name.
	 *
	 * @return string
	 */
	public function message(): string {
		return __( 'The :attribute field is invalid.', 'text-domain' );
	}
}

PHP;
	}
}
