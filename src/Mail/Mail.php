<?php
/**
 * Fluent email builder wrapping wp_mail().
 *
 * @package WPFlint\Mail
 */

declare(strict_types=1);

namespace WPFlint\Mail;

use WPFlint\View\View;

/**
 * Builds and sends emails via wp_mail().
 *
 * Usage:
 *
 *     // Plain text
 *     Mail::to( 'user@example.com' )
 *         ->subject( 'Welcome!' )
 *         ->message( 'Thanks for signing up.' )
 *         ->send();
 *
 *     // HTML body
 *     Mail::to( [ 'alice@example.com', 'bob@example.com' ] )
 *         ->subject( 'Order Confirmed' )
 *         ->html( '<h1>Your order is confirmed.</h1>' )
 *         ->send();
 *
 *     // PHP template (requires View base path to be configured)
 *     Mail::to( $user->user_email )
 *         ->subject( 'Order Confirmed' )
 *         ->from( 'shop@example.com', 'My Shop' )
 *         ->template( 'emails.order-confirmed', [ 'order' => $order ] )
 *         ->send();
 */
class Mail {

	/**
	 * Recipient email addresses.
	 *
	 * @var array<int, string>
	 */
	protected array $to = array();

	/**
	 * Email subject line.
	 *
	 * @var string
	 */
	protected string $subject = '';

	/**
	 * Email body.
	 *
	 * @var string
	 */
	protected string $message = '';

	/**
	 * Additional headers (From, Cc, Bcc, Content-Type, etc.).
	 *
	 * @var array<int, string>
	 */
	protected array $headers = array();

	/**
	 * Absolute paths to files to attach.
	 *
	 * @var array<int, string>
	 */
	protected array $attachments = array();

	/**
	 * Create a Mail instance.
	 *
	 * @param array<int, string> $to Recipient email addresses.
	 */
	public function __construct( array $to ) {
		$this->to = $to;
	}

	/**
	 * Static factory — set the recipient(s).
	 *
	 * @param string|array<int, string> $email One or more recipient addresses.
	 * @return static
	 */
	public static function to( $email ): self {
		return new static( (array) $email );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Set the email subject.
	 *
	 * @param string $subject Subject line.
	 * @return $this
	 */
	public function subject( string $subject ): self {
		$this->subject = $subject;
		return $this;
	}

	/**
	 * Set the plain-text email body.
	 *
	 * @param string $message Message body.
	 * @return $this
	 */
	public function message( string $message ): self {
		$this->message = $message;
		return $this;
	}

	/**
	 * Set an HTML body and automatically add the HTML content-type header.
	 *
	 * @param string $html Raw HTML string.
	 * @return $this
	 */
	public function html( string $html ): self {
		$this->message   = $html;
		$this->headers[] = 'Content-Type: text/html; charset=UTF-8';
		return $this;
	}

	/**
	 * Render a PHP view template as the email body.
	 *
	 * Requires View::set_base_path() to have been called (or ViewServiceProvider registered).
	 * Automatically adds the HTML content-type header.
	 *
	 * @param string               $template Dot-notation template name, e.g. 'emails.order-confirmed'.
	 * @param array<string, mixed> $data     Data to pass to the template.
	 * @return $this
	 */
	public function template( string $template, array $data = array() ): self {
		$this->message   = View::make( $template )->with( $data )->render();
		$this->headers[] = 'Content-Type: text/html; charset=UTF-8';
		return $this;
	}

	/**
	 * Set the From header.
	 *
	 * @param string $email Sender email address.
	 * @param string $name  Optional sender display name.
	 * @return $this
	 */
	public function from( string $email, string $name = '' ): self {
		$from            = '' !== $name ? $name . ' <' . $email . '>' : $email;
		$this->headers[] = 'From: ' . $from;
		return $this;
	}

	/**
	 * Add one or more Cc recipients.
	 *
	 * @param string|array<int, string> $email Email address(es).
	 * @return $this
	 */
	public function cc( $email ): self {
		foreach ( (array) $email as $addr ) {
			$this->headers[] = 'Cc: ' . $addr;
		}

		return $this;
	}

	/**
	 * Add one or more Bcc recipients.
	 *
	 * @param string|array<int, string> $email Email address(es).
	 * @return $this
	 */
	public function bcc( $email ): self {
		foreach ( (array) $email as $addr ) {
			$this->headers[] = 'Bcc: ' . $addr;
		}

		return $this;
	}

	/**
	 * Append a raw header string.
	 *
	 * @param string $header Full header line, e.g. 'Reply-To: noreply@example.com'.
	 * @return $this
	 */
	public function header( string $header ): self {
		$this->headers[] = $header;
		return $this;
	}

	/**
	 * Attach a file to the email.
	 *
	 * @param string $path Absolute path to the file.
	 * @return $this
	 */
	public function attach( string $path ): self {
		$this->attachments[] = $path;
		return $this;
	}

	// ---------------------------------------------------------------
	// Sending
	// ---------------------------------------------------------------

	/**
	 * Send the email via wp_mail().
	 *
	 * @return bool True if the email was accepted for delivery, false on failure.
	 */
	public function send(): bool {
		return wp_mail(
			$this->to,
			$this->subject,
			$this->message,
			$this->headers,
			$this->attachments
		);
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the recipient list.
	 *
	 * @return array<int, string>
	 */
	public function get_to(): array {
		return $this->to;
	}

	/**
	 * Get the subject line.
	 *
	 * @return string
	 */
	public function get_subject(): string {
		return $this->subject;
	}

	/**
	 * Get the message body.
	 *
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Get the headers array.
	 *
	 * @return array<int, string>
	 */
	public function get_headers(): array {
		return $this->headers;
	}

	/**
	 * Get the attachment paths.
	 *
	 * @return array<int, string>
	 */
	public function get_attachments(): array {
		return $this->attachments;
	}
}
