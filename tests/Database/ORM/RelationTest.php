<?php

declare(strict_types=1);

namespace WPFlint\Tests\Database\ORM;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Database\ORM\BelongsTo;
use WPFlint\Database\ORM\HasMany;
use WPFlint\Database\ORM\HasOne;
use WPFlint\Database\ORM\Model;
use WPFlint\Database\ORM\ModelQueryBuilder;
use WPFlint\Database\ORM\Relation;

/**
 * @covers \WPFlint\Database\ORM\HasOne
 * @covers \WPFlint\Database\ORM\HasMany
 * @covers \WPFlint\Database\ORM\BelongsTo
 * @covers \WPFlint\Database\ORM\Relation
 */
class RelationTest extends TestCase {

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
	// HasOne
	// ---------------------------------------------------------------

	public function testHasOneGetResultsReturnsSingleModel(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '10', 'user_id' => '1', 'bio' => 'hello' ) ) );

		$user     = RelUser::hydrate_one( array( 'id' => '1', 'name' => 'John' ) );
		$relation = new HasOne( $user, RelProfile::class, 'user_id', 'id' );

		$result = $relation->get_results();

		$this->assertInstanceOf( RelProfile::class, $result );
		$this->assertSame( '10', $result->get_attributes()['id'] );
	}

	public function testHasOneEagerLoadSetsRelations(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'id' => '10', 'user_id' => '1', 'bio' => 'hello' ),
					array( 'id' => '11', 'user_id' => '2', 'bio' => 'world' ),
				)
			);

		$user1 = RelUser::hydrate_one( array( 'id' => '1', 'name' => 'John' ) );
		$user2 = RelUser::hydrate_one( array( 'id' => '2', 'name' => 'Jane' ) );

		$relation = new HasOne( $user1, RelProfile::class, 'user_id', 'id' );
		$models   = $relation->eager_load( array( $user1, $user2 ), 'profile' );

		$this->assertTrue( $models[0]->relation_loaded( 'profile' ) );
		$this->assertTrue( $models[1]->relation_loaded( 'profile' ) );
		$this->assertSame( '10', $models[0]->get_relation( 'profile' )->get_attributes()['id'] );
		$this->assertSame( '11', $models[1]->get_relation( 'profile' )->get_attributes()['id'] );
	}

	public function testHasOneEagerLoadSetsNullForMissingRelation(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$user     = RelUser::hydrate_one( array( 'id' => '1', 'name' => 'John' ) );
		$relation = new HasOne( $user, RelProfile::class, 'user_id', 'id' );
		$models   = $relation->eager_load( array( $user ), 'profile' );

		$this->assertNull( $models[0]->get_relation( 'profile' ) );
	}

	// ---------------------------------------------------------------
	// HasMany
	// ---------------------------------------------------------------

	public function testHasManyGetResultsReturnsArrayOfModels(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'id' => '20', 'user_id' => '1', 'status' => 'pending' ),
					array( 'id' => '21', 'user_id' => '1', 'status' => 'paid' ),
				)
			);

		$user     = RelUser::hydrate_one( array( 'id' => '1', 'name' => 'John' ) );
		$relation = new HasMany( $user, RelOrder::class, 'user_id', 'id' );

		$results = $relation->get_results();

		$this->assertCount( 2, $results );
		$this->assertInstanceOf( RelOrder::class, $results[0] );
	}

	public function testHasManyEagerLoadGroupsByForeignKey(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'id' => '20', 'user_id' => '1', 'status' => 'pending' ),
					array( 'id' => '21', 'user_id' => '1', 'status' => 'paid' ),
					array( 'id' => '22', 'user_id' => '2', 'status' => 'pending' ),
				)
			);

		$user1 = RelUser::hydrate_one( array( 'id' => '1', 'name' => 'John' ) );
		$user2 = RelUser::hydrate_one( array( 'id' => '2', 'name' => 'Jane' ) );

		$relation = new HasMany( $user1, RelOrder::class, 'user_id', 'id' );
		$models   = $relation->eager_load( array( $user1, $user2 ), 'orders' );

		$this->assertCount( 2, $models[0]->get_relation( 'orders' ) );
		$this->assertCount( 1, $models[1]->get_relation( 'orders' ) );
	}

	public function testHasManyEagerLoadSetsEmptyArrayForMissing(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$user     = RelUser::hydrate_one( array( 'id' => '1', 'name' => 'John' ) );
		$relation = new HasMany( $user, RelOrder::class, 'user_id', 'id' );
		$models   = $relation->eager_load( array( $user ), 'orders' );

		$this->assertSame( array(), $models[0]->get_relation( 'orders' ) );
	}

	// ---------------------------------------------------------------
	// BelongsTo
	// ---------------------------------------------------------------

	public function testBelongsToGetResultsReturnsParentModel(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '1', 'name' => 'John' ) ) );

		$order    = RelOrder::hydrate_one( array( 'id' => '20', 'user_id' => '1', 'status' => 'pending' ) );
		$relation = new BelongsTo( $order, RelUser::class, 'user_id', 'id' );

		$result = $relation->get_results();

		$this->assertInstanceOf( RelUser::class, $result );
		$this->assertSame( '1', $result->get_attributes()['id'] );
	}

	public function testBelongsToGetResultsReturnsNullWhenFkNull(): void {
		$order    = RelOrder::hydrate_one( array( 'id' => '20', 'user_id' => null, 'status' => 'pending' ) );
		$relation = new BelongsTo( $order, RelUser::class, 'user_id', 'id' );

		$result = $relation->get_results();

		$this->assertNull( $result );
	}

	public function testBelongsToEagerLoadSetsParent(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'id' => '1', 'name' => 'John' ),
					array( 'id' => '2', 'name' => 'Jane' ),
				)
			);

		$order1 = RelOrder::hydrate_one( array( 'id' => '20', 'user_id' => '1', 'status' => 'pending' ) );
		$order2 = RelOrder::hydrate_one( array( 'id' => '21', 'user_id' => '2', 'status' => 'paid' ) );

		$relation = new BelongsTo( $order1, RelUser::class, 'user_id', 'id' );
		$models   = $relation->eager_load( array( $order1, $order2 ), 'user' );

		$this->assertSame( '1', $models[0]->get_relation( 'user' )->get_attributes()['id'] );
		$this->assertSame( '2', $models[1]->get_relation( 'user' )->get_attributes()['id'] );
	}

	public function testBelongsToEagerLoadSetsNullForMissing(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$order    = RelOrder::hydrate_one( array( 'id' => '20', 'user_id' => '999', 'status' => 'pending' ) );
		$relation = new BelongsTo( $order, RelUser::class, 'user_id', 'id' );
		$models   = $relation->eager_load( array( $order ), 'user' );

		$this->assertNull( $models[0]->get_relation( 'user' ) );
	}

	// ---------------------------------------------------------------
	// Eager load with empty keys
	// ---------------------------------------------------------------

	public function testEagerLoadSkipsWhenAllKeysNull(): void {
		$user     = RelUser::hydrate_one( array( 'id' => null, 'name' => 'Ghost' ) );
		$relation = new HasMany( $user, RelOrder::class, 'user_id', 'id' );
		$models   = $relation->eager_load( array( $user ), 'orders' );

		// No DB call should be made, and no relation loaded.
		$this->assertFalse( $models[0]->relation_loaded( 'orders' ) );
	}

	// ---------------------------------------------------------------
	// Relation accessors
	// ---------------------------------------------------------------

	public function testRelationGetters(): void {
		$user     = RelUser::hydrate_one( array( 'id' => '1' ) );
		$relation = new HasMany( $user, RelOrder::class, 'user_id', 'id' );

		$this->assertSame( RelOrder::class, $relation->get_related() );
		$this->assertSame( 'user_id', $relation->get_foreign_key() );
		$this->assertSame( 'id', $relation->get_local_key() );
	}

	// ---------------------------------------------------------------
	// Model eager_load_relations static method
	// ---------------------------------------------------------------

	public function testEagerLoadRelationsCallsRelationMethod(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'id' => '20', 'user_id' => '1', 'status' => 'pending' ),
				)
			);

		$user1 = RelUser::hydrate_one( array( 'id' => '1', 'name' => 'John' ) );

		$models = RelUser::eager_load_relations( array( $user1 ), array( 'orders' ) );

		$this->assertTrue( $models[0]->relation_loaded( 'orders' ) );
	}

	public function testEagerLoadRelationsReturnsEmptyOnEmptyModels(): void {
		$result = RelUser::eager_load_relations( array(), array( 'orders' ) );

		$this->assertSame( array(), $result );
	}

	// ---------------------------------------------------------------
	// Model relationship definition helpers
	// ---------------------------------------------------------------

	public function testModelHasOneInfersKeys(): void {
		$user     = RelUser::hydrate_one( array( 'id' => '1' ) );
		$relation = $user->has_one( RelProfile::class );

		$this->assertSame( 'rel_user_id', $relation->get_foreign_key() );
		$this->assertSame( 'id', $relation->get_local_key() );
	}

	public function testModelHasManyInfersKeys(): void {
		$user     = RelUser::hydrate_one( array( 'id' => '1' ) );
		$relation = $user->has_many( RelOrder::class );

		$this->assertSame( 'rel_user_id', $relation->get_foreign_key() );
		$this->assertSame( 'id', $relation->get_local_key() );
	}

	public function testModelBelongsToInfersKeys(): void {
		$order    = RelOrder::hydrate_one( array( 'id' => '1', 'user_id' => '5' ) );
		$relation = $order->belongs_to( RelUser::class );

		$this->assertSame( 'rel_user_id', $relation->get_foreign_key() );
		$this->assertSame( 'id', $relation->get_local_key() );
	}

	// ---------------------------------------------------------------
	// ModelQueryBuilder with()
	// ---------------------------------------------------------------

	public function testModelQueryBuilderWithSetsEagerLoads(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->twice()
			->andReturn(
				array( array( 'id' => '1', 'name' => 'John' ) ),
				array( array( 'id' => '20', 'user_id' => '1', 'status' => 'pending' ) )
			);

		$models = RelUser::where( 'name', 'John' )->with( array( 'orders' ) )->get_models();

		$this->assertCount( 1, $models );
		$this->assertTrue( $models[0]->relation_loaded( 'orders' ) );
	}
}

// ---------------------------------------------------------------
// Relation test model stubs
// ---------------------------------------------------------------

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

class RelUser extends Model {

	protected static string $table = 'users';

	/**
	 * User has many orders.
	 *
	 * @return HasMany
	 */
	public function orders(): HasMany {
		return $this->has_many( RelOrder::class, 'user_id', 'id' );
	}

	/**
	 * User has one profile.
	 *
	 * @return HasOne
	 */
	public function profile(): HasOne {
		return $this->has_one( RelProfile::class, 'user_id', 'id' );
	}
}

class RelOrder extends Model {

	protected static string $table = 'orders';

	/**
	 * Order belongs to user.
	 *
	 * @return BelongsTo
	 */
	public function user(): BelongsTo {
		return $this->belongs_to( RelUser::class, 'user_id', 'id' );
	}
}

class RelProfile extends Model {

	protected static string $table = 'profiles';
}

// phpcs:enable
