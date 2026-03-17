<?php

declare(strict_types=1);

namespace WPFlint\Tests\Config;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Application;
use WPFlint\Config\ConfigServiceProvider;
use WPFlint\Config\Repository;

/**
 * @covers \WPFlint\Config\ConfigServiceProvider
 */
class ConfigServiceProviderTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		Application::clear_instance();
	}

	public function tearDown(): void {
		Application::clear_instance();
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function testRegisterBindsConfigSingleton(): void {
		$app      = Application::get_instance( '/tmp/nonexistent-plugin' );
		$provider = new ConfigServiceProvider( $app );

		$provider->register();

		$config = $app->make( 'config' );

		$this->assertInstanceOf( Repository::class, $config );
	}

	public function testConfigIsSingleton(): void {
		$app      = Application::get_instance( '/tmp/nonexistent-plugin' );
		$provider = new ConfigServiceProvider( $app );

		$provider->register();

		$config_a = $app->make( 'config' );
		$config_b = $app->make( 'config' );

		$this->assertSame( $config_a, $config_b );
	}

	public function testConfigLoadsPhpFilesFromConfigDir(): void {
		$tmp_dir    = sys_get_temp_dir() . '/wpflint_config_test_' . uniqid();
		$config_dir = $tmp_dir . '/config';

		mkdir( $config_dir, 0755, true );

		file_put_contents(
			$config_dir . '/app.php',
			'<?php return array( "name" => "WPFlint", "debug" => false );'
		);
		file_put_contents(
			$config_dir . '/cache.php',
			'<?php return array( "driver" => "file", "ttl" => 3600 );'
		);

		$app      = Application::get_instance( $tmp_dir );
		$provider = new ConfigServiceProvider( $app );

		$provider->register();

		/** @var Repository $config */
		$config = $app->make( 'config' );

		$this->assertSame( 'WPFlint', $config->get( 'app.name' ) );
		$this->assertFalse( $config->get( 'app.debug' ) );
		$this->assertSame( 'file', $config->get( 'cache.driver' ) );
		$this->assertSame( 3600, $config->get( 'cache.ttl' ) );

		// Cleanup.
		unlink( $config_dir . '/app.php' );
		unlink( $config_dir . '/cache.php' );
		rmdir( $config_dir );
		rmdir( $tmp_dir );
	}

	public function testConfigReturnsEmptyWhenNoConfigDir(): void {
		$app      = Application::get_instance( '/tmp/no-such-dir-xyz' );
		$provider = new ConfigServiceProvider( $app );

		$provider->register();

		/** @var Repository $config */
		$config = $app->make( 'config' );

		$this->assertSame( array(), $config->all() );
	}

	public function testBootDoesNothing(): void {
		$app      = Application::get_instance();
		$provider = new ConfigServiceProvider( $app );

		// Should not throw.
		$provider->boot();

		$this->assertTrue( true );
	}
}
