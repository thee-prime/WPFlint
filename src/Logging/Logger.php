<?php
/**
 * PSR-3-compatible logger that writes to WP_DEBUG_LOG.
 *
 * @package WPFlint\Logging
 */

declare(strict_types=1);

namespace WPFlint\Logging;

/**
 * Writes structured log entries to the WordPress debug log.
 *
 * Features:
 * - PSR-3 compatible interface (all 8 log levels).
 * - Placeholder interpolation: {key} replaced from context array.
 * - Minimum level filter — entries below the threshold are suppressed.
 * - Optional channel prefix (e.g. 'my-plugin') prepended to every line.
 * - Error objects in context['exception'] are auto-formatted.
 * - Writes via error_log() — respects WP_DEBUG_LOG path constant.
 *
 * Usage:
 *
 *     $logger = new Logger( 'my-plugin' );
 *     $logger->info( 'Order {id} placed.', [ 'id' => $order_id ] );
 *     $logger->error( 'Payment failed', [ 'exception' => $e ] );
 *
 *     // Only log warnings and above:
 *     $logger = new Logger( 'my-plugin', LogLevel::WARNING );
 */
class Logger implements LoggerInterface {

	/**
	 * Channel prefix shown in every log entry.
	 *
	 * @var string
	 */
	protected string $channel;

	/**
	 * Minimum log level to write (levels below this are dropped).
	 *
	 * @var string
	 */
	protected string $min_level;

	/**
	 * Create a new Logger.
	 *
	 * @param string $channel   Channel name prepended to each entry.
	 * @param string $min_level Minimum PSR-3 level to write. Default: debug (all levels).
	 */
	public function __construct( string $channel = 'wpflint', string $min_level = LogLevel::DEBUG ) {
		$this->channel   = $channel;
		$this->min_level = $min_level;
	}

	// ---------------------------------------------------------------
	// PSR-3 level methods
	// ---------------------------------------------------------------

	/**
	 * System is unusable.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function emergency( string $message, array $context = array() ): void {
		$this->log( LogLevel::EMERGENCY, $message, $context );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function alert( string $message, array $context = array() ): void {
		$this->log( LogLevel::ALERT, $message, $context );
	}

	/**
	 * Critical conditions.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function critical( string $message, array $context = array() ): void {
		$this->log( LogLevel::CRITICAL, $message, $context );
	}

	/**
	 * Runtime errors.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( LogLevel::ERROR, $message, $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( LogLevel::WARNING, $message, $context );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function notice( string $message, array $context = array() ): void {
		$this->log( LogLevel::NOTICE, $message, $context );
	}

	/**
	 * Interesting events.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( LogLevel::INFO, $message, $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( LogLevel::DEBUG, $message, $context );
	}

	// ---------------------------------------------------------------
	// Core log method
	// ---------------------------------------------------------------

	/**
	 * Write a log entry at the given level.
	 *
	 * Entry format (single line):
	 *   [YYYY-MM-DD HH:MM:SS] channel.LEVEL: Interpolated message {context_json?}
	 *
	 * @param string               $level   One of the LogLevel constants.
	 * @param string               $message Log message (may contain {placeholders}).
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! $this->should_log( $level ) ) {
			return;
		}

		$interpolated = $this->interpolate( $message, $context );
		$extra        = $this->format_context( $context );
		$entry        = $this->format_entry( $level, $interpolated, $extra );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug logging.
		error_log( $entry );
	}

	// ---------------------------------------------------------------
	// Configuration
	// ---------------------------------------------------------------

	/**
	 * Return a new Logger with the given minimum level.
	 *
	 * @param string $level Minimum PSR-3 level to write.
	 * @return static
	 */
	public function with_min_level( string $level ): self {
		$clone            = clone $this;
		$clone->min_level = $level;
		return $clone;
	}

	/**
	 * Return a new Logger for a different channel.
	 *
	 * @param string $channel Channel name.
	 * @return static
	 */
	public function channel( string $channel ): self {
		$clone          = clone $this;
		$clone->channel = $channel;
		return $clone;
	}

	/**
	 * Get the current channel name.
	 *
	 * @return string
	 */
	public function get_channel(): string {
		return $this->channel;
	}

	/**
	 * Get the current minimum level.
	 *
	 * @return string
	 */
	public function get_min_level(): string {
		return $this->min_level;
	}

	// ---------------------------------------------------------------
	// Internals
	// ---------------------------------------------------------------

	/**
	 * Whether the given level meets the minimum threshold.
	 *
	 * @param string $level Level to test.
	 * @return bool
	 */
	protected function should_log( string $level ): bool {
		return LogLevel::severity( $level ) <= LogLevel::severity( $this->min_level );
	}

	/**
	 * Interpolate {placeholder} tokens in the message from context values.
	 *
	 * PSR-3 §1.2: placeholders are delimited by braces: {key}.
	 * Keys and values must be string-castable.
	 *
	 * @param string               $message Raw message.
	 * @param array<string, mixed> $context Context values.
	 * @return string
	 */
	protected function interpolate( string $message, array $context ): string {
		if ( false === strpos( $message, '{' ) ) {
			return $message;
		}

		$replacements = array();
		foreach ( $context as $key => $value ) {
			if ( ! is_array( $value ) && ( ! is_object( $value ) || method_exists( $value, '__toString' ) ) ) {
				$replacements[ '{' . $key . '}' ] = (string) $value;
			}
		}

		return strtr( $message, $replacements );
	}

	/**
	 * Build the extra context string appended after the message.
	 *
	 * - `exception` key: formats as "ExceptionClass: message in file:line".
	 * - Other keys: JSON-encoded, stripped of `exception`.
	 *
	 * @param array<string, mixed> $context Context values.
	 * @return string Empty string or a formatted context tail.
	 */
	protected function format_context( array $context ): string {
		$parts = array();

		if ( isset( $context['exception'] ) && $context['exception'] instanceof \Throwable ) {
			$e       = $context['exception'];
			$parts[] = sprintf(
				'%s: %s in %s:%d',
				get_class( $e ),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			);
		}

		$extra = array_diff_key( $context, array( 'exception' => true ) );
		if ( ! empty( $extra ) ) {
			$encoded = wp_json_encode( $extra );
			if ( false !== $encoded ) {
				$parts[] = $encoded;
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * Assemble the final log line.
	 *
	 * @param string $level        Log level.
	 * @param string $message      Interpolated message.
	 * @param string $context_tail Formatted context tail (may be empty).
	 * @return string
	 */
	protected function format_entry( string $level, string $message, string $context_tail ): string {
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$prefix    = sprintf( '[%s] %s.%s: ', $timestamp, $this->channel, strtoupper( $level ) );
		$line      = $prefix . $message;

		if ( '' !== $context_tail ) {
			$line .= ' ' . $context_tail;
		}

		return $line;
	}
}
