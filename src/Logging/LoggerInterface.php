<?php
/**
 * PSR-3–compatible logger contract.
 *
 * @package WPFlint\Logging
 */

declare(strict_types=1);

namespace WPFlint\Logging;

/**
 * Describes a logger instance (PSR-3 compatible).
 *
 * Implementations MUST accept any string-castable message and an optional
 * context array. Placeholder interpolation follows PSR-3: {key} is replaced
 * by context['key'].
 */
interface LoggerInterface {

	/**
	 * System is unusable.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function emergency( string $message, array $context = array() ): void;

	/**
	 * Action must be taken immediately.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function alert( string $message, array $context = array() ): void;

	/**
	 * Critical conditions.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function critical( string $message, array $context = array() ): void;

	/**
	 * Runtime errors that do not require immediate action.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void;

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void;

	/**
	 * Normal but significant events.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function notice( string $message, array $context = array() ): void;

	/**
	 * Interesting events.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void;

	/**
	 * Detailed debug information.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void;

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param string               $level   One of the LogLevel constants.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array() ): void;
}
