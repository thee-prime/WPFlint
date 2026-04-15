<?php
/**
 * Immutable value object representing the outcome of a validation run.
 *
 * @package WPFlint\Validation
 */

declare(strict_types=1);

namespace WPFlint\Validation;

/**
 * Holds the outcome of a Validator run.
 *
 * Usage:
 *
 *     $result = Validator::make( $data, $rules );
 *
 *     if ( $result->fails() ) {
 *         wp_send_json_error( $result->errors() );
 *     }
 *
 *     $clean = $result->validated();      // only the fields that passed
 *     $msg   = $result->first( 'email' ); // first error for the email field
 *
 *     // Throw on failure (strict mode):
 *     $result->throw_if_fails();
 */
class ValidationResult {

	/**
	 * Error messages, keyed by field name.
	 *
	 * @var array<string, string>
	 */
	protected array $errors;

	/**
	 * Validated (clean) data — only fields that passed all rules.
	 *
	 * @var array<string, mixed>
	 */
	protected array $validated;

	/**
	 * Create a new ValidationResult.
	 *
	 * @param array<string, string> $errors    Validation errors keyed by field.
	 * @param array<string, mixed>  $validated Clean data for fields that passed.
	 */
	public function __construct( array $errors, array $validated ) {
		$this->errors    = $errors;
		$this->validated = $validated;
	}

	// ---------------------------------------------------------------
	// Status
	// ---------------------------------------------------------------

	/**
	 * Whether validation passed for all fields.
	 *
	 * @return bool
	 */
	public function passes(): bool {
		return empty( $this->errors );
	}

	/**
	 * Whether any validation errors occurred.
	 *
	 * @return bool
	 */
	public function fails(): bool {
		return ! $this->passes();
	}

	// ---------------------------------------------------------------
	// Errors
	// ---------------------------------------------------------------

	/**
	 * All error messages, keyed by field.
	 *
	 * @return array<string, string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Get the first error message, optionally for a specific field.
	 *
	 * @param string|null $field Field name, or null for the first error overall.
	 * @return string|null
	 */
	public function first( ?string $field = null ): ?string {
		if ( null !== $field ) {
			return $this->errors[ $field ] ?? null;
		}

		$first = reset( $this->errors );
		return is_string( $first ) ? $first : null;
	}

	/**
	 * Whether a specific field has an error.
	 *
	 * @param string $field Field name.
	 * @return bool
	 */
	public function has_error( string $field ): bool {
		return isset( $this->errors[ $field ] );
	}

	// ---------------------------------------------------------------
	// Validated data
	// ---------------------------------------------------------------

	/**
	 * Get all validated (clean) data.
	 *
	 * Only fields that passed their rules are included. Fields with no rules
	 * are excluded unless rules were an empty array.
	 *
	 * @return array<string, mixed>
	 */
	public function validated(): array {
		return $this->validated;
	}

	/**
	 * Get a single validated value.
	 *
	 * @param string $key     Field name (dot-notation supported).
	 * @param mixed  $default Default when field is absent.
	 * @return mixed
	 */
	public function value( string $key, $default = null ) {
		$segments = explode( '.', $key );
		$current  = $this->validated;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return $default;
			}
			$current = $current[ $segment ];
		}

		return $current;
	}

	// ---------------------------------------------------------------
	// Strict mode
	// ---------------------------------------------------------------

	/**
	 * Throw a ValidationException if validation failed.
	 *
	 * @return $this Fluent — returns self if validation passed.
	 * @throws ValidationException When validation has failed.
	 */
	public function throw_if_fails(): self {
		if ( $this->fails() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
			throw new ValidationException( $this->errors );
		}

		return $this;
	}
}
