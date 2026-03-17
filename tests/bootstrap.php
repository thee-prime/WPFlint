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

// Initialize WP_Mock.
WP_Mock::bootstrap();
