# Validation

WPFlint ships a standalone `Validator` class inspired by Laravel's validation engine, plus a `ValidationResult` value object and `ValidationException` for strict-mode usage. The HTTP `Request` class delegates all validation to `Validator` internally.

## Quick start

```php
use WPFlint\Validation\Validator;

$result = Validator::make(
    [ 'email' => 'bad-email', 'age' => '' ],
    [ 'email' => 'required|email', 'age' => 'required|integer|min:18' ]
);

if ( $result->fails() ) {
    wp_send_json_error( $result->errors() );
}

$clean = $result->validated(); // only fields that passed
```

## ValidationResult API

| Method | Returns | Description |
|---|---|---|
| `passes()` | `bool` | All fields passed. |
| `fails()` | `bool` | Any field failed. |
| `errors()` | `array<string,string>` | All errors keyed by field. |
| `first(?string $field)` | `?string` | First error overall, or for a field. |
| `has_error(string $field)` | `bool` | Whether a specific field failed. |
| `validated()` | `array` | Fields that passed all rules. |
| `value(string $key, $default)` | `mixed` | Single validated value (dot notation). |
| `throw_if_fails()` | `$this` | Throws `ValidationException` on failure. |

### Strict mode

```php
Validator::make( $data, $rules )->throw_if_fails();

try {
    Validator::make( $data, $rules )->throw_if_fails();
} catch ( \WPFlint\Validation\ValidationException $e ) {
    $e->errors();        // array of messages
    $e->first('email');  // first error for a field
}
```

## Built-in rules

### Presence

| Rule | Description |
|---|---|
| `required` | Field must be present and not empty (`''`, `null`, `[]`). |
| `required_if:other_field,value` | Required when `other_field` equals `value`. |
| `required_unless:other_field,value` | Required unless `other_field` equals `value`. |
| `sometimes` | Skip validation if the field is absent from the input. |
| `nullable` | Allow `null` — skip remaining rules when value is `null`. |

### Type

| Rule | Description |
|---|---|
| `string` | Must be a string. |
| `integer` | Must be an integer (or numeric string). |
| `numeric` | Must be numeric (int or float string). |
| `boolean` | Must be `true`, `false`, `1`, `0`, `'true'`, `'false'`, `'1'`, `'0'`. |
| `array` | Must be an array. |
| `email` | Must be a valid email address. |
| `url` | Must be a valid URL. |
| `date` | Must be parseable by `strtotime()`. |

### Comparison

| Rule | Description |
|---|---|
| `min:N` | Minimum value (numeric) or minimum length (string/array). |
| `max:N` | Maximum value (numeric) or maximum length (string/array). |
| `between:min,max` | Value/length between min and max inclusive. |
| `size:N` | Exact value (numeric) or length (string/array). |
| `in:a,b,c` | Value must be one of the comma-separated list. |
| `not_in:a,b,c` | Value must not be in the list. |
| `same:other_field` | Must match the value of another field. |
| `different:other_field` | Must differ from another field. |
| `confirmed` | Shorthand for `same:{field}_confirmation`. |

### String format

| Rule | Description |
|---|---|
| `alpha` | Only letters. |
| `alpha_num` | Letters and digits. |
| `alpha_dash` | Letters, digits, hyphens, underscores. |
| `regex:/pattern/` | Must match the regular expression. |

### Other

| Rule | Description |
|---|---|
| `bail` | Stop validating this field after the first failure. |

## Specifying rules

Rules can be a pipe-delimited string or an array:

```php
// String format
'email' => 'required|email|max:255'

// Array format — useful when mixing objects and strings
'email' => [ 'required', 'email', 'max:255' ]

// Mix with RuleInterface objects or closures
'username' => [ 'required', new UniqueUsername(), 'min:3' ]
```

## Dot-notation and nested fields

```php
$data = [
    'user' => [ 'email' => 'test@example.com', 'age' => 25 ],
];

Validator::make( $data, [
    'user.email' => 'required|email',
    'user.age'   => 'required|integer|min:18',
] );
```

## Wildcards

Use `*` to validate every element of an array:

```php
$data = [
    'items' => [
        [ 'product_id' => 1, 'qty' => 2 ],
        [ 'product_id' => 5, 'qty' => 0 ],
    ],
];

Validator::make( $data, [
    'items'            => 'required|array|min:1',
    'items.*.product_id' => 'required|integer',
    'items.*.qty'        => 'required|integer|min:1',
] );
```

## Custom error messages

Pass a third argument to `Validator::make()`. Keys can be `field.rule` or just `rule`:

```php
Validator::make( $data, $rules, [
    'email.required' => 'Please provide your email.',
    'email.email'    => ':attribute must be a valid address.',
    'required'       => 'The :attribute field cannot be blank.',  // applies to all fields
] );
```

`:attribute` is replaced with the human-readable field name (underscores → spaces).

## Custom attribute names

Pass a fourth argument to use friendlier names in messages:

```php
Validator::make( $data, $rules, [], [
    'dob'           => 'date of birth',
    'user.email'    => 'email address',
] );
```

## Closure rules

```php
Validator::make( $data, [
    'username' => [
        'required',
        function( $field, $value, $fail ) {
            if ( username_exists( $value ) ) {
                $fail( 'That username is already taken.' );
            }
        },
    ],
] );
```

## RuleInterface objects

Implement `WPFlint\Validation\Rules\RuleInterface` for reusable rules:

```php
use WPFlint\Validation\Rules\RuleInterface;

class UniqueEmail implements RuleInterface {
    public function passes( $value ): bool {
        return ! email_exists( $value );
    }
    public function message(): string {
        return __( 'The :attribute is already registered.', 'my-plugin' );
    }
}

// Use inline
Validator::make( $data, [
    'email' => [ 'required', 'email', new UniqueEmail() ],
] );
```

Generate a stub with WP-CLI:

```bash
wp wpflint make:rule UniqueEmail
wp wpflint make:rule PhoneNumber --path=app/Rules
```

## Global custom rules

Register reusable rules globally so they can be referenced by name in rule strings:

```php
Validator::extend( 'phone', new PhoneNumber() );

// Or as a closure
Validator::extend( 'phone', function( $field, $value, $fail ) {
    if ( ! preg_match( '/^\+?[0-9\s\-]{7,15}$/', $value ) ) {
        $fail( 'The :attribute must be a valid phone number.' );
    }
} );

// Now use by name
Validator::make( $data, [ 'phone' => 'required|phone' ] );
```

## bail and sometimes

```php
// bail — stop on first failure for this field
'email' => 'bail|required|email|max:255'

// sometimes — skip entirely if field is absent
'newsletter' => 'sometimes|boolean'

// nullable — treat null as valid and skip remaining rules
'bio' => 'nullable|string|max:500'
```

## Using with Request

`Request` subclasses call `Validator::make()` internally when `validate()` is invoked. Define rules in `rules()`, messages in (override) and attributes in (override) if needed. No direct interaction with `Validator` is required:

```php
class StorePostRequest extends Request {
    public function authorize(): bool {
        return current_user_can( 'publish_posts' );
    }
    public function rules(): array {
        return [
            'title'   => 'required|string|max:200',
            'content' => 'required|string',
            'status'  => 'required|in:draft,publish',
        ];
    }
    public function sanitize(): array {
        return [
            'title'   => 'sanitize_text_field',
            'content' => 'wp_kses_post',
            'status'  => 'sanitize_text_field',
        ];
    }
}
```
