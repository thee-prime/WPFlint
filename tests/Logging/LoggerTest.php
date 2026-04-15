<?php

declare(strict_types=1);

namespace WPFlint\Tests\Logging;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Logging\Logger;
use WPFlint\Logging\LoggerInterface;
use WPFlint\Logging\LoggingServiceProvider;
use WPFlint\Logging\LogLevel;
use WPFlint\Logging\NullLogger;
use WPFlint\Application;

/**
 * @covers \WPFlint\Logging\Logger
 * @covers \WPFlint\Logging\LogLevel
 * @covers \WPFlint\Logging\NullLogger
 * @covers \WPFlint\Logging\LoggingServiceProvider
 */
class LoggerTest extends TestCase {

	/** @var array<int, string> Captured error_log calls. */
	private array $logged = array();

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		$this->logged = array();

		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'wp_json_encode' )->andReturnUsing(
			function ( $data ) {
				return json_encode( $data );
			}
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		Application::clear_instance();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// LogLevel
	// ---------------------------------------------------------------

	public function test_log_level_severity_ordering(): void {
		$this->assertLessThan(
			LogLevel::severity( LogLevel::ALERT ),
			LogLevel::severity( LogLevel::EMERGENCY )
		);
		$this->assertLessThan(
			LogLevel::severity( LogLevel::DEBUG ),
			LogLevel::severity( LogLevel::INFO )
		);
	}

	public function test_log_level_severity_unknown_returns_999(): void {
		$this->assertSame( 999, LogLevel::severity( 'bogus' ) );
	}

	public function test_log_level_constants_match_psr3(): void {
		$this->assertSame( 'emergency', LogLevel::EMERGENCY );
		$this->assertSame( 'alert', LogLevel::ALERT );
		$this->assertSame( 'critical', LogLevel::CRITICAL );
		$this->assertSame( 'error', LogLevel::ERROR );
		$this->assertSame( 'warning', LogLevel::WARNING );
		$this->assertSame( 'notice', LogLevel::NOTICE );
		$this->assertSame( 'info', LogLevel::INFO );
		$this->assertSame( 'debug', LogLevel::DEBUG );
	}

	// ---------------------------------------------------------------
	// Logger — construction / configuration
	// ---------------------------------------------------------------

	public function test_get_channel_returns_channel(): void {
		$logger = new Logger( 'my-plugin' );
		$this->assertSame( 'my-plugin', $logger->get_channel() );
	}

	public function test_get_min_level_defaults_to_debug(): void {
		$logger = new Logger();
		$this->assertSame( LogLevel::DEBUG, $logger->get_min_level() );
	}

	public function test_with_min_level_returns_new_instance(): void {
		$logger  = new Logger( 'app' );
		$limited = $logger->with_min_level( LogLevel::WARNING );

		$this->assertNotSame( $logger, $limited );
		$this->assertSame( LogLevel::DEBUG, $logger->get_min_level() );
		$this->assertSame( LogLevel::WARNING, $limited->get_min_level() );
	}

	public function test_channel_returns_new_instance(): void {
		$logger  = new Logger( 'app' );
		$other   = $logger->channel( 'payments' );

		$this->assertNotSame( $logger, $other );
		$this->assertSame( 'app', $logger->get_channel() );
		$this->assertSame( 'payments', $other->get_channel() );
	}

	public function test_implements_logger_interface(): void {
		$this->assertInstanceOf( LoggerInterface::class, new Logger() );
	}

	// ---------------------------------------------------------------
	// Logger — writes entries via error_log
	// ---------------------------------------------------------------

	public function test_log_writes_entry(): void {
		$logger  = $this->spy_logger();
		$logger->log( LogLevel::INFO, 'Hello world' );

		$this->assertCount( 1, $this->logged );
		$this->assertStringContainsString( 'Hello world', $this->logged[0] );
	}

	public function test_log_entry_contains_level(): void {
		$logger = $this->spy_logger();
		$logger->error( 'Something broke' );

		$this->assertStringContainsString( 'ERROR', $this->logged[0] );
	}

	public function test_log_entry_contains_channel(): void {
		$logger = $this->spy_logger( 'payments' );
		$logger->info( 'Payment received' );

		$this->assertStringContainsString( 'payments', $this->logged[0] );
	}

	public function test_log_entry_contains_timestamp(): void {
		$logger = $this->spy_logger();
		$logger->debug( 'tick' );

		$this->assertMatchesRegularExpression( '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $this->logged[0] );
	}

	public function test_entry_format(): void {
		$logger = $this->spy_logger( 'app' );
		$logger->info( 'Hello' );

		$this->assertMatchesRegularExpression( '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] app\.INFO: Hello/', $this->logged[0] );
	}

	// ---------------------------------------------------------------
	// Logger — convenience level methods
	// ---------------------------------------------------------------

	/** @dataProvider level_provider */
	public function test_level_method_writes_correct_level( string $method, string $expected_label ): void {
		$logger = $this->spy_logger();
		$logger->$method( 'msg' );

		$this->assertCount( 1, $this->logged );
		$this->assertStringContainsString( $expected_label, $this->logged[0] );
	}

	/** @return array<string, array{string, string}> */
	public function level_provider(): array {
		return array(
			'emergency' => array( 'emergency', 'EMERGENCY' ),
			'alert'     => array( 'alert', 'ALERT' ),
			'critical'  => array( 'critical', 'CRITICAL' ),
			'error'     => array( 'error', 'ERROR' ),
			'warning'   => array( 'warning', 'WARNING' ),
			'notice'    => array( 'notice', 'NOTICE' ),
			'info'      => array( 'info', 'INFO' ),
			'debug'     => array( 'debug', 'DEBUG' ),
		);
	}

	// ---------------------------------------------------------------
	// Logger — placeholder interpolation
	// ---------------------------------------------------------------

	public function test_interpolates_placeholders(): void {
		$logger = $this->spy_logger();
		$logger->info( 'Order {id} placed by {user}.', array( 'id' => 42, 'user' => 'alice' ) );

		$this->assertStringContainsString( 'Order 42 placed by alice.', $this->logged[0] );
	}

	public function test_skips_interpolation_when_no_braces(): void {
		$logger = $this->spy_logger();
		$logger->debug( 'No placeholders here' );

		$this->assertStringContainsString( 'No placeholders here', $this->logged[0] );
	}

	public function test_array_context_values_not_interpolated_as_placeholder(): void {
		$logger = $this->spy_logger();
		// Array value — cannot be cast to string, so placeholder is left as-is.
		$logger->info( 'Items: {list}', array( 'list' => array( 'a', 'b' ) ) );

		$this->assertStringContainsString( 'Items: {list}', $this->logged[0] );
	}

	// ---------------------------------------------------------------
	// Logger — context formatting
	// ---------------------------------------------------------------

	public function test_exception_in_context_is_formatted(): void {
		$logger = $this->spy_logger();
		$e      = new \RuntimeException( 'disk full' );
		$logger->error( 'Write failed', array( 'exception' => $e ) );

		$this->assertStringContainsString( 'RuntimeException', $this->logged[0] );
		$this->assertStringContainsString( 'disk full', $this->logged[0] );
	}

	public function test_extra_context_keys_json_encoded(): void {
		$logger = $this->spy_logger();
		$logger->info( 'Payment', array( 'amount' => 99, 'currency' => 'USD' ) );

		$this->assertStringContainsString( '"amount"', $this->logged[0] );
		$this->assertStringContainsString( '"currency"', $this->logged[0] );
	}

	public function test_exception_key_excluded_from_extra_json(): void {
		$logger = $this->spy_logger();
		$e      = new \RuntimeException( 'oops' );
		$logger->error( 'Failed', array( 'exception' => $e, 'code' => 500 ) );

		// The exception class name appears (formatted), but not as a JSON key.
		$entry = $this->logged[0];
		$this->assertStringNotContainsString( '"exception"', $entry );
		$this->assertStringContainsString( '"code"', $entry );
	}

	// ---------------------------------------------------------------
	// Logger — minimum level filtering
	// ---------------------------------------------------------------

	public function test_entries_below_min_level_are_suppressed(): void {
		$logger = $this->spy_logger( 'app', LogLevel::WARNING );
		$logger->debug( 'suppressed' );
		$logger->info( 'also suppressed' );

		$this->assertCount( 0, $this->logged );
	}

	public function test_entries_at_or_above_min_level_are_written(): void {
		$logger = $this->spy_logger( 'app', LogLevel::WARNING );
		$logger->warning( 'at threshold' );
		$logger->error( 'above threshold' );

		$this->assertCount( 2, $this->logged );
	}

	public function test_with_min_level_filters_correctly(): void {
		$base    = $this->spy_logger();
		$limited = $base->with_min_level( LogLevel::ERROR );

		// Writes to shared $this->logged.
		$limited->warning( 'suppressed' );
		$limited->error( 'written' );

		// Only the 'written' entry should appear.
		$this->assertCount( 1, $this->logged );
		$this->assertStringContainsString( 'written', $this->logged[0] );
	}

	// ---------------------------------------------------------------
	// NullLogger
	// ---------------------------------------------------------------

	public function test_null_logger_discards_all_levels(): void {
		$null = new NullLogger();
		// None of these should throw or write anything.
		$null->emergency( 'x' );
		$null->alert( 'x' );
		$null->critical( 'x' );
		$null->error( 'x' );
		$null->warning( 'x' );
		$null->notice( 'x' );
		$null->info( 'x' );
		$null->debug( 'x' );
		$null->log( LogLevel::INFO, 'x' );

		$this->assertTrue( true ); // No exception = pass.
	}

	public function test_null_logger_implements_interface(): void {
		$this->assertInstanceOf( LoggerInterface::class, new NullLogger() );
	}

	// ---------------------------------------------------------------
	// LoggingServiceProvider
	// ---------------------------------------------------------------

	public function test_provider_binds_logger_singleton(): void {
		$app = Application::get_instance();
		$app->register( LoggingServiceProvider::class );
		$app->boot();

		$a = $app->make( 'logger' );
		$b = $app->make( 'logger' );

		$this->assertInstanceOf( Logger::class, $a );
		$this->assertSame( $a, $b );
	}

	public function test_provider_binds_logger_interface(): void {
		$app = Application::get_instance();
		$app->register( LoggingServiceProvider::class );
		$app->boot();

		$this->assertInstanceOf( LoggerInterface::class, $app->make( LoggerInterface::class ) );
	}

	public function test_provider_provides_returns_expected_abstracts(): void {
		$provider = new LoggingServiceProvider( Application::get_instance() );

		$this->assertContains( 'logger', $provider->provides() );
		$this->assertContains( LoggerInterface::class, $provider->provides() );
		$this->assertContains( Logger::class, $provider->provides() );
	}

	// ---------------------------------------------------------------
	// Helpers — wpflint_log / wpflint_dd
	// ---------------------------------------------------------------

	public function test_wpflint_log_global_exists(): void {
		$this->assertTrue( function_exists( 'wpflint_log' ) );
	}

	public function test_wpflint_dd_global_exists(): void {
		$this->assertTrue( function_exists( 'wpflint_dd' ) );
	}

	public function test_log_message_canonical_writes_to_logger(): void {
		$app = Application::get_instance();
		$app->register( LoggingServiceProvider::class );
		$app->boot();

		$spy_logger = $this->spy_logger();
		$app->singleton( 'logger', fn() => $spy_logger );
		$app->singleton( LoggerInterface::class, fn() => $spy_logger );

		\WPFlint\Support\log_message( 'test entry', array(), LogLevel::INFO );

		$this->assertCount( 1, $this->logged );
		$this->assertStringContainsString( 'test entry', $this->logged[0] );
	}

	public function test_logger_instance_returns_null_logger_when_app_not_booted(): void {
		Application::clear_instance();

		$logger = \WPFlint\Support\logger_instance();
		$this->assertInstanceOf( NullLogger::class, $logger );
	}

	// ---------------------------------------------------------------
	// Helper — spy logger that captures error_log output
	// ---------------------------------------------------------------

	/**
	 * Build a Logger subclass that captures entries instead of calling error_log.
	 *
	 * @param string $channel   Channel name.
	 * @param string $min_level Minimum level.
	 * @return Logger
	 */
	private function spy_logger( string $channel = 'test', string $min_level = LogLevel::DEBUG ): Logger {
		$logged = &$this->logged;

		return new class( $channel, $min_level, $logged ) extends Logger {

			/** @var array<int, string> */
			private array $store;

			/**
			 * @param string           $channel   Channel name.
			 * @param string           $min_level Min level.
			 * @param array<int,string> $store     Reference to captured entries array.
			 */
			public function __construct( string $channel, string $min_level, array &$store ) {
				parent::__construct( $channel, $min_level );
				$this->store = &$store;
			}

			public function log( string $level, string $message, array $context = array() ): void {
				if ( ! $this->should_log( $level ) ) {
					return;
				}
				$interpolated    = $this->interpolate( $message, $context );
				$extra           = $this->format_context( $context );
				$this->store[]   = $this->format_entry( $level, $interpolated, $extra );
			}
		};
	}
}
