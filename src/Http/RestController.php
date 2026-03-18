<?php
/**
 * Base REST controller.
 *
 * @package WPFlint\Http
 */

declare(strict_types=1);

namespace WPFlint\Http;

/**
 * Base class for WP REST API controllers.
 *
 * Provides respond() helper for consistent JSON output.
 */
abstract class RestController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected string $namespace = '';

	/**
	 * REST base route.
	 *
	 * @var string
	 */
	protected string $rest_base = '';

	/**
	 * Wrap data into a WP_REST_Response.
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status code.
	 * @return \WP_REST_Response
	 */
	protected function respond( $data = null, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response( $data, $status );
	}

	/**
	 * Return an error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_Error
	 */
	protected function error( string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( 'rest_error', $message, array( 'status' => $status ) );
	}

	/**
	 * Get the full REST namespace.
	 *
	 * @return string
	 */
	public function get_namespace(): string {
		return $this->namespace;
	}

	/**
	 * Get the REST base.
	 *
	 * @return string
	 */
	public function get_rest_base(): string {
		return $this->rest_base;
	}
}
