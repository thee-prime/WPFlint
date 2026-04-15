<?php
/**
 * Exception thrown when validation fails in strict mode.
 *
 * @package WPFlint\Validation
 */

declare(strict_types=1);

namespace WPFlint\Validation;

use RuntimeException;

/**
 * Thrown by ValidationResult::throw_if_fails() when validation does not pass.
 *
 * Usage:
 *
 *     try {
 *         Validator::make( $data, $rules )->throw_if_fails();
 *     } catch ( ValidationException $e ) {
 *         $errors = $e->errors();  // keyed by field
 *         $first  = $e->first();  // first error message overall
 *     }
 */
class ValidationException extends RuntimeException {

	/**
	 * All validation error messages, keyed by field.
	 *
	 * @var array<string, string>
	 */
	protected array $errors;

	/**
	 * Create a new ValidationException.
	 *
	 * @param array<string, string> $errors Validation error messages.
	 */
	public function __construct( array $errors ) {
		$this->errors = $errors;

		$first = reset( $errors );

		parent::__construct(
			is_string( $first ) ? $first : __( 'Validation failed.', 'wpflint' )
		);
	}

	/**
	 * Get all validation error messages, keyed by field.
	 *
	 * @return array<string, string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Get the first error message for a given field (or overall).
	 *
	 * @param string|null $field Optional field name.
	 * @return string|null
	 */
	public function first( ?string $field = null ): ?string {
		if ( null !== $field ) {
			return $this->errors[ $field ] ?? null;
		}

		$first = reset( $this->errors );
		return is_string( $first ) ? $first : null;
	}
}
