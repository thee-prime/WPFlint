<?php
/**
 * No-op logger — discards all entries.
 *
 * @package WPFlint\Logging
 */

declare(strict_types=1);

namespace WPFlint\Logging;

/**
 * Silently discards all log entries.
 *
 * Useful as a safe default / in tests where you do not want to assert logging.
 */
class NullLogger implements LoggerInterface {

	/**
	 * System is unusable.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function emergency( string $message, array $context = array() ): void {}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function alert( string $message, array $context = array() ): void {}

	/**
	 * Critical conditions.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function critical( string $message, array $context = array() ): void {}

	/**
	 * Runtime errors.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {}

	/**
	 * Normal but significant events.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function notice( string $message, array $context = array() ): void {}

	/**
	 * Interesting events.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {}

	/**
	 * Detailed debug information.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {}

	/**
	 * Logs with an arbitrary level — discarded.
	 *
	 * @param string               $level   One of the LogLevel constants.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array() ): void {}
}
