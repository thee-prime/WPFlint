<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WP constants used throughout the framework.
if (! defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (! defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

// Stub WP_CLI for Console command tests.
if (! class_exists('WP_CLI')) {
    // phpcs:ignore
    class WP_CLI {
        /** @var array Captured output for test assertions. */
        public static array $captured = array();

        public static function line(string $message): void {
            self::$captured[] = array( 'line', $message );
        }

        public static function success(string $message): void {
            self::$captured[] = array( 'success', $message );
        }

        public static function error(string $message): void {
            self::$captured[] = array( 'error', $message );
        }

        public static function warning(string $message): void {
            self::$captured[] = array( 'warning', $message );
        }

        public static function confirm(string $message): void {
            self::$captured[] = array( 'confirm', $message );
        }

        public static function reset(): void {
            self::$captured = array();
        }
    }
}

// Initialize WP_Mock.
WP_Mock::bootstrap();
