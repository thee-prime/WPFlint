<?php

declare(strict_types=1);

namespace WPFlint\Tests\Validation;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Validation\Validator;
use WPFlint\Validation\ValidationResult;
use WPFlint\Validation\ValidationException;
use WPFlint\Validation\Rules\RuleInterface;

/**
 * @covers \WPFlint\Validation\Validator
 * @covers \WPFlint\Validation\ValidationResult
 * @covers \WPFlint\Validation\ValidationException
 * @covers \WPFlint\Validation\Rules\RuleInterface
 */
class ValidatorTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'is_email' )->andReturnUsing(
			fn( $v ) => (bool) filter_var( $v, FILTER_VALIDATE_EMAIL )
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Basics — passes / fails / validated / errors
	// ---------------------------------------------------------------

	public function testPassesWhenAllRulesPass(): void {
		$result = Validator::make(
			array( 'name' => 'Alice' ),
			array( 'name' => 'required|string' )
		);

		$this->assertTrue( $result->passes() );
		$this->assertFalse( $result->fails() );
	}

	public function testFailsWhenRuleBreached(): void {
		$result = Validator::make(
			array( 'name' => '' ),
			array( 'name' => 'required' )
		);

		$this->assertTrue( $result->fails() );
		$this->assertArrayHasKey( 'name', $result->errors() );
	}

	public function testValidatedContainsOnlyPassedFields(): void {
		$result = Validator::make(
			array( 'email' => 'bad', 'age' => 25 ),
			array( 'email' => 'email', 'age' => 'integer' )
		);

		$validated = $result->validated();
		$this->assertArrayNotHasKey( 'email', $validated );
		$this->assertArrayHasKey( 'age', $validated );
	}

	public function testFirstReturnsFirstErrorOverall(): void {
		$result = Validator::make(
			array( 'name' => '' ),
			array( 'name' => 'required' )
		);

		$this->assertIsString( $result->first() );
	}

	public function testFirstForFieldReturnsFieldError(): void {
		$result = Validator::make(
			array( 'email' => 'notanemail' ),
			array( 'email' => 'email' )
		);

		$this->assertNotNull( $result->first( 'email' ) );
		$this->assertNull( $result->first( 'nonexistent' ) );
	}

	public function testHasErrorChecksField(): void {
		$result = Validator::make(
			array( 'age' => 'nope' ),
			array( 'age' => 'integer' )
		);

		$this->assertTrue( $result->has_error( 'age' ) );
		$this->assertFalse( $result->has_error( 'name' ) );
	}

	public function testValueRetrievesFromValidated(): void {
		$result = Validator::make(
			array( 'user' => array( 'name' => 'Bob' ) ),
			array( 'user.name' => 'required|string' )
		);

		$this->assertSame( 'Bob', $result->value( 'user.name' ) );
		$this->assertSame( 'default', $result->value( 'user.missing', 'default' ) );
	}

	public function testEmptyRulesPassesAllData(): void {
		$result = Validator::make(
			array( 'a' => 1 ),
			array()
		);

		$this->assertTrue( $result->passes() );
	}

	// ---------------------------------------------------------------
	// Rules — required
	// ---------------------------------------------------------------

	public function testRequiredFailsOnNull(): void {
		$r = Validator::make( array( 'f' => null ), array( 'f' => 'required' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testRequiredFailsOnEmptyString(): void {
		$r = Validator::make( array( 'f' => '' ), array( 'f' => 'required' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testRequiredFailsOnEmptyArray(): void {
		$r = Validator::make( array( 'f' => array() ), array( 'f' => 'required' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testRequiredPassesOnZero(): void {
		$r = Validator::make( array( 'f' => 0 ), array( 'f' => 'required' ) );
		$this->assertTrue( $r->passes() );
	}

	// ---------------------------------------------------------------
	// Rules — required_if / required_unless
	// ---------------------------------------------------------------

	public function testRequiredIfFailsWhenConditionMet(): void {
		$r = Validator::make(
			array( 'role' => 'admin', 'code' => '' ),
			array( 'code' => 'required_if:role,admin' )
		);
		$this->assertTrue( $r->fails() );
	}

	public function testRequiredIfPassesWhenConditionNotMet(): void {
		$r = Validator::make(
			array( 'role' => 'user', 'code' => '' ),
			array( 'code' => 'required_if:role,admin' )
		);
		$this->assertTrue( $r->passes() );
	}

	public function testRequiredUnlessFailsWhenConditionNotMet(): void {
		$r = Validator::make(
			array( 'role' => 'editor', 'code' => '' ),
			array( 'code' => 'required_unless:role,admin' )
		);
		$this->assertTrue( $r->fails() );
	}

	public function testRequiredUnlessPassesWhenConditionMet(): void {
		$r = Validator::make(
			array( 'role' => 'admin', 'code' => '' ),
			array( 'code' => 'required_unless:role,admin' )
		);
		$this->assertTrue( $r->passes() );
	}

	// ---------------------------------------------------------------
	// Rules — sometimes / nullable / bail
	// ---------------------------------------------------------------

	public function testSometimesSkipsAbsentField(): void {
		$r = Validator::make(
			array(),
			array( 'name' => 'sometimes|required|string' )
		);
		$this->assertTrue( $r->passes() );
	}

	public function testSometimesValidatesWhenPresent(): void {
		$r = Validator::make(
			array( 'name' => '' ),
			array( 'name' => 'sometimes|required|string' )
		);
		$this->assertTrue( $r->fails() );
	}

	public function testNullableAllowsNull(): void {
		$r = Validator::make(
			array( 'bio' => null ),
			array( 'bio' => 'nullable|string' )
		);
		$this->assertTrue( $r->passes() );
	}

	public function testNullableStillValidatesNonNull(): void {
		$r = Validator::make(
			array( 'age' => 'notanint' ),
			array( 'age' => 'nullable|integer' )
		);
		$this->assertTrue( $r->fails() );
	}

	public function testBailStopsOnFirstFailure(): void {
		$r = Validator::make(
			array( 'email' => '' ),
			array( 'email' => 'bail|required|email|max:100' )
		);
		// Only 1 error (required), not multiple.
		$this->assertCount( 1, $r->errors() );
	}

	// ---------------------------------------------------------------
	// Rules — type
	// ---------------------------------------------------------------

	public function testStringPassesOnString(): void {
		$r = Validator::make( array( 'n' => 'hello' ), array( 'n' => 'string' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testStringFailsOnArray(): void {
		$r = Validator::make( array( 'n' => array() ), array( 'n' => 'string' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testIntegerPassesOnIntString(): void {
		$r = Validator::make( array( 'n' => '42' ), array( 'n' => 'integer' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testIntegerFailsOnFloat(): void {
		$r = Validator::make( array( 'n' => '3.14' ), array( 'n' => 'integer' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testNumericPassesOnFloat(): void {
		$r = Validator::make( array( 'n' => '3.14' ), array( 'n' => 'numeric' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testBooleanPassesOnZeroAndOne(): void {
		$r1 = Validator::make( array( 'b' => '1' ), array( 'b' => 'boolean' ) );
		$r2 = Validator::make( array( 'b' => '0' ), array( 'b' => 'boolean' ) );
		$this->assertTrue( $r1->passes() );
		$this->assertTrue( $r2->passes() );
	}

	public function testBooleanFailsOnArbitraryString(): void {
		$r = Validator::make( array( 'b' => 'yes' ), array( 'b' => 'boolean' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testArrayPassesOnArray(): void {
		$r = Validator::make( array( 'items' => array( 1, 2 ) ), array( 'items' => 'array' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testJsonPassesOnValidJson(): void {
		$r = Validator::make( array( 'data' => '{"key":"val"}' ), array( 'data' => 'json' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testJsonFailsOnInvalidJson(): void {
		$r = Validator::make( array( 'data' => '{bad}' ), array( 'data' => 'json' ) );
		$this->assertTrue( $r->fails() );
	}

	// ---------------------------------------------------------------
	// Rules — size / length
	// ---------------------------------------------------------------

	public function testMinPassesOnStringLength(): void {
		$r = Validator::make( array( 'p' => 'hello' ), array( 'p' => 'min:3' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testMinFailsOnShortString(): void {
		$r = Validator::make( array( 'p' => 'hi' ), array( 'p' => 'min:5' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testMinPassesOnNumericValue(): void {
		$r = Validator::make( array( 'age' => '18' ), array( 'age' => 'min:18' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testMinFailsOnSmallNumeric(): void {
		$r = Validator::make( array( 'age' => '17' ), array( 'age' => 'min:18' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testMaxPassesOnShortString(): void {
		$r = Validator::make( array( 'n' => 'hi' ), array( 'n' => 'max:10' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testMaxFailsOnLongString(): void {
		$r = Validator::make( array( 'n' => str_repeat( 'a', 101 ) ), array( 'n' => 'max:100' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testBetweenPasses(): void {
		$r = Validator::make( array( 'age' => '25' ), array( 'age' => 'between:18,65' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testBetweenFails(): void {
		$r = Validator::make( array( 'age' => '17' ), array( 'age' => 'between:18,65' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testSizePasses(): void {
		$r = Validator::make( array( 'code' => 'ABC' ), array( 'code' => 'size:3' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testSizeFails(): void {
		$r = Validator::make( array( 'code' => 'AB' ), array( 'code' => 'size:3' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testDigitsPasses(): void {
		$r = Validator::make( array( 'pin' => '1234' ), array( 'pin' => 'digits:4' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testDigitsFailsOnWrongLength(): void {
		$r = Validator::make( array( 'pin' => '123' ), array( 'pin' => 'digits:4' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testDigitsFailsOnNonDigit(): void {
		$r = Validator::make( array( 'pin' => '12a4' ), array( 'pin' => 'digits:4' ) );
		$this->assertTrue( $r->fails() );
	}

	// ---------------------------------------------------------------
	// Rules — format
	// ---------------------------------------------------------------

	public function testEmailPassesOnValidEmail(): void {
		$r = Validator::make( array( 'e' => 'user@example.com' ), array( 'e' => 'email' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testEmailFailsOnInvalidEmail(): void {
		$r = Validator::make( array( 'e' => 'notanemail' ), array( 'e' => 'email' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testUrlPassesOnValidUrl(): void {
		$r = Validator::make( array( 'u' => 'https://example.com' ), array( 'u' => 'url' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testUrlFailsOnInvalidUrl(): void {
		$r = Validator::make( array( 'u' => 'not a url' ), array( 'u' => 'url' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testIpPassesOnValidIp(): void {
		$r = Validator::make( array( 'ip' => '192.168.1.1' ), array( 'ip' => 'ip' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testIpFailsOnInvalidIp(): void {
		$r = Validator::make( array( 'ip' => '999.999.999.999' ), array( 'ip' => 'ip' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testUuidPassesOnValidUuid(): void {
		$r = Validator::make(
			array( 'id' => '550e8400-e29b-41d4-a716-446655440000' ),
			array( 'id' => 'uuid' )
		);
		$this->assertTrue( $r->passes() );
	}

	public function testUuidFailsOnInvalidUuid(): void {
		$r = Validator::make( array( 'id' => 'not-a-uuid' ), array( 'id' => 'uuid' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testRegexPasses(): void {
		$r = Validator::make(
			array( 'code' => 'ABC' ),
			array( 'code' => 'regex:/^[A-Z]{3}$/' )
		);
		$this->assertTrue( $r->passes() );
	}

	public function testRegexFails(): void {
		$r = Validator::make(
			array( 'code' => 'abc' ),
			array( 'code' => 'regex:/^[A-Z]{3}$/' )
		);
		$this->assertTrue( $r->fails() );
	}

	public function testAlphaPasses(): void {
		$r = Validator::make( array( 'n' => 'Hello' ), array( 'n' => 'alpha' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testAlphaFailsWithNumbers(): void {
		$r = Validator::make( array( 'n' => 'Hello1' ), array( 'n' => 'alpha' ) );
		$this->assertTrue( $r->fails() );
	}

	public function testAlphaNumPasses(): void {
		$r = Validator::make( array( 'n' => 'Hello123' ), array( 'n' => 'alpha_num' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testAlphaDashPasses(): void {
		$r = Validator::make( array( 'n' => 'hello-world_1' ), array( 'n' => 'alpha_dash' ) );
		$this->assertTrue( $r->passes() );
	}

	public function testAlphaDashFailsWithSpace(): void {
		$r = Validator::make( array( 'n' => 'hello world' ), array( 'n' => 'alpha_dash' ) );
		$this->assertTrue( $r->fails() );
	}

	// ---------------------------------------------------------------
	// Rules — comparison
	// ---------------------------------------------------------------

	public function testInPasses(): void {
		$r = Validator::make(
			array( 's' => 'active' ),
			array( 's' => 'in:active,inactive,pending' )
		);
		$this->assertTrue( $r->passes() );
	}

	public function testInFails(): void {
		$r = Validator::make(
			array( 's' => 'deleted' ),
			array( 's' => 'in:active,inactive,pending' )
		);
		$this->assertTrue( $r->fails() );
	}

	public function testNotInPasses(): void {
		$r = Validator::make(
			array( 's' => 'active' ),
			array( 's' => 'not_in:banned,deleted' )
		);
		$this->assertTrue( $r->passes() );
	}

	public function testNotInFails(): void {
		$r = Validator::make(
			array( 's' => 'banned' ),
			array( 's' => 'not_in:banned,deleted' )
		);
		$this->assertTrue( $r->fails() );
	}

	public function testSamePasses(): void {
		$r = Validator::make(
			array( 'pass' => 'secret', 'pass_confirm' => 'secret' ),
			array( 'pass' => 'same:pass_confirm' )
		);
		$this->assertTrue( $r->passes() );
	}

	public function testSameFails(): void {
		$r = Validator::make(
			array( 'pass' => 'secret', 'pass_confirm' => 'different' ),
			array( 'pass' => 'same:pass_confirm' )
		);
		$this->assertTrue( $r->fails() );
	}

	public function testDifferentPasses(): void {
		$r = Validator::make(
			array( 'a' => 'foo', 'b' => 'bar' ),
			array( 'a' => 'different:b' )
		);
		$this->assertTrue( $r->passes() );
	}

	public function testDifferentFails(): void {
		$r = Validator::make(
			array( 'a' => 'foo', 'b' => 'foo' ),
			array( 'a' => 'different:b' )
		);
		$this->assertTrue( $r->fails() );
	}

	public function testConfirmedPasses(): void {
		$r = Validator::make(
			array( 'password' => 'secret', 'password_confirmation' => 'secret' ),
			array( 'password' => 'confirmed' )
		);
		$this->assertTrue( $r->passes() );
	}

	public function testConfirmedFails(): void {
		$r = Validator::make(
			array( 'password' => 'secret', 'password_confirmation' => 'wrong' ),
			array( 'password' => 'confirmed' )
		);
		$this->assertTrue( $r->fails() );
	}

	// ---------------------------------------------------------------
	// Dot-notation and wildcards
	// ---------------------------------------------------------------

	public function testDotNotationValidatesNestedField(): void {
		$r = Validator::make(
			array( 'user' => array( 'email' => 'bad' ) ),
			array( 'user.email' => 'email' )
		);
		$this->assertTrue( $r->fails() );
		$this->assertArrayHasKey( 'user.email', $r->errors() );
	}

	public function testWildcardValidatesAllArrayItems(): void {
		$r = Validator::make(
			array(
				'items' => array(
					array( 'qty' => 2 ),
					array( 'qty' => 0 ),
				),
			),
			array( 'items.*.qty' => 'required|integer|min:1' )
		);

		$this->assertTrue( $r->fails() );
		$this->assertArrayHasKey( 'items.1.qty', $r->errors() );
	}

	public function testWildcardPassesWhenAllItemsValid(): void {
		$r = Validator::make(
			array(
				'items' => array(
					array( 'qty' => 2 ),
					array( 'qty' => 3 ),
				),
			),
			array( 'items.*.qty' => 'required|integer|min:1' )
		);

		$this->assertTrue( $r->passes() );
	}

	// ---------------------------------------------------------------
	// Array-format rules
	// ---------------------------------------------------------------

	public function testArrayFormatRulesWork(): void {
		$r = Validator::make(
			array( 'email' => 'user@example.com', 'age' => 25 ),
			array(
				'email' => array( 'required', 'email' ),
				'age'   => array( 'required', 'integer', 'min:18' ),
			)
		);

		$this->assertTrue( $r->passes() );
	}

	// ---------------------------------------------------------------
	// Custom messages
	// ---------------------------------------------------------------

	public function testCustomFieldRuleMessageOverridesDefault(): void {
		$r = Validator::make(
			array( 'email' => '' ),
			array( 'email' => 'required' ),
			array( 'email.required' => 'Please enter your email.' )
		);

		$this->assertSame( 'Please enter your email.', $r->first( 'email' ) );
	}

	public function testCustomRuleMessageOverridesDefault(): void {
		$r = Validator::make(
			array( 'name' => '' ),
			array( 'name' => 'required' ),
			array( 'required' => 'The :attribute is required.' )
		);

		$this->assertStringContainsString( 'name', $r->first( 'name' ) );
	}

	// ---------------------------------------------------------------
	// Custom attributes
	// ---------------------------------------------------------------

	public function testCustomAttributeNameAppearedInMessage(): void {
		$r = Validator::make(
			array( 'first_name' => '' ),
			array( 'first_name' => 'required' ),
			array(),
			array( 'first_name' => 'first name' )
		);

		$this->assertStringContainsString( 'first name', $r->first( 'first_name' ) );
	}

	// ---------------------------------------------------------------
	// Closure rules
	// ---------------------------------------------------------------

	public function testClosureRulePassesOnValid(): void {
		$r = Validator::make(
			array( 'code' => 'ABC' ),
			array(
				'code' => array(
					'required',
					function ( $field, $value, $fail ) {
						if ( $value !== strtoupper( $value ) ) {
							$fail( 'The :attribute must be uppercase.' );
						}
					},
				),
			)
		);

		$this->assertTrue( $r->passes() );
	}

	public function testClosureRuleFailsOnInvalid(): void {
		$r = Validator::make(
			array( 'code' => 'abc' ),
			array(
				'code' => array(
					function ( $field, $value, $fail ) {
						if ( $value !== strtoupper( $value ) ) {
							$fail( 'The :attribute must be uppercase.' );
						}
					},
				),
			)
		);

		$this->assertTrue( $r->fails() );
	}

	// ---------------------------------------------------------------
	// RuleInterface objects
	// ---------------------------------------------------------------

	public function testRuleObjectPassesOnValid(): void {
		$rule = new class implements RuleInterface {
			public function passes( $value ): bool { return $value === strtoupper( $value ); }
			public function message(): string { return 'The :attribute must be uppercase.'; }
		};

		$r = Validator::make(
			array( 'code' => 'ABC' ),
			array( 'code' => array( $rule ) )
		);

		$this->assertTrue( $r->passes() );
	}

	public function testRuleObjectFailsOnInvalid(): void {
		$rule = new class implements RuleInterface {
			public function passes( $value ): bool { return $value === strtoupper( $value ); }
			public function message(): string { return 'The :attribute must be uppercase.'; }
		};

		$r = Validator::make(
			array( 'code' => 'abc' ),
			array( 'code' => array( $rule ) )
		);

		$this->assertTrue( $r->fails() );
		$this->assertStringContainsString( 'code', $r->first( 'code' ) );
	}

	// ---------------------------------------------------------------
	// Global extend()
	// ---------------------------------------------------------------

	public function testExtendRegistersGlobalRule(): void {
		Validator::extend(
			'uppercase_test',
			function ( $field, $value, $fail ) {
				if ( $value !== strtoupper( $value ) ) {
					$fail( 'The :attribute must be uppercase.' );
				}
			}
		);

		$pass = Validator::make( array( 'c' => 'ABC' ), array( 'c' => 'uppercase_test' ) );
		$fail = Validator::make( array( 'c' => 'abc' ), array( 'c' => 'uppercase_test' ) );

		$this->assertTrue( $pass->passes() );
		$this->assertTrue( $fail->fails() );
	}

	// ---------------------------------------------------------------
	// ValidationException
	// ---------------------------------------------------------------

	public function testThrowIfFailsThrowsValidationException(): void {
		$this->expectException( ValidationException::class );

		Validator::make(
			array( 'name' => '' ),
			array( 'name' => 'required' )
		)->throw_if_fails();
	}

	public function testThrowIfFailsDoesNotThrowOnPass(): void {
		$result = Validator::make(
			array( 'name' => 'Alice' ),
			array( 'name' => 'required' )
		)->throw_if_fails();

		$this->assertInstanceOf( ValidationResult::class, $result );
	}

	public function testValidationExceptionExposesErrors(): void {
		try {
			Validator::make(
				array( 'email' => 'bad' ),
				array( 'email' => 'email' )
			)->throw_if_fails();

			$this->fail( 'Expected ValidationException' );
		} catch ( ValidationException $e ) {
			$this->assertArrayHasKey( 'email', $e->errors() );
			$this->assertNotNull( $e->first( 'email' ) );
		}
	}

	// ---------------------------------------------------------------
	// Request delegates to Validator
	// ---------------------------------------------------------------

	public function testRequestDelegatesToValidator(): void {
		WP_Mock::userFunction( 'wp_unslash' )->andReturnArg( 0 );

		$request = new \WPFlint\Http\Request(
			array( 'email' => 'notanemail', 'age' => 25 )
		);

		$stub = new class( array( 'email' => 'notanemail', 'age' => 25 ) ) extends \WPFlint\Http\Request {
			public function rules(): array {
				return array( 'email' => 'email', 'age' => 'integer|min:18' );
			}
		};

		$result = $stub->validate();

		$this->assertFalse( $result );
		$this->assertArrayHasKey( 'email', $stub->errors() );
		$this->assertArrayNotHasKey( 'age', $stub->errors() );
	}
}
