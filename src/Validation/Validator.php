<?php
/**
 * Standalone validator — validate any data array, anywhere in the application.
 *
 * @package WPFlint\Validation
 */

declare(strict_types=1);

namespace WPFlint\Validation;

use Closure;
use WPFlint\Validation\Rules\RuleInterface;

/**
 * Validates arbitrary data against a rule set.
 *
 * Usage:
 *
 *     $result = Validator::make(
 *         array( 'email' => 'foo', 'age' => 17 ),
 *         array(
 *             'email' => 'required|email',
 *             'age'   => 'required|integer|min:18',
 *             'name'  => 'sometimes|string|max:100',
 *         ),
 *         array( 'age.min' => __( 'You must be 18 or older.', 'my-plugin' ) ),
 *         array( 'age'     => __( 'your age', 'my-plugin' ) )
 *     );
 *
 *     if ( $result->fails() ) {
 *         return Response::error( $result->first(), 422 );
 *     }
 *
 *     $clean = $result->validated();
 *
 * Custom rules via closure:
 *
 *     Validator::make( $data, [
 *         'code' => [ 'required', function ( $field, $value, $fail ) {
 *             if ( ! preg_match( '/^[A-Z]{3}$/', $value ) ) {
 *                 $fail( 'The :attribute must be 3 uppercase letters.' );
 *             }
 *         } ],
 *     ] );
 *
 * Custom rules via class:
 *
 *     Validator::extend( 'uppercase', new UppercaseRule() );
 *     Validator::make( $data, [ 'code' => 'required|uppercase' ] );
 */
class Validator {

	// ---------------------------------------------------------------
	// Global custom rule registry
	// ---------------------------------------------------------------

	/**
	 * Globally registered custom rules.
	 * Key = rule name, value = RuleInterface|Closure.
	 *
	 * @var array<string, RuleInterface|Closure>
	 */
	protected static array $extensions = array();

	/**
	 * Register a global custom rule by name.
	 *
	 * The callback receives ($field, $value, $fail):
	 *
	 *     Validator::extend( 'phone', function ( $field, $value, $fail ) {
	 *         if ( ! preg_match( '/^\+?[0-9]{7,15}$/', $value ) ) {
	 *             $fail( 'The :attribute must be a valid phone number.' );
	 *         }
	 *     } );
	 *
	 * Or pass a RuleInterface instance.
	 *
	 * @param string                $name     Rule name (lowercase, no spaces).
	 * @param RuleInterface|Closure $callback Rule object or closure.
	 * @return void
	 */
	public static function extend( string $name, $callback ): void {
		static::$extensions[ $name ] = $callback;
	}

	// ---------------------------------------------------------------
	// Factory
	// ---------------------------------------------------------------

	/**
	 * Create and immediately run a validator.
	 *
	 * @param array<string, mixed>        $data       Input data to validate.
	 * @param array<string, string|array> $rules      Rules keyed by field.
	 * @param array<string, string>       $messages   Custom error messages.
	 * @param array<string, string>       $attributes Friendly attribute names.
	 * @return ValidationResult
	 */
	public static function make(
		array $data,
		array $rules,
		array $messages = array(),
		array $attributes = array()
	): ValidationResult {
		$instance = new self( $data, $rules, $messages, $attributes );
		return $instance->run();
	}

	// ---------------------------------------------------------------
	// Instance state
	// ---------------------------------------------------------------

	/**
	 * Input data.
	 *
	 * @var array<string, mixed>
	 */
	protected array $data;

	/**
	 * Validation rules keyed by field.
	 *
	 * @var array<string, string|array>
	 */
	protected array $rules;

	/**
	 * Custom error messages.
	 *
	 * @var array<string, string>
	 */
	protected array $custom_messages;

	/**
	 * Custom attribute names.
	 *
	 * @var array<string, string>
	 */
	protected array $attributes;

	/**
	 * Accumulated errors keyed by field.
	 *
	 * @var array<string, string>
	 */
	protected array $errors = array();

	/**
	 * Fields that passed validation.
	 *
	 * @var array<string, mixed>
	 */
	protected array $validated_data = array();

	/**
	 * Constructor.
	 *
	 * @param array $data       Input data.
	 * @param array $rules      Validation rules.
	 * @param array $messages   Custom error messages.
	 * @param array $attributes Friendly attribute names.
	 */
	public function __construct(
		array $data,
		array $rules,
		array $messages = array(),
		array $attributes = array()
	) {
		$this->data            = $data;
		$this->rules           = $rules;
		$this->custom_messages = $messages;
		$this->attributes      = $attributes;
	}

	// ---------------------------------------------------------------
	// Execution
	// ---------------------------------------------------------------

	/**
	 * Run all rules and return a ValidationResult.
	 *
	 * @return ValidationResult
	 */
	public function run(): ValidationResult {
		$this->errors         = array();
		$this->validated_data = array();

		foreach ( $this->rules as $field => $rule_definition ) {
			$rules = $this->normalize_rules( $rule_definition );

			if ( false !== strpos( $field, '*' ) ) {
				$this->validate_wildcard_field( $field, $rules );
			} else {
				$this->validate_field( $field, $rules );
			}
		}

		return new ValidationResult( $this->errors, $this->validated_data );
	}

	/**
	 * Normalize rules to an array of items (strings, closures, or RuleInterface).
	 *
	 * @param string|array $rules Raw rules definition.
	 * @return array
	 */
	protected function normalize_rules( $rules ): array {
		if ( is_string( $rules ) ) {
			return explode( '|', $rules );
		}

		return (array) $rules;
	}

	// ---------------------------------------------------------------
	// Field validation
	// ---------------------------------------------------------------

	/**
	 * Validate a single non-wildcard field.
	 *
	 * @param string $field Field name (supports dot-notation).
	 * @param array  $rules Normalized rules.
	 * @return void
	 */
	protected function validate_field( string $field, array $rules ): void {
		$bail      = in_array( 'bail', $rules, true );
		$nullable  = in_array( 'nullable', $rules, true );
		$sometimes = in_array( 'sometimes', $rules, true );

		// `sometimes` — skip entirely if the key is not present in data.
		if ( $sometimes && ! $this->key_exists( $field, $this->data ) ) {
			return;
		}

		$value = $this->array_get( $this->data, $field );

		// `nullable` — if value is null, mark as validated and stop.
		if ( $nullable && null === $value ) {
			$this->array_set( $this->validated_data, $field, null );
			return;
		}

		foreach ( $rules as $rule ) {
			if ( in_array( $rule, array( 'bail', 'nullable', 'sometimes' ), true ) ) {
				continue;
			}

			$error = $this->apply_rule( $field, $value, $rule );

			if ( null !== $error ) {
				$this->errors[ $field ] = $error;
				if ( $bail ) {
					return; // Stop after first failure when bail is set.
				}
				return; // Always stop on first failure per field.
			}
		}

		$this->array_set( $this->validated_data, $field, $value );
	}

	/**
	 * Validate a wildcard field pattern (e.g. items.*.qty).
	 *
	 * @param string $pattern Dot-notated pattern containing *.
	 * @param array  $rules   Normalized rules.
	 * @return void
	 */
	protected function validate_wildcard_field( string $pattern, array $rules ): void {
		$nullable  = in_array( 'nullable', $rules, true );
		$sometimes = in_array( 'sometimes', $rules, true );
		$expanded  = $this->expand_wildcard( $pattern, $this->data );

		foreach ( $expanded as $concrete_field ) {
			if ( $sometimes && ! $this->key_exists( $concrete_field, $this->data ) ) {
				continue;
			}

			$value = $this->array_get( $this->data, $concrete_field );

			if ( $nullable && null === $value ) {
				$this->array_set( $this->validated_data, $concrete_field, null );
				continue;
			}

			foreach ( $rules as $rule ) {
				if ( in_array( $rule, array( 'bail', 'nullable', 'sometimes' ), true ) ) {
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

	// ---------------------------------------------------------------
	// Rule dispatcher
	// ---------------------------------------------------------------

	/**
	 * Apply a single rule item to a field value.
	 *
	 * @param string                       $field Field name.
	 * @param mixed                        $value Field value.
	 * @param string|Closure|RuleInterface $rule  Rule to apply.
	 * @return string|null Error message or null if valid.
	 */
	protected function apply_rule( string $field, $value, $rule ): ?string {
		// Closure-based rule — receives field, value, and a $fail callback.
		if ( $rule instanceof Closure ) {
			return $this->apply_closure_rule( $field, $value, $rule );
		}

		// RuleInterface object.
		if ( $rule instanceof RuleInterface ) {
			return $this->apply_rule_object( $field, $value, $rule );
		}

		// Named rule string.
		$parts = explode( ':', (string) $rule, 2 );
		$name  = $parts[0];
		$param = $parts[1] ?? null;

		// Check global extension registry first.
		if ( isset( static::$extensions[ $name ] ) ) {
			return $this->apply_extension( $field, $value, $name, $param );
		}

		return $this->apply_named_rule( $field, $value, $name, $param );
	}

	/**
	 * Apply a closure-based rule.
	 *
	 * @param string  $field   Field name.
	 * @param mixed   $value   Field value.
	 * @param Closure $closure Closure( $field, $value, $fail ).
	 * @return string|null
	 */
	protected function apply_closure_rule( string $field, $value, Closure $closure ): ?string {
		$error = null;

		$fail = function ( string $message ) use ( &$error, $field ) {
			$attr  = $this->get_attribute_name( $field );
			$error = str_replace( ':attribute', $attr, $message );
		};

		$closure( $field, $value, $fail );

		return $error;
	}

	/**
	 * Apply a RuleInterface object.
	 *
	 * @param string        $field Field name.
	 * @param mixed         $value Field value.
	 * @param RuleInterface $rule  Rule object.
	 * @return string|null
	 */
	protected function apply_rule_object( string $field, $value, RuleInterface $rule ): ?string {
		if ( $rule->passes( $value ) ) {
			return null;
		}

		$attr    = $this->get_attribute_name( $field );
		$message = str_replace( ':attribute', $attr, $rule->message() );

		return $this->resolve_message( $field, 'custom', $message );
	}

	/**
	 * Apply a globally-registered extension rule.
	 *
	 * @param string      $field   Field name.
	 * @param mixed       $value   Field value.
	 * @param string      $name    Rule name.
	 * @param string|null $param   Rule parameter.
	 * @return string|null
	 */
	protected function apply_extension( string $field, $value, string $name, ?string $param ): ?string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $param reserved for future string-extension support.
		$extension = static::$extensions[ $name ];

		if ( $extension instanceof RuleInterface ) {
			return $this->apply_rule_object( $field, $value, $extension );
		}

		if ( $extension instanceof Closure ) {
			return $this->apply_closure_rule( $field, $value, $extension );
		}

		return null;
	}

	/**
	 * Apply a built-in named rule.
	 *
	 * @param string      $field Field name.
	 * @param mixed       $value Field value.
	 * @param string      $name  Rule name.
	 * @param string|null $param Rule parameter.
	 * @return string|null
	 */
	protected function apply_named_rule( string $field, $value, string $name, ?string $param ): ?string { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- switch dispatches 26 rules; extraction would obscure intent.
		$attr = $this->get_attribute_name( $field );

		switch ( $name ) {

			// -------------------------------------------------------
			// Existence
			// -------------------------------------------------------

			case 'required':
				if ( null === $value || '' === $value || ( is_array( $value ) && empty( $value ) ) ) {
					return $this->resolve_message(
						$field,
						'required',
						/* translators: %s: field name */
						sprintf( __( 'The %s field is required.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'required_if':
				list( $other_field, $other_value ) = explode( ',', $param ?? ',', 2 );
				$other                             = $this->array_get( $this->data, trim( $other_field ) );
				if ( trim( $other_value ) === (string) $other ) { // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- both sides dynamic.
					if ( null === $value || '' === $value ) {
						return $this->resolve_message(
							$field,
							'required_if',
							/* translators: 1: field name 2: other field 3: other value */
							sprintf( __( 'The %1$s field is required when %2$s is %3$s.', 'wpflint' ), $attr, trim( $other_field ), trim( $other_value ) )
						);
					}
				}
				break;

			case 'required_unless':
				list( $other_field, $other_value ) = explode( ',', $param ?? ',', 2 );
				$other                             = $this->array_get( $this->data, trim( $other_field ) );
				if ( trim( $other_value ) !== (string) $other ) { // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- both sides dynamic.
					if ( null === $value || '' === $value ) {
						return $this->resolve_message(
							$field,
							'required_unless',
							/* translators: 1: field name 2: other field 3: other value */
							sprintf( __( 'The %1$s field is required unless %2$s is %3$s.', 'wpflint' ), $attr, trim( $other_field ), trim( $other_value ) )
						);
					}
				}
				break;

			// -------------------------------------------------------
			// Type
			// -------------------------------------------------------

			case 'string':
				if ( null !== $value && ! is_string( $value ) ) {
					return $this->resolve_message(
						$field,
						'string',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must be a string.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'integer':
				if ( null !== $value && ( ! is_numeric( $value ) || (string) (int) $value !== (string) $value ) ) {
					return $this->resolve_message(
						$field,
						'integer',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must be an integer.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'numeric':
				if ( null !== $value && ! is_numeric( $value ) ) {
					return $this->resolve_message(
						$field,
						'numeric',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must be numeric.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'boolean':
				$allowed = array( true, false, 0, 1, '0', '1', 'true', 'false' );
				if ( null !== $value && ! in_array( $value, $allowed, true ) ) {
					return $this->resolve_message(
						$field,
						'boolean',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must be true or false.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'array':
				if ( null !== $value && ! is_array( $value ) ) {
					return $this->resolve_message(
						$field,
						'array',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must be an array.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'json':
				if ( null !== $value ) {
					json_decode( (string) $value );
					if ( JSON_ERROR_NONE !== json_last_error() ) {
						return $this->resolve_message(
							$field,
							'json',
							/* translators: %s: field name */
							sprintf( __( 'The %s field must be valid JSON.', 'wpflint' ), $attr )
						);
					}
				}
				break;

			// -------------------------------------------------------
			// Size / Length
			// -------------------------------------------------------

			case 'min':
				$error = $this->check_min( $field, $value, (string) $param, $attr );
				if ( null !== $error ) {
					return $error;
				}
				break;

			case 'max':
				$error = $this->check_max( $field, $value, (string) $param, $attr );
				if ( null !== $error ) {
					return $error;
				}
				break;

			case 'between':
				$parts = explode( ',', $param ?? ',' );
				$min   = (float) ( $parts[0] ?? 0 );
				$max   = (float) ( $parts[1] ?? 0 );
				if ( null !== $value ) {
					$size = $this->get_size( $value );
					if ( $size < $min || $size > $max ) {
						return $this->resolve_message(
							$field,
							'between',
							/* translators: 1: field name 2: min 3: max */
							sprintf( __( 'The %1$s field must be between %2$s and %3$s.', 'wpflint' ), $attr, $min, $max )
						);
					}
				}
				break;

			case 'size':
				if ( null !== $value ) {
					$size     = $this->get_size( $value );
					$expected = (float) $param;
					if ( $size !== $expected ) {
						return $this->resolve_message(
							$field,
							'size',
							/* translators: 1: field name 2: expected size */
							sprintf( __( 'The %1$s field must be exactly %2$s.', 'wpflint' ), $attr, $param )
						);
					}
				}
				break;

			case 'digits':
				if ( null !== $value ) {
					$str = (string) $value;
					if ( ! ctype_digit( $str ) || strlen( $str ) !== (int) $param ) {
						return $this->resolve_message(
							$field,
							'digits',
							/* translators: 1: field name 2: required digit count */
							sprintf( __( 'The %1$s field must be %2$s digits.', 'wpflint' ), $attr, $param )
						);
					}
				}
				break;

			// -------------------------------------------------------
			// Format
			// -------------------------------------------------------

			case 'email':
				if ( null !== $value && ! is_email( (string) $value ) ) {
					return $this->resolve_message(
						$field,
						'email',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must be a valid email address.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'url':
				if ( null !== $value && false === filter_var( (string) $value, FILTER_VALIDATE_URL ) ) {
					return $this->resolve_message(
						$field,
						'url',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must be a valid URL.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'ip':
				if ( null !== $value && false === filter_var( (string) $value, FILTER_VALIDATE_IP ) ) {
					return $this->resolve_message(
						$field,
						'ip',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must be a valid IP address.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'uuid':
				$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
				if ( null !== $value && ! preg_match( $pattern, (string) $value ) ) {
					return $this->resolve_message(
						$field,
						'uuid',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must be a valid UUID.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'regex':
				if ( null !== $value && ! preg_match( (string) $param, (string) $value ) ) {
					return $this->resolve_message(
						$field,
						'regex',
						/* translators: %s: field name */
						sprintf( __( 'The %s field format is invalid.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'alpha':
				if ( null !== $value && ! ctype_alpha( (string) $value ) ) {
					return $this->resolve_message(
						$field,
						'alpha',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must only contain letters.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'alpha_num':
				if ( null !== $value && ! ctype_alnum( (string) $value ) ) {
					return $this->resolve_message(
						$field,
						'alpha_num',
						/* translators: %s: field name */
						sprintf( __( 'The %s field must only contain letters and numbers.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'alpha_dash':
				if ( null !== $value && ! preg_match( '/^[a-zA-Z0-9_\-]+$/', (string) $value ) ) {
					return $this->resolve_message(
						$field,
						'alpha_dash',
						/* translators: %s: field name */
						sprintf( __( 'The %s field may only contain letters, numbers, dashes, and underscores.', 'wpflint' ), $attr )
					);
				}
				break;

			// -------------------------------------------------------
			// Comparison
			// -------------------------------------------------------

			case 'in':
				$allowed = explode( ',', (string) $param );
				if ( null !== $value && ! in_array( (string) $value, $allowed, true ) ) {
					return $this->resolve_message(
						$field,
						'in',
						/* translators: 1: field name 2: allowed values */
						sprintf( __( 'The selected %1$s is invalid. Allowed: %2$s.', 'wpflint' ), $attr, $param )
					);
				}
				break;

			case 'not_in':
				$disallowed = explode( ',', (string) $param );
				if ( null !== $value && in_array( (string) $value, $disallowed, true ) ) {
					return $this->resolve_message(
						$field,
						'not_in',
						/* translators: %s: field name */
						sprintf( __( 'The selected %s is invalid.', 'wpflint' ), $attr )
					);
				}
				break;

			case 'same':
				$other = $this->array_get( $this->data, (string) $param );
				if ( null !== $value && $value !== $other ) {
					return $this->resolve_message(
						$field,
						'same',
						/* translators: 1: field name 2: other field name */
						sprintf( __( 'The %1$s field must match %2$s.', 'wpflint' ), $attr, $param )
					);
				}
				break;

			case 'different':
				$other = $this->array_get( $this->data, (string) $param );
				if ( null !== $value && $value === $other ) {
					return $this->resolve_message(
						$field,
						'different',
						/* translators: 1: field name 2: other field name */
						sprintf( __( 'The %1$s field and %2$s must be different.', 'wpflint' ), $attr, $param )
					);
				}
				break;

			case 'confirmed':
				$confirmation_field = $field . '_confirmation';
				$confirmation       = $this->array_get( $this->data, $confirmation_field );
				if ( $value !== $confirmation ) {
					return $this->resolve_message(
						$field,
						'confirmed',
						/* translators: %s: field name */
						sprintf( __( 'The %s confirmation does not match.', 'wpflint' ), $attr )
					);
				}
				break;
		}

		return null;
	}

	// ---------------------------------------------------------------
	// Size helpers
	// ---------------------------------------------------------------

	/**
	 * Get the comparable size of a value.
	 *
	 * @param mixed $value Value to measure.
	 * @return float
	 */
	protected function get_size( $value ): float {
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}
		if ( is_array( $value ) ) {
			return (float) count( $value );
		}
		return (float) strlen( (string) $value );
	}

	/**
	 * Check the min rule.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @param string $param Min parameter.
	 * @param string $attr  Friendly attribute name.
	 * @return string|null
	 */
	protected function check_min( string $field, $value, string $param, string $attr ): ?string {
		if ( null === $value ) {
			return null;
		}

		$min = (float) $param;

		if ( is_array( $value ) ) {
			if ( count( $value ) < $min ) {
				return $this->resolve_message(
					$field,
					'min',
					/* translators: 1: field name 2: minimum count */
					sprintf( __( 'The %1$s field must have at least %2$s items.', 'wpflint' ), $attr, $param )
				);
			}
		} elseif ( is_numeric( $value ) ) {
			if ( (float) $value < $min ) {
				return $this->resolve_message(
					$field,
					'min',
					/* translators: 1: field name 2: minimum value */
					sprintf( __( 'The %1$s field must be at least %2$s.', 'wpflint' ), $attr, $param )
				);
			}
		} elseif ( is_string( $value ) && strlen( $value ) < (int) $min ) {
			return $this->resolve_message(
				$field,
				'min',
				/* translators: 1: field name 2: minimum length */
				sprintf( __( 'The %1$s field must be at least %2$s characters.', 'wpflint' ), $attr, $param )
			);
		}

		return null;
	}

	/**
	 * Check the max rule.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @param string $param Max parameter.
	 * @param string $attr  Friendly attribute name.
	 * @return string|null
	 */
	protected function check_max( string $field, $value, string $param, string $attr ): ?string {
		if ( null === $value ) {
			return null;
		}

		$max = (float) $param;

		if ( is_array( $value ) ) {
			if ( count( $value ) > $max ) {
				return $this->resolve_message(
					$field,
					'max',
					/* translators: 1: field name 2: maximum count */
					sprintf( __( 'The %1$s field must not have more than %2$s items.', 'wpflint' ), $attr, $param )
				);
			}
		} elseif ( is_numeric( $value ) ) {
			if ( (float) $value > $max ) {
				return $this->resolve_message(
					$field,
					'max',
					/* translators: 1: field name 2: maximum value */
					sprintf( __( 'The %1$s field must not be greater than %2$s.', 'wpflint' ), $attr, $param )
				);
			}
		} elseif ( is_string( $value ) && strlen( $value ) > (int) $max ) {
			return $this->resolve_message(
				$field,
				'max',
				/* translators: 1: field name 2: maximum length */
				sprintf( __( 'The %1$s field must not be greater than %2$s characters.', 'wpflint' ), $attr, $param )
			);
		}

		return null;
	}

	// ---------------------------------------------------------------
	// Message resolution
	// ---------------------------------------------------------------

	/**
	 * Resolve the error message for a field + rule, checking custom messages first.
	 *
	 * Custom message lookup order:
	 *   1. 'field.rule' — e.g. 'email.required'
	 *   2. 'rule'       — e.g. 'required'
	 *   3. $default     — built-in translated message
	 *
	 * @param string $field   Field name.
	 * @param string $rule    Rule name.
	 * @param string $default Default message.
	 * @return string
	 */
	protected function resolve_message( string $field, string $rule, string $default ): string {
		$field_rule_key = $field . '.' . $rule;

		if ( isset( $this->custom_messages[ $field_rule_key ] ) ) {
			return str_replace(
				':attribute',
				$this->get_attribute_name( $field ),
				$this->custom_messages[ $field_rule_key ]
			);
		}

		if ( isset( $this->custom_messages[ $rule ] ) ) {
			return str_replace(
				':attribute',
				$this->get_attribute_name( $field ),
				$this->custom_messages[ $rule ]
			);
		}

		return $default;
	}

	/**
	 * Get the friendly attribute name for a field.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	protected function get_attribute_name( string $field ): string {
		return $this->attributes[ $field ] ?? str_replace( '_', ' ', $field );
	}

	// ---------------------------------------------------------------
	// Dot-notation + wildcard helpers
	// ---------------------------------------------------------------

	/**
	 * Check whether a dot-notated key exists (not just non-null) in data.
	 *
	 * @param string $key  Dot-notated key.
	 * @param array  $data Source array.
	 * @return bool
	 */
	protected function key_exists( string $key, array $data ): bool {
		$segments = explode( '.', $key );
		$current  = $data;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return false;
			}
			$current = $current[ $segment ];
		}

		return true;
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
	 * @param array  $array Target array (by reference).
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

	/**
	 * Expand a wildcard pattern into concrete field paths.
	 *
	 * @param string $pattern Dot-notated pattern with *.
	 * @param array  $data    Source data.
	 * @return string[]
	 */
	protected function expand_wildcard( string $pattern, array $data ): array {
		return $this->expand_segments( explode( '.', $pattern ), $data, '' );
	}

	/**
	 * Recursively expand wildcard segments.
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
				$child      = $data[ $index ];
				$paths      = array_merge( $paths, $this->expand_segments( $segments, $child, $new_prefix ) );
			}
		} else {
			$new_prefix = $prefix . $segment . '.';
			$next_data  = is_array( $data ) && array_key_exists( $segment, $data ) ? $data[ $segment ] : null;
			$paths      = $this->expand_segments( $segments, $next_data, $new_prefix );
		}

		return $paths;
	}
}
