<?php
/**
 * Tests for the Lifecycle module.
 *
 * @package WPFlint\Tests\Lifecycle
 */

declare(strict_types=1);

namespace WPFlint\Tests\Lifecycle;

use WPFlint\Lifecycle\Lifecycle;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \WPFlint\Lifecycle\Lifecycle
 */
class LifecycleTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	// ---------------------------------------------------------------
	// Construction & factories
	// ---------------------------------------------------------------

	public function test_for_factory_returns_instance(): void {
		$lc = Lifecycle::for( '/plugin/my-plugin.php' );

		$this->assertInstanceOf( Lifecycle::class, $lc );
		$this->assertSame( '/plugin/my-plugin.php', $lc->get_plugin_file() );
	}

	public function test_initial_state_is_empty(): void {
		$lc = Lifecycle::for( '/plugin/my-plugin.php' );

		$this->assertEmpty( $lc->get_activate_callbacks() );
		$this->assertEmpty( $lc->get_deactivate_callbacks() );
		$this->assertEmpty( $lc->get_uninstall_classes() );
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	public function test_on_activate_accumulates_callbacks(): void {
		$lc = Lifecycle::for( '/plugin/my-plugin.php' );

		$cb1 = static function () {};
		$cb2 = static function () {};

		$lc->on_activate( $cb1 )->on_activate( $cb2 );

		$this->assertCount( 2, $lc->get_activate_callbacks() );
	}

	public function test_on_deactivate_accumulates_callbacks(): void {
		$lc = Lifecycle::for( '/plugin/my-plugin.php' );

		$lc->on_deactivate( static function () {} );
		$lc->on_deactivate( static function () {} );

		$this->assertCount( 2, $lc->get_deactivate_callbacks() );
	}

	public function test_on_uninstall_accumulates_class_names(): void {
		$lc = Lifecycle::for( '/plugin/my-plugin.php' );

		$lc->on_uninstall( 'MyUninstaller' )
		   ->on_uninstall( 'AnotherUninstaller' );

		$this->assertSame( [ 'MyUninstaller', 'AnotherUninstaller' ], $lc->get_uninstall_classes() );
	}

	public function test_on_activate_returns_self_for_chaining(): void {
		$lc     = Lifecycle::for( '/plugin/my-plugin.php' );
		$result = $lc->on_activate( static function () {} );

		$this->assertSame( $lc, $result );
	}

	public function test_on_deactivate_returns_self_for_chaining(): void {
		$lc     = Lifecycle::for( '/plugin/my-plugin.php' );
		$result = $lc->on_deactivate( static function () {} );

		$this->assertSame( $lc, $result );
	}

	public function test_on_uninstall_returns_self_for_chaining(): void {
		$lc     = Lifecycle::for( '/plugin/my-plugin.php' );
		$result = $lc->on_uninstall( 'SomeClass' );

		$this->assertSame( $lc, $result );
	}

	// ---------------------------------------------------------------
	// register() — activation hook
	// ---------------------------------------------------------------

	public function test_register_calls_activation_hook_when_callbacks_exist(): void {
		$file = '/plugin/my-plugin.php';

		WP_Mock::userFunction( 'register_activation_hook' )
			->once()
			->andReturn( null );

		WP_Mock::userFunction( 'register_deactivation_hook' )->never();
		WP_Mock::userFunction( 'register_uninstall_hook' )->never();

		Lifecycle::for( $file )
			->on_activate( static function () {} )
			->register();

		$this->addToAssertionCount( 1 );
	}

	public function test_register_skips_activation_hook_when_no_callbacks(): void {
		WP_Mock::userFunction( 'register_activation_hook' )->never();
		WP_Mock::userFunction( 'register_deactivation_hook' )->never();
		WP_Mock::userFunction( 'register_uninstall_hook' )->never();

		Lifecycle::for( '/plugin/my-plugin.php' )->register();

		$this->addToAssertionCount( 1 );
	}

	// ---------------------------------------------------------------
	// register() — deactivation hook
	// ---------------------------------------------------------------

	public function test_register_calls_deactivation_hook_when_callbacks_exist(): void {
		WP_Mock::userFunction( 'register_activation_hook' )->never();

		WP_Mock::userFunction( 'register_deactivation_hook' )
			->once()
			->andReturn( null );

		WP_Mock::userFunction( 'register_uninstall_hook' )->never();

		Lifecycle::for( '/plugin/my-plugin.php' )
			->on_deactivate( static function () {} )
			->register();

		$this->addToAssertionCount( 1 );
	}

	// ---------------------------------------------------------------
	// register() — uninstall hook
	// ---------------------------------------------------------------

	public function test_register_calls_uninstall_hook_for_each_class(): void {
		WP_Mock::userFunction( 'register_activation_hook' )->never();
		WP_Mock::userFunction( 'register_deactivation_hook' )->never();

		WP_Mock::userFunction( 'register_uninstall_hook' )
			->times( 2 )
			->andReturn( null );

		Lifecycle::for( '/plugin/my-plugin.php' )
			->on_uninstall( 'UninstallerA' )
			->on_uninstall( 'UninstallerB' )
			->register();

		$this->addToAssertionCount( 1 );
	}

	// ---------------------------------------------------------------
	// register() — all hooks at once
	// ---------------------------------------------------------------

	public function test_register_all_hooks_simultaneously(): void {
		WP_Mock::userFunction( 'register_activation_hook' )->once()->andReturn( null );
		WP_Mock::userFunction( 'register_deactivation_hook' )->once()->andReturn( null );
		WP_Mock::userFunction( 'register_uninstall_hook' )->once()->andReturn( null );

		Lifecycle::for( '/plugin/my-plugin.php' )
			->on_activate( static function () {} )
			->on_deactivate( static function () {} )
			->on_uninstall( 'MyUninstaller' )
			->register();

		$this->addToAssertionCount( 1 );
	}

	// ---------------------------------------------------------------
	// Activation callbacks execute
	// ---------------------------------------------------------------

	public function test_activation_callbacks_are_all_invoked(): void {
		$called = array();
		$file   = '/plugin/my-plugin.php';

		$captured_hook = null;

		WP_Mock::userFunction( 'register_activation_hook' )
			->andReturnUsing(
				static function ( $path, $cb ) use ( &$captured_hook ) {
					$captured_hook = $cb;
				}
			);

		Lifecycle::for( $file )
			->on_activate( static function () use ( &$called ) { $called[] = 'first'; } )
			->on_activate( static function () use ( &$called ) { $called[] = 'second'; } )
			->register();

		// Simulate WP calling the hook.
		( $captured_hook )();

		$this->assertSame( [ 'first', 'second' ], $called );
	}

	// ---------------------------------------------------------------
	// Deactivation callbacks execute
	// ---------------------------------------------------------------

	public function test_deactivation_callbacks_are_all_invoked(): void {
		$called = array();

		$captured_hook = null;

		WP_Mock::userFunction( 'register_deactivation_hook' )
			->andReturnUsing(
				static function ( $path, $cb ) use ( &$captured_hook ) {
					$captured_hook = $cb;
				}
			);

		Lifecycle::for( '/plugin/my-plugin.php' )
			->on_deactivate( static function () use ( &$called ) { $called[] = 'a'; } )
			->on_deactivate( static function () use ( &$called ) { $called[] = 'b'; } )
			->register();

		( $captured_hook )();

		$this->assertSame( [ 'a', 'b' ], $called );
	}
}
