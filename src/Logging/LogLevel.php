<?php
/**
 * PSR-3 log level constants.
 *
 * @package WPFlint\Logging
 */

declare(strict_types=1);

namespace WPFlint\Logging;

/**
 * Log level constants matching PSR-3.
 */
class LogLevel {

	const EMERGENCY = 'emergency';
	const ALERT     = 'alert';
	const CRITICAL  = 'critical';
	const ERROR     = 'error';
	const WARNING   = 'warning';
	const NOTICE    = 'notice';
	const INFO      = 'info';
	const DEBUG     = 'debug';

	/**
	 * All levels ordered from most to least severe.
	 *
	 * @var array<int, string>
	 */
	public static array $levels = array(
		self::EMERGENCY,
		self::ALERT,
		self::CRITICAL,
		self::ERROR,
		self::WARNING,
		self::NOTICE,
		self::INFO,
		self::DEBUG,
	);

	/**
	 * Severity index — lower = more severe.
	 *
	 * @param string $level Log level constant.
	 * @return int
	 */
	public static function severity( string $level ): int {
		$index = array_search( $level, static::$levels, true );
		return ( false === $index ) ? 999 : (int) $index;
	}
}
