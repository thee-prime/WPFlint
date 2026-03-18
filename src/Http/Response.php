<?php
/**
 * HTTP Response helpers for AJAX and REST.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

/**
 * Unified response class for AJAX and REST endpoints.
 */
class Response {

	/**
	 * Response data.
	 *
	 * @var mixed
	 */
	protected $data;

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	protected int $status;

	/**
	 * Additional headers.
	 *
	 * @var array<string, string>
	 */
	protected array $headers = array();

	/**
	 * Constructor.
	 *
	 * @param mixed $data    Response data.
	 * @param int   $status  HTTP status code.
	 * @param array $headers Additional headers.
	 */
	public function __construct( $data = null, int $status = 200, array $headers = array() ) {
		$this->data    = $data;
		$this->status  = $status;
		$this->headers = $headers;
	}

	/**
	 * Create a JSON success response.
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status code.
	 * @return static
	 */
	public static function json( $data = null, int $status = 200 ): self {
		return new static( $data, $status );
	}

	/**
	 * Create an error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return static
	 */
	public static function error( string $message, int $status = 400 ): self {
		return new static( array( 'message' => $message ), $status );
	}

	/**
	 * Create a 204 No Content response.
	 *
	 * @return static
	 */
	public static function no_content(): self {
		return new static( null, 204 );
	}

	/**
	 * Get the response data.
	 *
	 * @return mixed
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Get the status code.
	 *
	 * @return int
	 */
	public function get_status(): int {
		return $this->status;
	}

	/**
	 * Get additional headers.
	 *
	 * @return array<string, string>
	 */
	public function get_headers(): array {
		return $this->headers;
	}

	/**
	 * Set a header.
	 *
	 * @param string $name  Header name.
	 * @param string $value Header value.
	 * @return static
	 */
	public function with_header( string $name, string $value ): self {
		$this->headers[ $name ] = $value;
		return $this;
	}

	/**
	 * Send the response as AJAX (wp_send_json_success / wp_send_json_error).
	 */
	public function send_ajax(): void {
		foreach ( $this->headers as $name => $value ) {
			header( $name . ': ' . $value );
		}

		if ( $this->status >= 200 && $this->status < 300 ) {
			wp_send_json_success( $this->data, $this->status );
		} else {
			wp_send_json_error( $this->data, $this->status );
		}
	}

	/**
	 * Convert to a WP_REST_Response.
	 *
	 * @return \WP_REST_Response
	 */
	public function to_rest(): \WP_REST_Response {
		$response = new \WP_REST_Response( $this->data, $this->status );

		foreach ( $this->headers as $name => $value ) {
			$response->header( $name, $value );
		}

		return $response;
	}

	/**
	 * Check if the response is successful (2xx).
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return $this->status >= 200 && $this->status < 300;
	}

	/**
	 * Check if the response is an error (4xx or 5xx).
	 *
	 * @return bool
	 */
	public function is_error(): bool {
		return $this->status >= 400;
	}
}
