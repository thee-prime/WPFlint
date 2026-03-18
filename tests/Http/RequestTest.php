<?php

declare(strict_types=1);

namespace WPFlint\Tests\Http;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Http\Request;

/**
 * @covers \WPFlint\Http\Request
 */
class RequestTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Input accessors
	// ---------------------------------------------------------------

	public function testInputReturnsValue(): void {
		$request = new Request( array( 'name' => 'John' ) );
		$this->assertSame( 'John', $request->input( 'name' ) );
	}

	public function testInputReturnsDefault(): void {
		$request = new Request( array() );
		$this->assertSame( 'default', $request->input( 'missing', 'default' ) );
	}

	public function testInputDotNotation(): void {
		$request = new Request( array( 'user' => array( 'name' => 'John' ) ) );
		$this->assertSame( 'John', $request->input( 'user.name' ) );
	}

	public function testAllReturnsData(): void {
		$data    = array( 'a' => 1, 'b' => 2 );
		$request = new Request( $data );
		$this->assertSame( $data, $request->all() );
	}

	public function testOnlyReturnsSubset(): void {
		$request = new Request( array( 'a' => 1, 'b' => 2, 'c' => 3 ) );
		$this->assertSame( array( 'a' => 1, 'c' => 3 ), $request->only( array( 'a', 'c' ) ) );
	}

	public function testExceptExcludesKeys(): void {
		$request = new Request( array( 'a' => 1, 'b' => 2, 'c' => 3 ) );
		$this->assertSame( array( 'a' => 1, 'c' => 3 ), $request->except( array( 'b' ) ) );
	}

	public function testHasReturnsTrueForExistingKey(): void {
		$request = new Request( array( 'name' => 'John' ) );
		$this->assertTrue( $request->has( 'name' ) );
	}

	public function testHasReturnsFalseForMissingKey(): void {
		$request = new Request( array() );
		$this->assertFalse( $request->has( 'missing' ) );
	}

	public function testFileReturnsUploadedFile(): void {
		$files   = array( 'avatar' => array( 'name' => 'photo.jpg' ) );
		$request = new Request( array(), $files );
		$this->assertSame( array( 'name' => 'photo.jpg' ), $request->file( 'avatar' ) );
	}

	public function testFileReturnsNullForMissing(): void {
		$request = new Request( array() );
		$this->assertNull( $request->file( 'missing' ) );
	}

	// ---------------------------------------------------------------
	// Validation — required
	// ---------------------------------------------------------------

	public function testRequiredPassesWhenPresent(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestRequiredRequest( array( 'name' => 'John' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testRequiredFailsWhenNull(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestRequiredRequest( array() );
		$this->assertFalse( $request->validate() );
		$this->assertArrayHasKey( 'name', $request->errors() );
	}

	public function testRequiredFailsWhenEmpty(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestRequiredRequest( array( 'name' => '' ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — string
	// ---------------------------------------------------------------

	public function testStringRulePasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestStringRequest( array( 'name' => 'John' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testStringRuleFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestStringRequest( array( 'name' => 123 ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — integer
	// ---------------------------------------------------------------

	public function testIntegerRulePasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestIntegerRequest( array( 'qty' => '5' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testIntegerRuleFailsWithFloat(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestIntegerRequest( array( 'qty' => '5.5' ) );
		$this->assertFalse( $request->validate() );
	}

	public function testIntegerRuleFailsWithString(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestIntegerRequest( array( 'qty' => 'abc' ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — numeric
	// ---------------------------------------------------------------

	public function testNumericRulePasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestNumericRequest( array( 'total' => '99.50' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testNumericRuleFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestNumericRequest( array( 'total' => 'abc' ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — email
	// ---------------------------------------------------------------

	public function testEmailRulePasses(): void {
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'is_email', array(
			'args'   => array( 'test@example.com' ),
			'return' => true,
		) );
		$request = new TestEmailRequest( array( 'email' => 'test@example.com' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testEmailRuleFails(): void {
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'is_email', array(
			'args'   => array( 'not-email' ),
			'return' => false,
		) );
		$request = new TestEmailRequest( array( 'email' => 'not-email' ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — url
	// ---------------------------------------------------------------

	public function testUrlRulePasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestUrlRequest( array( 'website' => 'https://example.com' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testUrlRuleFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestUrlRequest( array( 'website' => 'not-a-url' ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — in
	// ---------------------------------------------------------------

	public function testInRulePasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestInRequest( array( 'status' => 'pending' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testInRuleFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestInRequest( array( 'status' => 'invalid' ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — min / max (numeric)
	// ---------------------------------------------------------------

	public function testMinNumericPasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMinNumericRequest( array( 'total' => '10' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testMinNumericFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMinNumericRequest( array( 'total' => '-1' ) );
		$this->assertFalse( $request->validate() );
	}

	public function testMaxNumericPasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMaxNumericRequest( array( 'total' => '50' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testMaxNumericFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMaxNumericRequest( array( 'total' => '200' ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — min / max (string length)
	// ---------------------------------------------------------------

	public function testMinStringPasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMinStringRequest( array( 'name' => 'John' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testMinStringFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMinStringRequest( array( 'name' => 'Jo' ) );
		$this->assertFalse( $request->validate() );
	}

	public function testMaxStringPasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMaxStringRequest( array( 'name' => 'John' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testMaxStringFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMaxStringRequest( array( 'name' => 'Johnathaniel' ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — min / max (array count)
	// ---------------------------------------------------------------

	public function testMinArrayPasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMinArrayRequest( array( 'items' => array( 'a', 'b' ) ) );
		$this->assertTrue( $request->validate() );
	}

	public function testMinArrayFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMinArrayRequest( array( 'items' => array() ) );
		$this->assertFalse( $request->validate() );
	}

	public function testMaxArrayPasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMaxArrayRequest( array( 'items' => array( 'a' ) ) );
		$this->assertTrue( $request->validate() );
	}

	public function testMaxArrayFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestMaxArrayRequest( array( 'items' => array( 'a', 'b', 'c', 'd' ) ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — array
	// ---------------------------------------------------------------

	public function testArrayRulePasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestArrayRuleRequest( array( 'tags' => array( 'php', 'wp' ) ) );
		$this->assertTrue( $request->validate() );
	}

	public function testArrayRuleFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestArrayRuleRequest( array( 'tags' => 'not-array' ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — boolean
	// ---------------------------------------------------------------

	public function testBooleanRulePasses(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestBooleanRequest( array( 'active' => '1' ) );
		$this->assertTrue( $request->validate() );
	}

	public function testBooleanRuleFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestBooleanRequest( array( 'active' => 'yes' ) );
		$this->assertFalse( $request->validate() );
	}

	// ---------------------------------------------------------------
	// Validation — nullable
	// ---------------------------------------------------------------

	public function testNullableAllowsNull(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestNullableRequest( array() );
		$this->assertTrue( $request->validate() );
		$this->assertNull( $request->validated()['note'] );
	}

	public function testNullableValidatesWhenPresent(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestNullableRequest( array( 'note' => 'hello' ) );
		$this->assertTrue( $request->validate() );
		$this->assertSame( 'hello', $request->validated()['note'] );
	}

	// ---------------------------------------------------------------
	// Validation — wildcard (dot-star notation)
	// ---------------------------------------------------------------

	public function testWildcardValidation(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestWildcardRequest( array(
			'items' => array(
				array( 'product_id' => '1', 'qty' => '2' ),
				array( 'product_id' => '3', 'qty' => '1' ),
			),
		) );
		$this->assertTrue( $request->validate() );
	}

	public function testWildcardValidationFails(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestWildcardRequest( array(
			'items' => array(
				array( 'product_id' => '1', 'qty' => '2' ),
				array( 'product_id' => '', 'qty' => '1' ),
			),
		) );
		$this->assertFalse( $request->validate() );
		$this->assertArrayHasKey( 'items.1.product_id', $request->errors() );
	}

	// ---------------------------------------------------------------
	// Validation — combined rules
	// ---------------------------------------------------------------

	public function testCombinedRulesPass(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestCombinedRequest( array(
			'status' => 'pending',
			'total'  => '50',
		) );
		$this->assertTrue( $request->validate() );
	}

	public function testCombinedRulesFirstErrorStops(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestCombinedRequest( array(
			'status' => '',
			'total'  => '50',
		) );
		$this->assertFalse( $request->validate() );
		$this->assertArrayHasKey( 'status', $request->errors() );
	}

	// ---------------------------------------------------------------
	// Sanitization
	// ---------------------------------------------------------------

	public function testSanitizationApplied(): void {
		WP_Mock::passthruFunction( '__' );
		$request = new TestSanitizedRequest( array( 'name' => '  John  ' ) );
		$this->assertTrue( $request->validate() );
		$this->assertSame( 'John', $request->validated()['name'] );
	}

	// ---------------------------------------------------------------
	// Authorization
	// ---------------------------------------------------------------

	public function testAuthorizeDefaultsToTrue(): void {
		$request = new Request();
		$this->assertTrue( $request->authorize() );
	}

	public function testAuthorizeCanBeFalse(): void {
		$request = new TestUnauthorizedRequest();
		$this->assertFalse( $request->authorize() );
	}

	// ---------------------------------------------------------------
	// No rules = pass-through
	// ---------------------------------------------------------------

	public function testValidateWithNoRulesPassesAll(): void {
		$request = new Request( array( 'a' => 1, 'b' => 2 ) );
		$this->assertTrue( $request->validate() );
		$this->assertSame( array( 'a' => 1, 'b' => 2 ), $request->validated() );
	}
}

// ---------------------------------------------------------------
// Test request stubs
// ---------------------------------------------------------------

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

class TestRequiredRequest extends Request {
	public function rules(): array {
		return array( 'name' => 'required' );
	}
}

class TestStringRequest extends Request {
	public function rules(): array {
		return array( 'name' => 'string' );
	}
}

class TestIntegerRequest extends Request {
	public function rules(): array {
		return array( 'qty' => 'integer' );
	}
}

class TestNumericRequest extends Request {
	public function rules(): array {
		return array( 'total' => 'numeric' );
	}
}

class TestEmailRequest extends Request {
	public function rules(): array {
		return array( 'email' => 'email' );
	}
}

class TestUrlRequest extends Request {
	public function rules(): array {
		return array( 'website' => 'url' );
	}
}

class TestInRequest extends Request {
	public function rules(): array {
		return array( 'status' => 'in:pending,paid,cancelled' );
	}
}

class TestMinNumericRequest extends Request {
	public function rules(): array {
		return array( 'total' => 'required|numeric|min:0' );
	}
}

class TestMaxNumericRequest extends Request {
	public function rules(): array {
		return array( 'total' => 'required|numeric|max:100' );
	}
}

class TestMinStringRequest extends Request {
	public function rules(): array {
		return array( 'name' => 'required|string|min:3' );
	}
}

class TestMaxStringRequest extends Request {
	public function rules(): array {
		return array( 'name' => 'required|string|max:10' );
	}
}

class TestMinArrayRequest extends Request {
	public function rules(): array {
		return array( 'items' => 'required|array|min:1' );
	}
}

class TestMaxArrayRequest extends Request {
	public function rules(): array {
		return array( 'items' => 'required|array|max:3' );
	}
}

class TestArrayRuleRequest extends Request {
	public function rules(): array {
		return array( 'tags' => 'array' );
	}
}

class TestBooleanRequest extends Request {
	public function rules(): array {
		return array( 'active' => 'boolean' );
	}
}

class TestNullableRequest extends Request {
	public function rules(): array {
		return array( 'note' => 'nullable|string' );
	}
}

class TestWildcardRequest extends Request {
	public function rules(): array {
		return array(
			'items'              => 'required|array|min:1',
			'items.*.product_id' => 'required|integer',
			'items.*.qty'        => 'required|integer|min:1',
		);
	}
}

class TestCombinedRequest extends Request {
	public function rules(): array {
		return array(
			'status' => 'required|in:pending,paid,cancelled',
			'total'  => 'required|numeric|min:0',
		);
	}
}

class TestSanitizedRequest extends Request {
	public function rules(): array {
		return array( 'name' => 'required|string' );
	}
	public function sanitize(): array {
		return array( 'name' => 'trim' );
	}
}

class TestUnauthorizedRequest extends Request {
	public function authorize(): bool {
		return false;
	}
}

// phpcs:enable
