<?php
/**
 * Interface for custom validation rule objects.
 *
 * @package WPFlint\Validation\Rules
 */

declare(strict_types=1);

namespace WPFlint\Validation\Rules;

/**
 * Implement this interface to create a reusable, named validation rule.
 *
 * Usage:
 *
 *     class UppercaseRule implements RuleInterface {
 *         public function passes( $value ): bool {
 *             return $value === strtoupper( $value );
 *         }
 *         public function message(): string {
 *             return __( 'The :attribute must be uppercase.', 'text-domain' );
 *         }
 *     }
 *
 *     Validator::make( $data, [ 'code' => [ 'required', new UppercaseRule() ] ] );
 */
interface RuleInterface {

	/**
	 * Determine whether the given value passes the rule.
	 *
	 * @param mixed $value The value being validated.
	 * @return bool
	 */
	public function passes( $value ): bool;

	/**
	 * Return the validation error message.
	 *
	 * Use :attribute as a placeholder for the field name, e.g.:
	 * 'The :attribute must be uppercase.'
	 *
	 * @return string
	 */
	public function message(): string;
}
