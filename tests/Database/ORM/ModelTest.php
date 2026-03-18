<?php

declare(strict_types=1);

namespace WPFlint\Tests\Database\ORM;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Database\ORM\HasMany;
use WPFlint\Database\ORM\HasOne;
use WPFlint\Database\ORM\BelongsTo;
use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelNotFoundException;
use WPFlint\Database\ORM\ModelQueryBuilder;
use WPFlint\Database\ORM\QueryBuilder;

/**
 * @covers \WPFlint\Database\ORM\Model
 * @covers \WPFlint\Database\ORM\ModelQueryBuilder
 * @covers \WPFlint\Database\ORM\ModelNotFoundException
 */
class ModelTest extends TestCase {

	/**
	 * Mock $wpdb instance.
	 *
	 * @var \Mockery\MockInterface
	 */
	protected $wpdb;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		$GLOBALS['wpdb'] = $this->wpdb;
	}

	public function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Table name resolution
	// ---------------------------------------------------------------

	public function testGetTableReturnsPrefixedStaticTable(): void {
		$this->assertSame( 'wp_orders', TestOrder::get_table() );
	}

	public function testGetTableInfersFromClassName(): void {
		$this->assertSame( 'wp_test_products', TestProduct::get_table() );
	}

	public function testGetPrimaryKeyReturnsDefault(): void {
		$this->assertSame( 'id', TestOrder::get_primary_key() );
	}

	public function testInferTableNamePluralizesY(): void {
		// TestCategory -> test_categories.
		$this->assertSame( 'wp_test_categories', TestCategory::get_table() );
	}

	// ---------------------------------------------------------------
	// Constructor / fill
	// ---------------------------------------------------------------

	public function testConstructorFillsAttributes(): void {
		$model = new TestOrder( array( 'status' => 'pending', 'total' => 10 ) );

		$this->assertSame( 'pending', $model->get_attribute( 'status' ) );
		$this->assertSame( 10.0, $model->get_attribute( 'total' ) );
	}

	public function testFillRespectsGuardedFillable(): void {
		$model = new TestOrder( array( 'status' => 'pending', 'secret' => 'val' ) );

		$this->assertSame( 'pending', $model->get_attribute( 'status' ) );
		$this->assertNull( $model->get_attribute( 'secret' ) );
	}

	public function testFillAllowsEverythingWhenFillableEmpty(): void {
		$model = new TestOpenModel( array( 'anything' => 'yes' ) );

		$this->assertSame( 'yes', $model->get_attribute( 'anything' ) );
	}

	// ---------------------------------------------------------------
	// Attribute access
	// ---------------------------------------------------------------

	public function testMagicGetAndSet(): void {
		$model         = new TestOrder();
		$model->status = 'paid';

		$this->assertSame( 'paid', $model->status );
	}

	public function testMagicIsset(): void {
		$model = new TestOrder( array( 'status' => 'pending' ) );

		$this->assertTrue( isset( $model->status ) );
		$this->assertFalse( isset( $model->nonexistent ) );
	}

	public function testSetAndGetAttribute(): void {
		$model = new TestOrder();
		$model->set_attribute( 'status', 'paid' );

		$this->assertSame( 'paid', $model->get_attribute( 'status' ) );
	}

	public function testGetAttributesReturnsAll(): void {
		$model = new TestOrder( array( 'status' => 'pending', 'total' => 10 ) );
		$attrs = $model->get_attributes();

		$this->assertSame(
			array( 'status' => 'pending', 'total' => 10 ),
			$attrs
		);
	}

	// ---------------------------------------------------------------
	// Casting
	// ---------------------------------------------------------------

	public function testCastToInt(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '42', 'status' => 'paid', 'total' => '10', 'meta' => '{}' ) );

		$this->assertSame( 42, $model->get_attribute( 'id' ) );
	}

	public function testCastToFloat(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'paid', 'total' => '99.50', 'meta' => '{}' ) );

		$this->assertSame( 99.5, $model->get_attribute( 'total' ) );
	}

	public function testCastToArray(): void {
		$json  = '{"foo":"bar"}';
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'paid', 'total' => '0', 'meta' => $json ) );

		$this->assertSame( array( 'foo' => 'bar' ), $model->get_attribute( 'meta' ) );
	}

	public function testCastArrayFromAlreadyArray(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'paid', 'total' => '0', 'meta' => array( 'foo' => 'bar' ) ) );

		$this->assertSame( array( 'foo' => 'bar' ), $model->get_attribute( 'meta' ) );
	}

	public function testCastToBool(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'paid', 'total' => '0', 'meta' => '{}', 'active' => '1' ) );

		$this->assertTrue( $model->get_attribute( 'active' ) );
	}

	public function testCastNullReturnsNull(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'paid', 'total' => null, 'meta' => null ) );

		$this->assertNull( $model->get_attribute( 'total' ) );
	}

	// ---------------------------------------------------------------
	// Dirty tracking
	// ---------------------------------------------------------------

	public function testIsDirtyAfterChange(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending', 'total' => '10', 'meta' => '{}' ) );
		$model->set_attribute( 'status', 'paid' );

		$this->assertTrue( $model->is_dirty() );
		$this->assertTrue( $model->is_dirty( 'status' ) );
		$this->assertFalse( $model->is_dirty( 'total' ) );
	}

	public function testGetDirtyReturnsChangedAttributes(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending', 'total' => '10', 'meta' => '{}' ) );
		$model->set_attribute( 'status', 'paid' );

		$this->assertSame( array( 'status' => 'paid' ), $model->get_dirty() );
	}

	public function testNotDirtyWhenUnchanged(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending', 'total' => '10', 'meta' => '{}' ) );

		$this->assertFalse( $model->is_dirty() );
	}

	// ---------------------------------------------------------------
	// Hydration
	// ---------------------------------------------------------------

	public function testHydrateOneSetsExistsTrue(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending' ) );

		$this->assertTrue( $model->exists() );
	}

	public function testHydrateManyReturnsArrayOfModels(): void {
		$rows = array(
			array( 'id' => '1', 'status' => 'pending' ),
			array( 'id' => '2', 'status' => 'paid' ),
		);

		$models = TestOrder::hydrate_many( $rows );

		$this->assertCount( 2, $models );
		$this->assertInstanceOf( TestOrder::class, $models[0] );
		$this->assertTrue( $models[0]->exists() );
	}

	// ---------------------------------------------------------------
	// Static finders
	// ---------------------------------------------------------------

	public function testFindReturnsModel(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '1', 'status' => 'pending' ) ) );

		$model = TestOrder::find( 1 );

		$this->assertInstanceOf( TestOrder::class, $model );
		$this->assertTrue( $model->exists() );
	}

	public function testFindReturnsNullWhenNotFound(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$model = TestOrder::find( 999 );

		$this->assertNull( $model );
	}

	public function testFindOrFailThrowsException(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		WP_Mock::userFunction( '__', array(
			'return' => function ( $text ) {
				return $text;
			},
		) );

		$this->expectException( ModelNotFoundException::class );

		TestOrder::find_or_fail( 999 );
	}

	public function testAllReturnsArrayOfModels(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'id' => '1', 'status' => 'pending' ),
					array( 'id' => '2', 'status' => 'paid' ),
				)
			);

		$models = TestOrder::all();

		$this->assertCount( 2, $models );
	}

	// ---------------------------------------------------------------
	// Static where
	// ---------------------------------------------------------------

	public function testWhereReturnsModelQueryBuilder(): void {
		$builder = TestOrder::where( 'status', 'pending' );

		$this->assertInstanceOf( ModelQueryBuilder::class, $builder );
	}

	public function testWhereInReturnsModelQueryBuilder(): void {
		$builder = TestOrder::where_in( 'id', array( 1, 2, 3 ) );

		$this->assertInstanceOf( ModelQueryBuilder::class, $builder );
	}

	// ---------------------------------------------------------------
	// create() / first_or_create() / update_or_create()
	// ---------------------------------------------------------------

	public function testCreatePersistsAndReturns(): void {
		WP_Mock::userFunction( 'current_time', array(
			'return' => '2026-03-17 12:00:00',
		) );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$this->wpdb->insert_id = 5;

		$model = TestOrder::create( array( 'status' => 'pending', 'total' => 10 ) );

		$this->assertInstanceOf( TestOrder::class, $model );
		$this->assertTrue( $model->exists() );
		$this->assertSame( 5, $model->get_attribute( 'id' ) );
	}

	public function testFirstOrCreateFindsExisting(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '1', 'status' => 'pending' ) ) );

		$model = TestOrder::first_or_create( array( 'status' => 'pending' ) );

		$this->assertTrue( $model->exists() );
		$this->assertSame( '1', $model->get_attributes()['id'] );
	}

	public function testFirstOrCreateCreatesNew(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		WP_Mock::userFunction( 'current_time', array(
			'return' => '2026-03-17 12:00:00',
		) );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$this->wpdb->insert_id = 10;

		$model = TestOrder::first_or_create(
			array( 'status' => 'new' ),
			array( 'total' => 50 )
		);

		$this->assertTrue( $model->exists() );
	}

	// ---------------------------------------------------------------
	// save() — insert
	// ---------------------------------------------------------------

	public function testSaveInsertsNewModel(): void {
		WP_Mock::userFunction( 'current_time', array(
			'return' => '2026-03-17 12:00:00',
		) );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$this->wpdb->insert_id = 7;

		$model = new TestOrder( array( 'status' => 'pending', 'total' => 20 ) );
		$result = $model->save();

		$this->assertTrue( $result );
		$this->assertTrue( $model->exists() );
		$this->assertSame( 7, $model->get_attribute( 'id' ) );
		$this->assertSame( '2026-03-17 12:00:00', $model->get_attributes()['created_at'] );
	}

	public function testSaveReturnsFalseOnInsertFailure(): void {
		WP_Mock::userFunction( 'current_time', array(
			'return' => '2026-03-17 12:00:00',
		) );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$model = new TestOrder( array( 'status' => 'pending', 'total' => 20 ) );
		$result = $model->save();

		$this->assertFalse( $result );
		$this->assertFalse( $model->exists() );
	}

	// ---------------------------------------------------------------
	// save() — update
	// ---------------------------------------------------------------

	public function testSaveUpdatesExistingModel(): void {
		WP_Mock::userFunction( 'current_time', array(
			'return' => '2026-03-17 13:00:00',
		) );

		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending', 'total' => '10', 'meta' => '{}' ) );
		$model->set_attribute( 'status', 'paid' );
		$result = $model->save();

		$this->assertTrue( $result );
	}

	public function testSaveSkipsUpdateWhenNotDirty(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending', 'total' => '10', 'meta' => '{}' ) );
		$result = $model->save();

		$this->assertTrue( $result );
	}

	// ---------------------------------------------------------------
	// delete()
	// ---------------------------------------------------------------

	public function testDeleteRemovesModel(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$model  = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending' ) );
		$result = $model->delete();

		$this->assertTrue( $result );
		$this->assertFalse( $model->exists() );
	}

	public function testDeleteReturnsFalseWhenNotExists(): void {
		$model  = new TestOrder( array( 'status' => 'pending' ) );
		$result = $model->delete();

		$this->assertFalse( $result );
	}

	// ---------------------------------------------------------------
	// fresh()
	// ---------------------------------------------------------------

	public function testFreshReloadsFromDb(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '1', 'status' => 'updated' ) ) );

		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending' ) );
		$model->fresh();

		$this->assertSame( 'updated', $model->get_attributes()['status'] );
	}

	public function testFreshReturnsSelfWhenNotExists(): void {
		$model  = new TestOrder( array( 'status' => 'pending' ) );
		$result = $model->fresh();

		$this->assertSame( $model, $result );
	}

	// ---------------------------------------------------------------
	// destroy()
	// ---------------------------------------------------------------

	public function testDestroyDeletesByPk(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$result = TestOrder::destroy( 1 );

		$this->assertSame( 1, $result );
	}

	// ---------------------------------------------------------------
	// Serialization
	// ---------------------------------------------------------------

	public function testToArrayExcludesHidden(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending', 'total' => '10', 'meta' => '{}', 'password' => 'secret' ) );
		$array = $model->to_array();

		$this->assertArrayHasKey( 'status', $array );
		$this->assertArrayNotHasKey( 'password', $array );
	}

	public function testToArrayCastsValues(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending', 'total' => '99.50', 'meta' => '{"k":"v"}' ) );
		$array = $model->to_array();

		$this->assertSame( 1, $array['id'] );
		$this->assertSame( 99.5, $array['total'] );
		$this->assertSame( array( 'k' => 'v' ), $array['meta'] );
	}

	public function testToJsonReturnsString(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending', 'total' => '10', 'meta' => '{}' ) );
		$json  = $model->to_json();

		$this->assertIsString( $json );
		$decoded = json_decode( $json, true );
		$this->assertSame( 1, $decoded['id'] );
	}

	public function testToArrayIncludesLoadedRelations(): void {
		$order = TestOrder::hydrate_one( array( 'id' => '1', 'status' => 'pending', 'total' => '10', 'meta' => '{}' ) );
		$item  = TestOpenModel::hydrate_one( array( 'id' => '10', 'name' => 'Widget' ) );
		$order->set_relation( 'items', array( $item ) );

		$array = $order->to_array();

		$this->assertArrayHasKey( 'items', $array );
		$this->assertCount( 1, $array['items'] );
		$this->assertSame( 'Widget', $array['items'][0]['name'] );
	}

	// ---------------------------------------------------------------
	// Scopes
	// ---------------------------------------------------------------

	public function testScopeCallsViaCallStatic(): void {
		$builder = TestOrder::pending();

		$this->assertInstanceOf( ModelQueryBuilder::class, $builder );
	}

	public function testScopeWithArguments(): void {
		$builder = TestOrder::with_min_total( 100 );

		$this->assertInstanceOf( ModelQueryBuilder::class, $builder );
	}

	public function testUndefinedScopeThrowsException(): void {
		WP_Mock::userFunction( '__', array(
			'return' => function ( $text ) {
				return $text;
			},
		) );

		$this->expectException( \RuntimeException::class );

		TestOrder::nonexistent_scope();
	}

	// ---------------------------------------------------------------
	// Relations setup
	// ---------------------------------------------------------------

	public function testHasOneReturnsHasOneInstance(): void {
		$model    = TestOrder::hydrate_one( array( 'id' => '1' ) );
		$relation = $model->has_one( TestOpenModel::class, 'order_id', 'id' );

		$this->assertInstanceOf( HasOne::class, $relation );
	}

	public function testHasManyReturnsHasManyInstance(): void {
		$model    = TestOrder::hydrate_one( array( 'id' => '1' ) );
		$relation = $model->has_many( TestOpenModel::class, 'order_id', 'id' );

		$this->assertInstanceOf( HasMany::class, $relation );
	}

	public function testBelongsToReturnsBelongsToInstance(): void {
		$model    = TestOrder::hydrate_one( array( 'id' => '1', 'user_id' => '5' ) );
		$relation = $model->belongs_to( TestOpenModel::class, 'user_id', 'id' );

		$this->assertInstanceOf( BelongsTo::class, $relation );
	}

	public function testSetAndGetRelation(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1' ) );
		$model->set_relation( 'user', 'test_value' );

		$this->assertSame( 'test_value', $model->get_relation( 'user' ) );
		$this->assertTrue( $model->relation_loaded( 'user' ) );
		$this->assertFalse( $model->relation_loaded( 'nonexistent' ) );
	}

	public function testMagicGetReturnsRelationBeforeAttribute(): void {
		$model = TestOrder::hydrate_one( array( 'id' => '1' ) );
		$model->set_relation( 'items', array( 'loaded' ) );

		$this->assertSame( array( 'loaded' ), $model->items );
	}

	// ---------------------------------------------------------------
	// Serialization: array cast serialization
	// ---------------------------------------------------------------

	public function testSerializeAttributeEncodesArray(): void {
		WP_Mock::userFunction( 'current_time', array(
			'return' => '2026-03-17 12:00:00',
		) );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_orders',
				Mockery::on( function ( $data ) {
					return '{"items":[1,2]}' === $data['meta'];
				} ),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->wpdb->insert_id = 1;

		$model = new TestOrder( array( 'status' => 'pending', 'total' => 10, 'meta' => array( 'items' => array( 1, 2 ) ) ) );
		$model->save();

		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// ModelQueryBuilder
	// ---------------------------------------------------------------

	public function testGetModelsReturnsModelInstances(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'id' => '1', 'status' => 'pending' ),
					array( 'id' => '2', 'status' => 'paid' ),
				)
			);

		$models = TestOrder::where( 'total', '>', 0 )->get_models();

		$this->assertCount( 2, $models );
		$this->assertInstanceOf( TestOrder::class, $models[0] );
	}

	public function testFirstModelReturnsOneOrNull(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '1', 'status' => 'pending' ) ) );

		$model = TestOrder::where( 'status', 'pending' )->first_model();

		$this->assertInstanceOf( TestOrder::class, $model );
	}

	public function testFirstModelReturnsNullWhenEmpty(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$model = TestOrder::where( 'status', 'nonexistent' )->first_model();

		$this->assertNull( $model );
	}

	// ---------------------------------------------------------------
	// No timestamps
	// ---------------------------------------------------------------

	public function testTimestampsDisabledDoesNotSetTimestamps(): void {
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_no_timestamps_models',
				Mockery::on( function ( $data ) {
					return ! isset( $data['created_at'] ) && ! isset( $data['updated_at'] );
				} ),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->wpdb->insert_id = 1;

		$model = new TestNoTimestampsModel( array( 'name' => 'test' ) );
		$model->save();

		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// first_or_new / update_or_create
	// ---------------------------------------------------------------

	public function testFirstOrNewReturnsExistingModel(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '1', 'status' => 'pending' ) ) );

		$model = TestOrder::first_or_new( array( 'status' => 'pending' ) );

		$this->assertTrue( $model->exists() );
	}

	public function testFirstOrNewReturnsNewModel(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$model = TestOrder::first_or_new(
			array( 'status' => 'new_status' ),
			array( 'total' => 100 )
		);

		$this->assertFalse( $model->exists() );
		$this->assertSame( 100.0, $model->get_attribute( 'total' ) );
	}

	public function testQueryReturnsQueryBuilder(): void {
		$builder = TestOrder::query();

		$this->assertInstanceOf( QueryBuilder::class, $builder );
	}
}

// ---------------------------------------------------------------
// Test model stubs
// ---------------------------------------------------------------

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

class TestOrder extends Model {

	protected static string $table = 'orders';

	protected array $fillable = array( 'status', 'total', 'meta' );

	protected array $hidden = array( 'password' );

	protected array $casts = array(
		'id'     => 'integer',
		'total'  => 'float',
		'meta'   => 'array',
		'active' => 'boolean',
	);

	/**
	 * @param ModelQueryBuilder $query Query builder.
	 * @return ModelQueryBuilder
	 */
	public function scope_pending( ModelQueryBuilder $query ): ModelQueryBuilder {
		return $query->where( 'status', '=', 'pending' );
	}

	/**
	 * @param ModelQueryBuilder $query     Query builder.
	 * @param int               $min_total Minimum total.
	 * @return ModelQueryBuilder
	 */
	public function scope_with_min_total( ModelQueryBuilder $query, int $min_total ): ModelQueryBuilder {
		return $query->where( 'total', '>=', $min_total );
	}
}

class TestProduct extends Model {
	// Table auto-inferred: test_products.
}

class TestCategory extends Model {
	// Table auto-inferred: test_categories (y -> ies).
}

class TestOpenModel extends Model {

	protected static string $table = 'open_models';

	// Empty fillable = no guarding.
}

class TestNoTimestampsModel extends Model {

	protected static string $table = 'no_timestamps_models';

	protected static bool $timestamps = false;
}

// phpcs:enable
