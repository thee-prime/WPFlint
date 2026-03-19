<?php

declare(strict_types=1);

namespace WPFlint\Tests\Console;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use Mockery;
use WPFlint\Console\Command;
use WPFlint\Console\MakeControllerCommand;
use WPFlint\Console\MakeMiddlewareCommand;
use WPFlint\Console\MakeRequestCommand;
use WPFlint\Console\MakeEventCommand;
use WPFlint\Console\MakeFacadeCommand;
use WPFlint\Console\MakeMigrationCommand;
use WPFlint\Console\MakeModelCommand;
use WPFlint\Console\MakeProviderCommand;
use WPFlint\Console\CacheClearCommand;
use WPFlint\Console\MigrateCommand;
use WPFlint\Cache\CacheManager;
use WPFlint\Cache\TaggedCache;
use WPFlint\Database\Migrations\Migrator;
use WPFlint\Database\Migrations\MigrationRepository;

/**
 * @covers \WPFlint\Console\Command
 * @covers \WPFlint\Console\MakeControllerCommand
 * @covers \WPFlint\Console\MakeMiddlewareCommand
 * @covers \WPFlint\Console\MakeRequestCommand
 * @covers \WPFlint\Console\MakeEventCommand
 * @covers \WPFlint\Console\MakeFacadeCommand
 * @covers \WPFlint\Console\MakeMigrationCommand
 * @covers \WPFlint\Console\MakeModelCommand
 * @covers \WPFlint\Console\MakeProviderCommand
 * @covers \WPFlint\Console\CacheClearCommand
 * @covers \WPFlint\Console\MigrateCommand
 */
class CommandTest extends TestCase {

	/**
	 * Temp directory for generated files.
	 *
	 * @var string
	 */
	private string $tmp_dir;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$abspath       = defined( 'ABSPATH' ) ? rtrim( ABSPATH, '/' ) : sys_get_temp_dir();
		$this->tmp_dir = $abspath . '/wpflint_test_' . uniqid();
		mkdir( $this->tmp_dir, 0777, true );

		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'wp_mkdir_p' )->andReturnUsing(
			function ( $dir ) {
				if ( ! is_dir( $dir ) ) {
					mkdir( $dir, 0777, true );
				}
				return true;
			}
		);
	}

	public function tearDown(): void {
		$this->remove_dir( $this->tmp_dir );
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Command base
	// -------------------------------------------------------------------------

	public function testSnakeCaseConvertsPascalCase(): void {
		$command = new MakeProviderCommand();
		$ref     = new \ReflectionMethod( $command, 'snake_case' );
		$ref->setAccessible( true );

		$this->assertSame( 'order_service', $ref->invoke( $command, 'OrderService' ) );
		$this->assertSame( 'user_profile', $ref->invoke( $command, 'UserProfile' ) );
	}

	public function testWriteFileCreatesFileAndCallsSuccess(): void {
		$command  = new MakeProviderCommand();
		$ref      = new \ReflectionMethod( $command, 'write_file' );
		$ref->setAccessible( true );

		$filepath = $this->tmp_dir . '/test_write/TestFile.php';

		$ref->invoke( $command, $filepath, '<?php // test' );

		$this->assertFileExists( $filepath );
		$this->assertStringContainsString( '// test', file_get_contents( $filepath ) );
	}

	// -------------------------------------------------------------------------
	// MakeControllerCommand
	// -------------------------------------------------------------------------

	public function testMakeControllerGeneratesController(): void {
		$cmd = new MakeControllerCommand();
		$cmd->__invoke(
			array( 'OrderController' ),
			array( 'path' => $this->relative_path() . '/app/Http/Controllers' )
		);

		$file = $this->tmp_dir . '/app/Http/Controllers/OrderController.php';
		$this->assertFileExists( $file );

		$content = file_get_contents( $file );
		$this->assertStringContainsString( 'class OrderController extends Controller', $content );
		$this->assertStringContainsString( 'use WPFlint\\Http\\Controller', $content );
		$this->assertStringContainsString( 'public function __construct()', $content );
	}

	public function testMakeControllerRestGeneratesRestController(): void {
		$cmd = new MakeControllerCommand();
		$cmd->__invoke(
			array( 'OrderController' ),
			array(
				'path' => $this->relative_path() . '/app/Http/Controllers',
				'rest' => true,
			)
		);

		$file    = $this->tmp_dir . '/app/Http/Controllers/OrderController.php';
		$content = file_get_contents( $file );
		$this->assertStringContainsString( 'class OrderController extends RestController', $content );
		$this->assertStringContainsString( 'use WPFlint\\Http\\RestController', $content );
		$this->assertStringContainsString( '$namespace', $content );
		$this->assertStringContainsString( '$rest_base', $content );
	}

	// -------------------------------------------------------------------------
	// MakeMiddlewareCommand
	// -------------------------------------------------------------------------

	public function testMakeMiddlewareGeneratesMiddleware(): void {
		$cmd = new MakeMiddlewareCommand();
		$cmd->__invoke(
			array( 'EnsureStoreIsOpen' ),
			array( 'path' => $this->relative_path() . '/app/Http/Middleware' )
		);

		$file    = $this->tmp_dir . '/app/Http/Middleware/EnsureStoreIsOpen.php';
		$content = file_get_contents( $file );
		$this->assertStringContainsString( 'class EnsureStoreIsOpen implements MiddlewareInterface', $content );
		$this->assertStringContainsString( 'public function handle( Request $request, Closure $next )', $content );
	}

	// -------------------------------------------------------------------------
	// MakeRequestCommand
	// -------------------------------------------------------------------------

	public function testMakeRequestGeneratesRequest(): void {
		$cmd = new MakeRequestCommand();
		$cmd->__invoke(
			array( 'StoreOrderRequest' ),
			array( 'path' => $this->relative_path() . '/app/Http/Requests' )
		);

		$file    = $this->tmp_dir . '/app/Http/Requests/StoreOrderRequest.php';
		$content = file_get_contents( $file );
		$this->assertStringContainsString( 'class StoreOrderRequest extends Request', $content );
		$this->assertStringContainsString( 'public function authorize(): bool', $content );
		$this->assertStringContainsString( 'public function rules(): array', $content );
		$this->assertStringContainsString( 'public function sanitize(): array', $content );
	}

	// -------------------------------------------------------------------------
	// MakeEventCommand
	// -------------------------------------------------------------------------

	public function testMakeEventGeneratesEvent(): void {
		$cmd = new MakeEventCommand();
		$cmd->__invoke(
			array( 'OrderPlaced' ),
			array( 'path' => $this->relative_path() . '/app/Events' )
		);

		$file    = $this->tmp_dir . '/app/Events/OrderPlaced.php';
		$content = file_get_contents( $file );
		$this->assertStringContainsString( 'class OrderPlaced extends Event', $content );
		$this->assertStringContainsString( 'use WPFlint\\Events\\Event', $content );
	}

	// -------------------------------------------------------------------------
	// MakeFacadeCommand
	// -------------------------------------------------------------------------

	public function testMakeFacadeGeneratesFacade(): void {
		$cmd = new MakeFacadeCommand();
		$cmd->__invoke(
			array( 'Order' ),
			array( 'path' => $this->relative_path() . '/app/Facades' )
		);

		$file    = $this->tmp_dir . '/app/Facades/Order.php';
		$content = file_get_contents( $file );
		$this->assertStringContainsString( 'class Order extends Facade', $content );
		$this->assertStringContainsString( 'protected static function get_facade_accessor(): string', $content );
	}

	// -------------------------------------------------------------------------
	// MakeMigrationCommand
	// -------------------------------------------------------------------------

	public function testMakeMigrationGeneratesMigrationWithTimestamp(): void {
		$cmd = new MakeMigrationCommand();
		$cmd->__invoke(
			array( 'CreateOrdersTable' ),
			array( 'path' => $this->relative_path() . '/database/migrations' )
		);

		$files = glob( $this->tmp_dir . '/database/migrations/*_create_orders_table.php' );
		$this->assertCount( 1, $files );

		$content = file_get_contents( $files[0] );
		$this->assertStringContainsString( 'class CreateOrdersTable extends Migration', $content );
		$this->assertMatchesRegularExpression( '/\d{4}_\d{2}_\d{2}_\d{6}/', basename( $files[0] ) );
	}

	public function testMakeMigrationGuessesTableName(): void {
		$cmd = new MakeMigrationCommand();
		$ref = new \ReflectionMethod( $cmd, 'guess_table_name' );
		$ref->setAccessible( true );

		$this->assertSame( 'orders', $ref->invoke( $cmd, 'CreateOrdersTable' ) );
		$this->assertSame( 'user_profiles', $ref->invoke( $cmd, 'CreateUserProfilesTable' ) );
	}

	// -------------------------------------------------------------------------
	// MakeModelCommand
	// -------------------------------------------------------------------------

	public function testMakeModelGeneratesModel(): void {
		$cmd = new MakeModelCommand();
		$cmd->__invoke(
			array( 'Order' ),
			array( 'path' => $this->relative_path() . '/app/Models' )
		);

		$file    = $this->tmp_dir . '/app/Models/Order.php';
		$content = file_get_contents( $file );
		$this->assertStringContainsString( 'class Order extends Model', $content );
		$this->assertStringContainsString( "\$table = 'orders'", $content );
		$this->assertStringContainsString( '$fillable', $content );
		$this->assertStringContainsString( '$casts', $content );
	}

	public function testMakeModelWithMigrationAlsoGeneratesMigration(): void {
		$cmd = new MakeModelCommand();
		$cmd->__invoke(
			array( 'Product' ),
			array(
				'path'      => $this->relative_path() . '/app/Models',
				'migration' => true,
			)
		);

		$this->assertFileExists( $this->tmp_dir . '/app/Models/Product.php' );

		// MakeModelCommand passes $assoc_args through, so migration uses same path.
		$migration_files = glob( $this->tmp_dir . '/app/Models/*_create_products_table.php' );
		$this->assertNotEmpty( $migration_files );
	}

	// -------------------------------------------------------------------------
	// MakeProviderCommand
	// -------------------------------------------------------------------------

	public function testMakeProviderGeneratesProvider(): void {
		$cmd = new MakeProviderCommand();
		$cmd->__invoke(
			array( 'OrderServiceProvider' ),
			array( 'path' => $this->relative_path() . '/app/Providers' )
		);

		$file    = $this->tmp_dir . '/app/Providers/OrderServiceProvider.php';
		$content = file_get_contents( $file );
		$this->assertStringContainsString( 'class OrderServiceProvider extends ServiceProvider', $content );
		$this->assertStringContainsString( 'public function register(): void', $content );
		$this->assertStringContainsString( 'public function boot(): void', $content );
	}

	// -------------------------------------------------------------------------
	// CacheClearCommand
	// -------------------------------------------------------------------------

	public function testCacheClearFlushesAllCache(): void {
		$cache = Mockery::mock( CacheManager::class );
		$cache->shouldReceive( 'flush' )->once()->andReturn( true );

		$cmd = new CacheClearCommand( $cache );
		$cmd->__invoke( array(), array() );

		$this->assertTrue( true ); // Mockery verifies the expectation.
	}

	public function testCacheClearFlushesTag(): void {
		$tagged = Mockery::mock( TaggedCache::class );
		$tagged->shouldReceive( 'flush' )->once()->andReturn( true );

		$cache = Mockery::mock( CacheManager::class );
		$cache->shouldReceive( 'tags' )->with( 'orders' )->once()->andReturn( $tagged );

		$cmd = new CacheClearCommand( $cache );
		$cmd->__invoke( array(), array( 'tag' => 'orders' ) );

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// MigrateCommand
	// -------------------------------------------------------------------------

	public function testMigrateRunsPendingMigrations(): void {
		$repository = Mockery::mock( MigrationRepository::class );
		$repository->shouldReceive( 'repository_exists' )->once()->andReturn( true );

		$migrator = Mockery::mock( Migrator::class );
		$migrator->shouldReceive( 'run' )->once()->andReturn( array( 'CreateOrdersTable' ) );

		$cmd = new MigrateCommand( $migrator, $repository );
		$cmd->__invoke( array(), array() );

		$this->assertTrue( true );
	}

	public function testMigrateStatusShowsMigrationStatus(): void {
		$repository = Mockery::mock( MigrationRepository::class );
		$repository->shouldReceive( 'repository_exists' )->once()->andReturn( true );

		$migrator = Mockery::mock( Migrator::class );
		$migrator->shouldReceive( 'get_status' )->once()->andReturn(
			array(
				array( 'migration' => 'CreateOrdersTable', 'ran' => true ),
				array( 'migration' => 'CreateProductsTable', 'ran' => false ),
			)
		);

		$cmd = new MigrateCommand( $migrator, $repository );
		$cmd->__invoke( array(), array( 'status' => true ) );

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get relative path from ABSPATH to tmp_dir.
	 *
	 * @return string
	 */
	private function relative_path(): string {
		$abspath = defined( 'ABSPATH' ) ? ABSPATH : '';
		return str_replace( rtrim( $abspath, '/' ), '', $this->tmp_dir );
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getRealPath() );
			} else {
				unlink( $item->getRealPath() );
			}
		}
		rmdir( $dir );
	}
}
