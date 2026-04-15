<?php
/**
 * Tests for the Mail builder.
 *
 * @package WPFlint\Tests\Mail
 */

declare(strict_types=1);

namespace WPFlint\Tests\Mail;

use WPFlint\Mail\Mail;
use WPFlint\View\View;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\Mail\Mail
 */
class MailTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
		View::set_base_path( '' );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		View::set_base_path( '' );
	}

	// ---------------------------------------------------------------
	// Construction & factory
	// ---------------------------------------------------------------

	public function test_to_factory_returns_instance(): void {
		$mail = Mail::to( 'user@example.com' );
		$this->assertInstanceOf( Mail::class, $mail );
	}

	public function test_to_wraps_string_in_array(): void {
		$mail = Mail::to( 'user@example.com' );
		$this->assertSame( array( 'user@example.com' ), $mail->get_to() );
	}

	public function test_to_accepts_array(): void {
		$mail = Mail::to( array( 'a@example.com', 'b@example.com' ) );
		$this->assertSame( array( 'a@example.com', 'b@example.com' ), $mail->get_to() );
	}

	// ---------------------------------------------------------------
	// Getters — initial state
	// ---------------------------------------------------------------

	public function test_initial_state(): void {
		$mail = Mail::to( 'u@example.com' );

		$this->assertSame( '', $mail->get_subject() );
		$this->assertSame( '', $mail->get_message() );
		$this->assertEmpty( $mail->get_headers() );
		$this->assertEmpty( $mail->get_attachments() );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	public function test_subject_setter(): void {
		$mail = Mail::to( 'u@e.com' )->subject( 'Hello' );
		$this->assertSame( 'Hello', $mail->get_subject() );
	}

	public function test_message_setter(): void {
		$mail = Mail::to( 'u@e.com' )->message( 'Body text.' );
		$this->assertSame( 'Body text.', $mail->get_message() );
	}

	public function test_html_sets_message_and_content_type_header(): void {
		$mail = Mail::to( 'u@e.com' )->html( '<p>Hello</p>' );

		$this->assertSame( '<p>Hello</p>', $mail->get_message() );
		$this->assertContains( 'Content-Type: text/html; charset=UTF-8', $mail->get_headers() );
	}

	public function test_from_setter_without_name(): void {
		$mail = Mail::to( 'u@e.com' )->from( 'noreply@example.com' );
		$this->assertContains( 'From: noreply@example.com', $mail->get_headers() );
	}

	public function test_from_setter_with_name(): void {
		$mail = Mail::to( 'u@e.com' )->from( 'noreply@example.com', 'My Shop' );
		$this->assertContains( 'From: My Shop <noreply@example.com>', $mail->get_headers() );
	}

	public function test_cc_adds_header(): void {
		$mail = Mail::to( 'u@e.com' )->cc( 'cc@example.com' );
		$this->assertContains( 'Cc: cc@example.com', $mail->get_headers() );
	}

	public function test_cc_accepts_array(): void {
		$mail = Mail::to( 'u@e.com' )->cc( array( 'a@e.com', 'b@e.com' ) );

		$headers = $mail->get_headers();
		$this->assertContains( 'Cc: a@e.com', $headers );
		$this->assertContains( 'Cc: b@e.com', $headers );
	}

	public function test_bcc_adds_header(): void {
		$mail = Mail::to( 'u@e.com' )->bcc( 'bcc@example.com' );
		$this->assertContains( 'Bcc: bcc@example.com', $mail->get_headers() );
	}

	public function test_header_appends_raw_header(): void {
		$mail = Mail::to( 'u@e.com' )->header( 'Reply-To: reply@example.com' );
		$this->assertContains( 'Reply-To: reply@example.com', $mail->get_headers() );
	}

	public function test_attach_stores_path(): void {
		$mail = Mail::to( 'u@e.com' )->attach( '/path/to/file.pdf' );
		$this->assertContains( '/path/to/file.pdf', $mail->get_attachments() );
	}

	public function test_multiple_attachments_accumulate(): void {
		$mail = Mail::to( 'u@e.com' )
			->attach( '/a.pdf' )
			->attach( '/b.pdf' );
		$this->assertCount( 2, $mail->get_attachments() );
	}

	// ---------------------------------------------------------------
	// Chaining returns self
	// ---------------------------------------------------------------

	public function test_all_setters_return_self(): void {
		$mail = Mail::to( 'u@e.com' );
		$this->assertSame( $mail, $mail->subject( 'S' ) );
		$this->assertSame( $mail, $mail->message( 'M' ) );
		$this->assertSame( $mail, $mail->html( '<b>H</b>' ) );
		$this->assertSame( $mail, $mail->from( 'f@e.com' ) );
		$this->assertSame( $mail, $mail->cc( 'c@e.com' ) );
		$this->assertSame( $mail, $mail->bcc( 'b@e.com' ) );
		$this->assertSame( $mail, $mail->header( 'X-Custom: 1' ) );
		$this->assertSame( $mail, $mail->attach( '/f.pdf' ) );
	}

	// ---------------------------------------------------------------
	// send()
	// ---------------------------------------------------------------

	public function test_send_calls_wp_mail_with_correct_args(): void {
		$calls = array();

		WP_Mock::userFunction( 'wp_mail' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
				return true;
			} );

		$result = Mail::to( 'user@example.com' )
			->subject( 'Hello' )
			->message( 'Body' )
			->send();

		$this->assertTrue( $result );
		$this->assertCount( 1, $calls );
		$this->assertSame( array( 'user@example.com' ), $calls[0][0] );
		$this->assertSame( 'Hello', $calls[0][1] );
		$this->assertSame( 'Body', $calls[0][2] );
	}

	public function test_send_returns_false_on_failure(): void {
		WP_Mock::userFunction( 'wp_mail' )->andReturn( false );

		$result = Mail::to( 'u@e.com' )->send();

		$this->assertFalse( $result );
	}

	public function test_send_passes_headers_and_attachments(): void {
		$calls = array();

		WP_Mock::userFunction( 'wp_mail' )
			->andReturnUsing( static function () use ( &$calls ) {
				$calls[] = func_get_args();
				return true;
			} );

		Mail::to( 'u@e.com' )
			->from( 'noreply@e.com', 'Shop' )
			->cc( 'admin@e.com' )
			->attach( '/receipt.pdf' )
			->send();

		$headers     = $calls[0][3];
		$attachments = $calls[0][4];

		$this->assertContains( 'From: Shop <noreply@e.com>', $headers );
		$this->assertContains( 'Cc: admin@e.com', $headers );
		$this->assertContains( '/receipt.pdf', $attachments );
	}

	// ---------------------------------------------------------------
	// template()
	// ---------------------------------------------------------------

	public function test_template_renders_view_and_sets_html_content_type(): void {
		$tmp = sys_get_temp_dir() . '/wpflint_mail_' . uniqid();
		mkdir( $tmp );
		file_put_contents( $tmp . '/confirm.php', '<?php echo "Order: " . $order_id; ?>' );

		View::set_base_path( $tmp );

		$mail = Mail::to( 'u@e.com' )
			->template( 'confirm', array( 'order_id' => 42 ) );

		$this->assertSame( 'Order: 42', $mail->get_message() );
		$this->assertContains( 'Content-Type: text/html; charset=UTF-8', $mail->get_headers() );

		unlink( $tmp . '/confirm.php' );
		rmdir( $tmp );
	}
}
