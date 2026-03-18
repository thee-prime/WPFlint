<?php
/**
 * HTTP Request with validation and sanitization.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

/**
 * Base request class with validation engine.
 *
 * Subclass to define rules(), sanitize(), and authorize().
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
	 * @return array<string, string>
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
	 * Run validation and sanitization.
	 *
	 * @return bool True if valid.
	 */
	public function validate(): bool {
		$this->errors         = array();
		$this->validated_data = array();

		$rules = $this->rules();

		if ( empty( $rules ) ) {
			$this->validated_data = $this->data;
			return true;
		}

		foreach ( $rules as $field => $rule_string ) {
			$this->validate_field( $field, $rule_string );
		}

		if ( ! empty( $this->errors ) ) {
			return false;
		}

		$this->apply_sanitization();
		return true;
	}

	/**
	 * Validate a single field against its rules.
	 *
	 * @param string $field       Field name (supports dot notation and wildcards).
	 * @param string $rule_string Pipe-separated rules.
	 */
	protected function validate_field( string $field, string $rule_string ): void {
		$rules = explode( '|', $rule_string );

		if ( false !== strpos( $field, '*' ) ) {
			$this->validate_wildcard_field( $field, $rules );
			return;
		}

		$value    = $this->array_get( $this->data, $field );
		$nullable = in_array( 'nullable', $rules, true );

		if ( $nullable && null === $value ) {
			$this->array_set( $this->validated_data, $field, $value );
			return;
		}

		foreach ( $rules as $rule ) {
			if ( 'nullable' === $rule ) {
				continue;
			}

			$error = $this->apply_rule( $field, $value, $rule );
			if ( null !== $error ) {
				$this->errors[ $field ] = $error;
				return;
			}
		}

		$this->array_set( $this->validated_data, $field, $value );
	}

	/**
	 * Validate wildcard fields (e.g. items.*.product_id).
	 *
	 * @param string   $pattern Dot-notated pattern with *.
	 * @param string[] $rules   Parsed rules.
	 */
	protected function validate_wildcard_field( string $pattern, array $rules ): void {
		$expanded = $this->expand_wildcard( $pattern, $this->data );

		foreach ( $expanded as $concrete_field ) {
			$value    = $this->array_get( $this->data, $concrete_field );
			$nullable = in_array( 'nullable', $rules, true );

			if ( $nullable && null === $value ) {
				$this->array_set( $this->validated_data, $concrete_field, $value );
				continue;
			}

			foreach ( $rules as $rule ) {
				if ( 'nullable' === $rule ) {
					continue;
				}

				$error = $this->apply_rule( $concrete_field, $value, $rule );
				if ( null !== $error ) {
					$this->errors[ $concrete_field ] = $error;
					break;
				}
			}

			if ( ! isset( $this->errors[ $concrete_field ] ) ) {
				$this->array_set( $this->validated_data, $concrete_field, $value );
			}
		}
	}

	/**
	 * Apply a single rule to a value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @param string $rule  Rule string (e.g. 'required', 'min:5', 'in:a,b,c').
	 * @return string|null Error message or null if valid.
	 */
	protected function apply_rule( string $field, $value, string $rule ): ?string {
		$parts = explode( ':', $rule, 2 );
		$name  = $parts[0];
		$param = $parts[1] ?? null;

		switch ( $name ) {
			case 'required':
				if ( null === $value || '' === $value ) {
					return sprintf(
						/* translators: %s: field name */
						__( 'The %s field is required.', 'wpflint' ),
						$field
					);
				}
				break;

			case 'string':
				if ( null !== $value && ! is_string( $value ) ) {
					return sprintf(
						/* translators: %s: field name */
						__( 'The %s field must be a string.', 'wpflint' ),
						$field
					);
				}
				break;

			case 'integer':
				if ( null !== $value && ! is_numeric( $value ) ) {
					return sprintf(
						/* translators: %s: field name */
						__( 'The %s field must be an integer.', 'wpflint' ),
						$field
					);
				}
				if ( null !== $value && (string) (int) $value !== (string) $value ) {
					return sprintf(
						/* translators: %s: field name */
						__( 'The %s field must be an integer.', 'wpflint' ),
						$field
					);
				}
				break;

			case 'numeric':
				if ( null !== $value && ! is_numeric( $value ) ) {
					return sprintf(
						/* translators: %s: field name */
						__( 'The %s field must be numeric.', 'wpflint' ),
						$field
					);
				}
				break;

			case 'email':
				if ( null !== $value && ! is_email( $value ) ) {
					return sprintf(
						/* translators: %s: field name */
						__( 'The %s field must be a valid email.', 'wpflint' ),
						$field
					);
				}
				break;

			case 'url':
				if ( null !== $value && false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return sprintf(
						/* translators: %s: field name */
						__( 'The %s field must be a valid URL.', 'wpflint' ),
						$field
					);
				}
				break;

			case 'min':
				return $this->validate_min( $field, $value, $param );

			case 'max':
				return $this->validate_max( $field, $value, $param );

			case 'in':
				$allowed = explode( ',', $param );
				if ( null !== $value && ! in_array( (string) $value, $allowed, true ) ) {
					return sprintf(
						/* translators: 1: field name 2: allowed values */
						__( 'The %1$s field must be one of: %2$s.', 'wpflint' ),
						$field,
						$param
					);
				}
				break;

			case 'array':
				if ( null !== $value && ! is_array( $value ) ) {
					return sprintf(
						/* translators: %s: field name */
						__( 'The %s field must be an array.', 'wpflint' ),
						$field
					);
				}
				break;

			case 'boolean':
				$booleans = array( true, false, 0, 1, '0', '1' );
				if ( null !== $value && ! in_array( $value, $booleans, true ) ) {
					return sprintf(
						/* translators: %s: field name */
						__( 'The %s field must be a boolean.', 'wpflint' ),
						$field
					);
				}
				break;
		}

		return null;
	}

	/**
	 * Validate min rule.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @param string $param Min parameter.
	 * @return string|null Error or null.
	 */
	protected function validate_min( string $field, $value, string $param ): ?string {
		$min = (float) $param;

		if ( is_array( $value ) ) {
			$item_count = count( $value );
			if ( $item_count < $min ) {
				return sprintf(
					/* translators: 1: field name 2: minimum count */
					__( 'The %1$s field must have at least %2$s items.', 'wpflint' ),
					$field,
					$param
				);
			}
		} elseif ( is_numeric( $value ) && (float) $value < $min ) {
			return sprintf(
				/* translators: 1: field name 2: minimum value */
				__( 'The %1$s field must be at least %2$s.', 'wpflint' ),
				$field,
				$param
			);
		} elseif ( is_string( $value ) && strlen( $value ) < (int) $min ) {
			return sprintf(
				/* translators: 1: field name 2: minimum length */
				__( 'The %1$s field must be at least %2$s characters.', 'wpflint' ),
				$field,
				$param
			);
		}

		return null;
	}

	/**
	 * Validate max rule.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @param string $param Max parameter.
	 * @return string|null Error or null.
	 */
	protected function validate_max( string $field, $value, string $param ): ?string {
		$max = (float) $param;

		if ( is_array( $value ) ) {
			$item_count = count( $value );
			if ( $item_count > $max ) {
				return sprintf(
					/* translators: 1: field name 2: maximum count */
					__( 'The %1$s field must not have more than %2$s items.', 'wpflint' ),
					$field,
					$param
				);
			}
		} elseif ( is_numeric( $value ) && (float) $value > $max ) {
			return sprintf(
				/* translators: 1: field name 2: maximum value */
				__( 'The %1$s field must not be greater than %2$s.', 'wpflint' ),
				$field,
				$param
			);
		} elseif ( is_string( $value ) && strlen( $value ) > (int) $max ) {
			return sprintf(
				/* translators: 1: field name 2: maximum length */
				__( 'The %1$s field must not be greater than %2$s characters.', 'wpflint' ),
				$field,
				$param
			);
		}

		return null;
	}

	/**
	 * Apply sanitization callbacks to validated data.
	 */
	protected function apply_sanitization(): void {
		$sanitizers = $this->sanitize();

		foreach ( $sanitizers as $field => $callback ) {
			$value = $this->array_get( $this->validated_data, $field );
			if ( null !== $value ) {
				$this->array_set( $this->validated_data, $field, $callback( $value ) );
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
	 */
	protected function array_set( array &$array, string $key, $value ): void {
		$segments = explode( '.', $key );
		$current  = &$array;

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

	/**
	 * Expand a wildcard pattern into concrete field paths.
	 *
	 * @param string $pattern Dot-notated pattern with *.
	 * @param array  $data    Source data.
	 * @return string[] Concrete field paths.
	 */
	protected function expand_wildcard( string $pattern, array $data ): array {
		$segments = explode( '.', $pattern );
		return $this->expand_segments( $segments, $data, '' );
	}

	/**
	 * Recursively expand pattern segments.
	 *
	 * @param string[] $segments Remaining segments.
	 * @param mixed    $data     Current data level.
	 * @param string   $prefix   Current path prefix.
	 * @return string[]
	 */
	protected function expand_segments( array $segments, $data, string $prefix ): array {
		if ( empty( $segments ) ) {
			return array( rtrim( $prefix, '.' ) );
		}

		$segment = array_shift( $segments );
		$paths   = array();

		if ( '*' === $segment ) {
			if ( ! is_array( $data ) ) {
				return array();
			}
			foreach ( array_keys( $data ) as $index ) {
				$new_prefix = $prefix . $index . '.';
				$paths      = array_merge( $paths, $this->expand_segments( $segments, $data[ $index ], $new_prefix ) );
			}
		} else {
			$new_prefix = $prefix . $segment . '.';
			$next_data  = is_array( $data ) && array_key_exists( $segment, $data ) ? $data[ $segment ] : null;
			$paths      = $this->expand_segments( $segments, $next_data, $new_prefix );
		}

		return $paths;
	}
}
