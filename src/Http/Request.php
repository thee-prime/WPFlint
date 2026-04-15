<?php
/**
 * HTTP Request with validation and sanitization.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

use WPFlint\Validation\Validator;

/**
 * Base request class with validation engine.
 *
 * Subclass to define rules(), sanitize(), and authorize().
 * Validation is delegated to WPFlint\Validation\Validator internally.
 */
class Request {

	/**
	 * Raw input data.
	 *
	 * @var array<string, mixed>
	 */
	protected array $data = array();

	/**
	 * Validated and sanitized data.
	 *
	 * @var array<string, mixed>
	 */
	protected array $validated_data = array();

	/**
	 * Uploaded files.
	 *
	 * @var array<string, mixed>
	 */
	protected array $files = array();

	/**
	 * Validation errors keyed by field.
	 *
	 * @var array<string, string>
	 */
	protected array $errors = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $data       Input data.
	 * @param array<string, mixed> $data_files Uploaded files ($_FILES format).
	 */
	public function __construct( array $data = array(), array $data_files = array() ) {
		$this->data  = $data;
		$this->files = $data_files;
	}

	/**
	 * Create a request from PHP superglobals.
	 *
	 * @return static
	 */
	public static function capture(): self {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

		if ( 'GET' === $method ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked in middleware.
			$data = wp_unslash( $_GET );
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked in middleware.
			$data = wp_unslash( $_POST );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Nonce checked in middleware; files passed through.
		return new static( $data, $_FILES );
	}

	/**
	 * Get a single input value using dot notation.
	 *
	 * @param string $key     Dot-notated key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function input( string $key, $default = null ) {
		return $this->array_get( $this->data, $key, $default );
	}

	/**
	 * Get all input data.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->data;
	}

	/**
	 * Get only specified keys.
	 *
	 * @param array<string> $keys Keys to include.
	 * @return array<string, mixed>
	 */
	public function only( array $keys ): array {
		$result = array();
		foreach ( $keys as $key ) {
			$value = $this->input( $key );
			if ( null !== $value ) {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Get all keys except specified.
	 *
	 * @param array<string> $keys Keys to exclude.
	 * @return array<string, mixed>
	 */
	public function except( array $keys ): array {
		return array_diff_key( $this->data, array_flip( $keys ) );
	}

	/**
	 * Check if input key exists.
	 *
	 * @param string $key Dot-notated key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return null !== $this->array_get( $this->data, $key );
	}

	/**
	 * Get an uploaded file.
	 *
	 * @param string $key File input name.
	 * @return array|null
	 */
	public function file( string $key ) {
		return $this->files[ $key ] ?? null;
	}

	/**
	 * Get validated data.
	 *
	 * @return array<string, mixed>
	 */
	public function validated(): array {
		return $this->validated_data;
	}

	/**
	 * Get validation errors.
	 *
	 * @return array<string, string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Define validation rules. Override in subclass.
	 *
	 * @return array<string, string|array>
	 */
	public function rules(): array {
		return array();
	}

	/**
	 * Define sanitization callbacks. Override in subclass.
	 *
	 * @return array<string, callable|string>
	 */
	public function sanitize(): array {
		return array();
	}

	/**
	 * Check authorization. Override in subclass.
	 *
	 * @return bool
	 */
	public function authorize(): bool {
		return true;
	}

	/**
	 * Run validation (delegated to Validator) and apply sanitization.
	 *
	 * @return bool True if valid.
	 */
	public function validate(): bool {
		$rules = $this->rules();

		if ( empty( $rules ) ) {
			$this->validated_data = $this->data;
			$this->errors         = array();
			return true;
		}

		$result = Validator::make( $this->data, $rules );

		$this->errors         = $result->errors();
		$this->validated_data = $result->validated();

		if ( $result->fails() ) {
			return false;
		}

		$this->apply_sanitization();
		return true;
	}

	/**
	 * Apply sanitization callbacks to validated data.
	 *
	 * @return void
	 */
	protected function apply_sanitization(): void {
		$sanitizers = $this->sanitize();

		foreach ( $sanitizers as $field => $callback ) {
			$value = $this->array_get( $this->validated_data, $field );
			if ( null !== $value ) {
				$sanitized = is_string( $callback )
					? call_user_func( $callback, $value )
					: $callback( $value );
				$this->array_set( $this->validated_data, $field, $sanitized );
			}
		}
	}

	/**
	 * Get a value from a nested array using dot notation.
	 *
	 * @param array  $array   Source array.
	 * @param string $key     Dot-notated key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	protected function array_get( array $array, string $key, $default = null ) {
		$segments = explode( '.', $key );

		foreach ( $segments as $segment ) {
			if ( ! is_array( $array ) || ! array_key_exists( $segment, $array ) ) {
				return $default;
			}
			$array = $array[ $segment ];
		}

		return $array;
	}

	/**
	 * Set a value in a nested array using dot notation.
	 *
	 * @param array  $array Target array (passed by reference).
	 * @param string $key   Dot-notated key.
	 * @param mixed  $value Value to set.
	 * @return void
	 */
	protected function array_set( array &$array, string $key, $value ): void {
		$segments      = explode( '.', $key );
		$current       = &$array;
		$segment_count = count( $segments );

		for ( $i = 0; $i < $segment_count; $i++ ) {
			$segment = $segments[ $i ];

			if ( $i === $segment_count - 1 ) {
				$current[ $segment ] = $value;
			} else {
				if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) {
					$current[ $segment ] = array();
				}
				$current = &$current[ $segment ];
			}
		}
	}
}
