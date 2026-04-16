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

if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (! defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
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

// Stub WP_Widget for Widget tests.
if (! class_exists('WP_Widget')) {
    // phpcs:ignore
    class WP_Widget {
        public $id_base = '';
        public $name    = '';

        public function __construct( $id_base = '', $name = '', $widget_options = array(), $control_options = array() ) {
            $this->id_base = $id_base;
            $this->name    = $name;
        }

        public function widget( $args, $instance ) {}
        public function form( $instance ) {}
        public function update( $new_instance, $old_instance ) { return $new_instance; }
        public function get_field_id( $field_name ) { return 'widget-' . $this->id_base . '-' . $field_name; }
        public function get_field_name( $field_name ) { return 'widget-' . $this->id_base . '[][' . $field_name . ']'; }
    }
}

// Stub WP_Post for MetaBox render callback tests.
if (! class_exists('WP_Post')) {
    // phpcs:ignore
    class WP_Post {
        public $ID        = 0;
        public $post_type = 'post';
        public function __construct( $id = 0, $post_type = 'post' ) {
            $this->ID        = $id;
            $this->post_type = $post_type;
        }
    }
}

// Initialize WP_Mock.
WP_Mock::bootstrap();
