<?php

declare(strict_types=1);

namespace WPFlint\Tests\Database\ORM;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Database\ORM\QueryBuilder;
use WPFlint\Database\ORM\RawExpression;

/**
 * @covers \WPFlint\Database\ORM\QueryBuilder
 */
class QueryBuilderTest extends TestCase {

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
	// Static constructor
	// ---------------------------------------------------------------

	public function testTableCreatesBuilderWithPrefixedName(): void {
		$builder = QueryBuilder::table( 'orders' );

		$this->assertSame( 'wp_orders', $builder->get_table() );
	}

	// ---------------------------------------------------------------
	// build_select() — SQL compilation
	// ---------------------------------------------------------------

	public function testBuildSelectSimple(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders', $sql );
	}

	public function testBuildSelectWithColumns(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->select( array( 'id', 'status' ) )->build_select();

		$this->assertSame( 'SELECT id, status FROM wp_orders', $sql );
	}

	public function testBuildSelectDistinct(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->distinct()->build_select();

		$this->assertSame( 'SELECT DISTINCT * FROM wp_orders', $sql );
	}

	public function testBuildSelectWithWhere(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->where( 'status', '=', 'pending' )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders WHERE status = %s', $sql );
		$this->assertSame( array( 'pending' ), $builder->get_bindings() );
	}

	public function testBuildSelectWithWhereTwoArgs(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->where( 'status', 'pending' )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders WHERE status = %s', $sql );
	}

	public function testBuildSelectWithOrWhere(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder
			->where( 'status', 'pending' )
			->or_where( 'status', 'paid' )
			->build_select();

		$this->assertSame(
			'SELECT * FROM wp_orders WHERE status = %s OR status = %s',
			$sql
		);
		$this->assertSame( array( 'pending', 'paid' ), $builder->get_bindings() );
	}

	public function testBuildSelectWithWhereIn(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->where_in( 'id', array( 1, 2, 3 ) )->build_select();

		$this->assertSame(
			'SELECT * FROM wp_orders WHERE id IN (%d, %d, %d)',
			$sql
		);
		$this->assertSame( array( 1, 2, 3 ), $builder->get_bindings() );
	}

	public function testBuildSelectWithWhereNotIn(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->where_not_in( 'id', array( 1, 2 ) )->build_select();

		$this->assertSame(
			'SELECT * FROM wp_orders WHERE id NOT IN (%d, %d)',
			$sql
		);
	}

	public function testBuildSelectWithWhereNull(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->where_null( 'deleted_at' )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders WHERE deleted_at IS NULL', $sql );
	}

	public function testBuildSelectWithWhereNotNull(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->where_not_null( 'paid_at' )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders WHERE paid_at IS NOT NULL', $sql );
	}

	public function testBuildSelectWithWhereBetween(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->where_between( 'total', 10, 100 )->build_select();

		$this->assertSame(
			'SELECT * FROM wp_orders WHERE total BETWEEN %d AND %d',
			$sql
		);
		$this->assertSame( array( 10, 100 ), $builder->get_bindings() );
	}

	public function testBuildSelectWithWhereNotBetween(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->where_not_between( 'total', 10, 100 )->build_select();

		$this->assertSame(
			'SELECT * FROM wp_orders WHERE total NOT BETWEEN %d AND %d',
			$sql
		);
	}

	public function testBuildSelectWithWhereLike(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->where_like( 'name', '%john%' )->build_select();

		$this->assertSame(
			'SELECT * FROM wp_orders WHERE name LIKE %s',
			$sql
		);
	}

	public function testBuildSelectWithWhereRaw(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->where_raw( 'total > %d', array( 50 ) )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders WHERE total > %d', $sql );
		$this->assertSame( array( 50 ), $builder->get_bindings() );
	}

	public function testBuildSelectWithOrderBy(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->order_by( 'created_at', 'DESC' )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders ORDER BY created_at DESC', $sql );
	}

	public function testBuildSelectWithLatest(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->latest()->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders ORDER BY created_at DESC', $sql );
	}

	public function testBuildSelectWithOldest(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->oldest()->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders ORDER BY created_at ASC', $sql );
	}

	public function testBuildSelectWithGroupBy(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->group_by( 'status' )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders GROUP BY status', $sql );
	}

	public function testBuildSelectWithHaving(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder
			->group_by( 'status' )
			->having( 'COUNT(*)', '>', 5 )
			->build_select();

		$this->assertSame(
			'SELECT * FROM wp_orders GROUP BY status HAVING COUNT(*) > %d',
			$sql
		);
		$this->assertSame( array( 5 ), $builder->get_bindings() );
	}

	public function testBuildSelectWithLimit(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->limit( 10 )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders LIMIT %d', $sql );
		$this->assertSame( array( 10 ), $builder->get_bindings() );
	}

	public function testBuildSelectWithOffset(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->limit( 10 )->offset( 20 )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders LIMIT %d OFFSET %d', $sql );
		$this->assertSame( array( 10, 20 ), $builder->get_bindings() );
	}

	public function testBuildSelectWithInnerJoin(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder
			->join( 'users', 'wp_orders.user_id', '=', 'wp_users.id' )
			->build_select();

		$this->assertSame(
			'SELECT * FROM wp_orders INNER JOIN wp_users ON wp_orders.user_id = wp_users.id',
			$sql
		);
	}

	public function testBuildSelectWithLeftJoin(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder
			->left_join( 'users', 'wp_orders.user_id', '=', 'wp_users.id' )
			->build_select();

		$this->assertSame(
			'SELECT * FROM wp_orders LEFT JOIN wp_users ON wp_orders.user_id = wp_users.id',
			$sql
		);
	}

	public function testBuildSelectWithAddSelect(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder
			->add_select( 'id' )
			->add_select( 'status' )
			->build_select();

		$this->assertSame( 'SELECT id, status FROM wp_orders', $sql );
	}

	public function testTakeSetsLimitAndOffset(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->take( 5, 10 )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders LIMIT %d OFFSET %d', $sql );
		$this->assertSame( array( 5, 10 ), $builder->get_bindings() );
	}

	public function testOrderByInvalidDirectionDefaultsToAsc(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder->order_by( 'id', 'INVALID' )->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders ORDER BY id ASC', $sql );
	}

	// ---------------------------------------------------------------
	// get()
	// ---------------------------------------------------------------

	public function testGetReturnsArrayOfRows(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'id' => '1', 'status' => 'pending' ),
					array( 'id' => '2', 'status' => 'paid' ),
				)
			);

		$builder = new QueryBuilder( 'wp_orders' );
		$results = $builder->get();

		$this->assertCount( 2, $results );
		$this->assertSame( '1', $results[0]['id'] );
	}

	public function testGetWithWhereUsesPrepare(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SELECT * FROM wp_orders WHERE status = 'pending'" );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$builder = new QueryBuilder( 'wp_orders' );
		$builder->where( 'status', 'pending' )->get();

		$this->assertConditionsMet();
	}

	public function testGetReturnsEmptyArrayOnNull(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( null );

		$builder = new QueryBuilder( 'wp_orders' );
		$results = $builder->get();

		$this->assertSame( array(), $results );
	}

	// ---------------------------------------------------------------
	// first()
	// ---------------------------------------------------------------

	public function testFirstReturnsFirstRow(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '1' ) ) );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->where( 'status', 'pending' )->first();

		$this->assertSame( '1', $result['id'] );
	}

	public function testFirstReturnsNullWhenEmpty(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->first();

		$this->assertNull( $result );
	}

	// ---------------------------------------------------------------
	// find()
	// ---------------------------------------------------------------

	public function testFindByPrimaryKey(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '1', 'status' => 'pending' ) ) );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->find( 1 );

		$this->assertSame( '1', $result['id'] );
	}

	// ---------------------------------------------------------------
	// value()
	// ---------------------------------------------------------------

	public function testValueReturnsSingleColumnValue(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'status' => 'pending' ) ) );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->where( 'id', 1 )->value( 'status' );

		$this->assertSame( 'pending', $result );
	}

	public function testValueReturnsNullWhenNoRows(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->value( 'status' );

		$this->assertNull( $result );
	}

	// ---------------------------------------------------------------
	// pluck()
	// ---------------------------------------------------------------

	public function testPluckReturnsArrayOfValues(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'status' => 'pending' ),
					array( 'status' => 'paid' ),
				)
			);

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->pluck( 'status' );

		$this->assertSame( array( 'pending', 'paid' ), $result );
	}

	public function testPluckWithKeyReturnsKeyedArray(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'id' => '1', 'status' => 'pending' ),
					array( 'id' => '2', 'status' => 'paid' ),
				)
			);

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->pluck( 'status', 'id' );

		$this->assertSame( array( '1' => 'pending', '2' => 'paid' ), $result );
	}

	// ---------------------------------------------------------------
	// exists() / doesnt_exist()
	// ---------------------------------------------------------------

	public function testExistsReturnsTrueWhenRowFound(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '1' ) ) );

		$builder = new QueryBuilder( 'wp_orders' );

		$this->assertTrue( $builder->where( 'status', 'pending' )->exists() );
	}

	public function testExistsReturnsFalseWhenNoRows(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$builder = new QueryBuilder( 'wp_orders' );

		$this->assertFalse( $builder->exists() );
	}

	public function testDoesntExistReturnsTrueWhenNoRows(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$builder = new QueryBuilder( 'wp_orders' );

		$this->assertTrue( $builder->doesnt_exist() );
	}

	// ---------------------------------------------------------------
	// Aggregates
	// ---------------------------------------------------------------

	public function testCountReturnsInteger(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '5' );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->count();

		$this->assertSame( 5, $result );
	}

	public function testCountWithWhereUsesPrepare(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '3' );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->where( 'status', 'pending' )->count();

		$this->assertSame( 3, $result );
	}

	public function testMaxReturnsValue(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '99.50' );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->max( 'total' );

		$this->assertSame( '99.50', $result );
	}

	public function testMinReturnsValue(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '1.00' );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->min( 'total' );

		$this->assertSame( '1.00', $result );
	}

	public function testAvgReturnsValue(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '50.25' );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->avg( 'total' );

		$this->assertSame( '50.25', $result );
	}

	public function testSumReturnsValue(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '500' );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->sum( 'total' );

		$this->assertSame( '500', $result );
	}

	// ---------------------------------------------------------------
	// insert()
	// ---------------------------------------------------------------

	public function testInsertCallsWpdbInsert(): void {
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_orders',
				array( 'status' => 'pending', 'total' => 10.5 ),
				array( '%s', '%f' )
			)
			->andReturn( 1 );

		$this->wpdb->insert_id = 42;

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->insert( array( 'status' => 'pending', 'total' => 10.5 ) );

		$this->assertSame( 42, $result );
	}

	public function testInsertReturnsFalseOnFailure(): void {
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->insert( array( 'status' => 'pending' ) );

		$this->assertFalse( $result );
	}

	// ---------------------------------------------------------------
	// insert_many()
	// ---------------------------------------------------------------

	public function testInsertManyInsertsMultipleRows(): void {
		$this->wpdb->shouldReceive( 'insert' )
			->twice()
			->andReturn( 1 );

		$this->wpdb->insert_id = 1;

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->insert_many(
			array(
				array( 'status' => 'pending' ),
				array( 'status' => 'paid' ),
			)
		);

		$this->assertSame( 2, $result );
	}

	// ---------------------------------------------------------------
	// update()
	// ---------------------------------------------------------------

	public function testUpdateCallsWpdbQuery(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "UPDATE wp_orders SET status = 'paid' WHERE id = 1" );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->where( 'id', '=', 1 )->update( array( 'status' => 'paid' ) );

		$this->assertSame( 1, $result );
	}

	public function testUpdateReturnsFalseOnError(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( false );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->where( 'id', '=', 1 )->update( array( 'status' => 'paid' ) );

		$this->assertFalse( $result );
	}

	// ---------------------------------------------------------------
	// delete()
	// ---------------------------------------------------------------

	public function testDeleteCallsWpdbQuery(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "DELETE FROM wp_orders WHERE id = 1" );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->where( 'id', '=', 1 )->delete();

		$this->assertSame( 1, $result );
	}

	public function testDeleteWithoutWhereExecutes(): void {
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( 'DELETE FROM wp_orders' )
			->andReturn( 5 );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->delete();

		$this->assertSame( 5, $result );
	}

	// ---------------------------------------------------------------
	// increment() / decrement()
	// ---------------------------------------------------------------

	public function testIncrementUsesRawExpression(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->where( 'id', '=', 1 )->increment( 'views' );

		$this->assertSame( 1, $result );
	}

	public function testDecrementUsesRawExpression(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->where( 'id', '=', 1 )->decrement( 'stock', 3 );

		$this->assertSame( 1, $result );
	}

	// ---------------------------------------------------------------
	// paginate()
	// ---------------------------------------------------------------

	public function testPaginateReturnsStructuredResult(): void {
		// First call for count.
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '25' );

		// Second call for data.
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'id' => '1' ),
					array( 'id' => '2' ),
				)
			);

		$builder = new QueryBuilder( 'wp_orders' );
		$result  = $builder->paginate( 10, 1 );

		$this->assertSame( 25, $result['total'] );
		$this->assertSame( 10, $result['per_page'] );
		$this->assertSame( 1, $result['current_page'] );
		$this->assertSame( 3, $result['last_page'] );
		$this->assertCount( 2, $result['data'] );
	}

	// ---------------------------------------------------------------
	// chunk()
	// ---------------------------------------------------------------

	public function testChunkProcessesInBatches(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->twice()
			->andReturn(
				array( array( 'id' => '1' ), array( 'id' => '2' ) ),
				array()
			);

		$chunks  = array();
		$builder = new QueryBuilder( 'wp_orders' );
		$builder->chunk(
			2,
			function ( array $rows ) use ( &$chunks ) {
				$chunks[] = $rows;
			}
		);

		$this->assertCount( 1, $chunks );
		$this->assertCount( 2, $chunks[0] );
	}

	public function testChunkStopsWhenCallbackReturnsFalse(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array( array( 'id' => '1' ) ) );

		$builder = new QueryBuilder( 'wp_orders' );
		$builder->chunk(
			2,
			function () {
				return false;
			}
		);

		$this->assertConditionsMet();
	}

	// ---------------------------------------------------------------
	// Placeholders
	// ---------------------------------------------------------------

	public function testIntegerValueUsesPercentD(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$builder->where( 'id', '=', 5 );
		$sql = $builder->build_select();

		$this->assertStringContainsString( '%d', $sql );
	}

	public function testFloatValueUsesPercentF(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$builder->where( 'total', '>', 9.99 );
		$sql = $builder->build_select();

		$this->assertStringContainsString( '%f', $sql );
	}

	public function testStringValueUsesPercentS(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$builder->where( 'status', '=', 'pending' );
		$sql = $builder->build_select();

		$this->assertStringContainsString( '%s', $sql );
	}

	// ---------------------------------------------------------------
	// RawExpression
	// ---------------------------------------------------------------

	public function testRawExpressionIsInsertedVerbatim(): void {
		$raw = new RawExpression( 'NOW()' );

		$this->assertSame( 'NOW()', $raw->get_expression() );
		$this->assertSame( 'NOW()', (string) $raw );
	}

	// ---------------------------------------------------------------
	// Utility
	// ---------------------------------------------------------------

	public function testCloneBuilderReturnsNewInstance(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$clone   = $builder->clone_builder();

		$this->assertNotSame( $builder, $clone );
		$this->assertSame( $builder->get_table(), $clone->get_table() );
	}

	public function testResetWheresClears(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$builder->where( 'status', 'pending' )->reset_wheres();
		$sql = $builder->build_select();

		$this->assertSame( 'SELECT * FROM wp_orders', $sql );
	}

	// ---------------------------------------------------------------
	// Complex combinations
	// ---------------------------------------------------------------

	public function testComplexQueryBuildsCorrectSql(): void {
		$builder = new QueryBuilder( 'wp_orders' );
		$sql     = $builder
			->select( array( 'id', 'status', 'total' ) )
			->where( 'status', 'pending' )
			->where( 'total', '>', 100 )
			->order_by( 'created_at', 'DESC' )
			->limit( 10 )
			->offset( 0 )
			->build_select();

		$this->assertSame(
			'SELECT id, status, total FROM wp_orders WHERE status = %s AND total > %d ORDER BY created_at DESC LIMIT %d OFFSET %d',
			$sql
		);

		$this->assertSame( array( 'pending', 100, 10, 0 ), $builder->get_bindings() );
	}
}
