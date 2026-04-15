<?php

declare(strict_types=1);

namespace WPFlint\Tests\WordPress;

use Mockery;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\WordPress\Post;
use WPFlint\WordPress\PostMeta;
use WPFlint\WordPress\User;
use WPFlint\WordPress\UserMeta;
use WPFlint\WordPress\Comment;
use WPFlint\WordPress\CommentMeta;
use WPFlint\WordPress\Term;
use WPFlint\WordPress\TermTaxonomy;
use WPFlint\WordPress\TermRelationship;
use WPFlint\WordPress\Option;
use WPFlint\WordPress\Link;

/**
 * @covers \WPFlint\WordPress\Post
 * @covers \WPFlint\WordPress\PostMeta
 * @covers \WPFlint\WordPress\User
 * @covers \WPFlint\WordPress\UserMeta
 * @covers \WPFlint\WordPress\Comment
 * @covers \WPFlint\WordPress\CommentMeta
 * @covers \WPFlint\WordPress\Term
 * @covers \WPFlint\WordPress\TermTaxonomy
 * @covers \WPFlint\WordPress\TermRelationship
 * @covers \WPFlint\WordPress\Option
 * @covers \WPFlint\WordPress\Link
 */
class WpModelsTest extends TestCase {

	/** @var \Mockery\MockInterface */
	protected $wpdb;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->wpdb          = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix  = 'wp_';
		$this->wpdb->users   = 'wp_users';
		$this->wpdb->usermeta = 'wp_usermeta';

		$GLOBALS['wpdb'] = $this->wpdb;

		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
	}

	public function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Post
	// ---------------------------------------------------------------

	public function testPostTableName(): void {
		$this->assertSame( 'wp_posts', Post::get_table() );
	}

	public function testPostPrimaryKey(): void {
		$this->assertSame( 'ID', Post::get_primary_key() );
	}

	public function testPostTimestampsDisabled(): void {
		$post = new Post();
		$ref  = new \ReflectionProperty( Post::class, 'timestamps' );
		$ref->setAccessible( true );
		$this->assertFalse( $ref->getValue( $post ) );
	}

	public function testPostIdCastToInteger(): void {
		// Primary keys come from the DB; use hydrate_one() to bypass $fillable.
		$post = Post::hydrate_one( array( 'ID' => '42' ) );
		$this->assertSame( 42, $post->get_attribute( 'ID' ) );
	}

	public function testPostUserPassHidden(): void {
		$user = new User( array( 'ID' => 1, 'user_login' => 'admin', 'user_pass' => 'secret' ) );
		$arr  = $user->to_array();
		$this->assertArrayNotHasKey( 'user_pass', $arr );
		$this->assertArrayHasKey( 'user_login', $arr );
	}

	public function testPostScopePublished(): void {
		$this->wpdb->prefix = 'wp_';

		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = Post::published()->get_models();
		$this->assertIsArray( $results );
	}

	public function testPostScopeDraft(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = Post::draft()->get_models();
		$this->assertIsArray( $results );
	}

	public function testPostScopeType(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = Post::type( 'product' )->get_models();
		$this->assertIsArray( $results );
	}

	public function testPostScopeStatus(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = Post::status( 'trash' )->get_models();
		$this->assertIsArray( $results );
	}

	public function testPostRelationshipsExist(): void {
		$post = new Post( array( 'ID' => 1, 'post_author' => 2 ) );

		$this->assertInstanceOf( \WPFlint\Database\ORM\BelongsTo::class, $post->author() );
		$this->assertInstanceOf( \WPFlint\Database\ORM\HasMany::class, $post->meta() );
		$this->assertInstanceOf( \WPFlint\Database\ORM\HasMany::class, $post->comments() );
		$this->assertInstanceOf( \WPFlint\Database\ORM\BelongsTo::class, $post->parent_post() );
	}

	// ---------------------------------------------------------------
	// PostMeta
	// ---------------------------------------------------------------

	public function testPostMetaTableName(): void {
		$this->assertSame( 'wp_postmeta', PostMeta::get_table() );
	}

	public function testPostMetaPrimaryKey(): void {
		$this->assertSame( 'meta_id', PostMeta::get_primary_key() );
	}

	public function testPostMetaRelationshipExists(): void {
		$meta = new PostMeta( array( 'meta_id' => 1, 'post_id' => 5 ) );
		$this->assertInstanceOf( \WPFlint\Database\ORM\BelongsTo::class, $meta->post() );
	}

	// ---------------------------------------------------------------
	// User
	// ---------------------------------------------------------------

	public function testUserTableName(): void {
		$this->assertSame( 'wp_users', User::get_table() );
	}

	public function testUserPrimaryKey(): void {
		$this->assertSame( 'ID', User::get_primary_key() );
	}

	public function testUserActivationKeyHidden(): void {
		$user = new User( array( 'ID' => 1, 'user_login' => 'admin', 'user_activation_key' => 'key123' ) );
		$arr  = $user->to_array();
		$this->assertArrayNotHasKey( 'user_activation_key', $arr );
	}

	public function testUserRelationshipsExist(): void {
		$user = new User( array( 'ID' => 1 ) );

		$this->assertInstanceOf( \WPFlint\Database\ORM\HasMany::class, $user->posts() );
		$this->assertInstanceOf( \WPFlint\Database\ORM\HasMany::class, $user->meta() );
		$this->assertInstanceOf( \WPFlint\Database\ORM\HasMany::class, $user->comments() );
	}

	// ---------------------------------------------------------------
	// UserMeta
	// ---------------------------------------------------------------

	public function testUserMetaTableName(): void {
		$this->assertSame( 'wp_usermeta', UserMeta::get_table() );
	}

	public function testUserMetaPrimaryKey(): void {
		$this->assertSame( 'umeta_id', UserMeta::get_primary_key() );
	}

	public function testUserMetaRelationshipExists(): void {
		$meta = new UserMeta( array( 'umeta_id' => 1, 'user_id' => 3 ) );
		$this->assertInstanceOf( \WPFlint\Database\ORM\BelongsTo::class, $meta->user() );
	}

	// ---------------------------------------------------------------
	// Comment
	// ---------------------------------------------------------------

	public function testCommentTableName(): void {
		$this->assertSame( 'wp_comments', Comment::get_table() );
	}

	public function testCommentPrimaryKey(): void {
		$this->assertSame( 'comment_ID', Comment::get_primary_key() );
	}

	public function testCommentScopeApproved(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = Comment::approved()->get_models();
		$this->assertIsArray( $results );
	}

	public function testCommentScopePending(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = Comment::pending()->get_models();
		$this->assertIsArray( $results );
	}

	public function testCommentScopeSpam(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = Comment::spam()->get_models();
		$this->assertIsArray( $results );
	}

	public function testCommentRelationshipsExist(): void {
		$comment = new Comment( array( 'comment_ID' => 1, 'comment_post_ID' => 10, 'user_id' => 2 ) );

		$this->assertInstanceOf( \WPFlint\Database\ORM\BelongsTo::class, $comment->post() );
		$this->assertInstanceOf( \WPFlint\Database\ORM\BelongsTo::class, $comment->user() );
		$this->assertInstanceOf( \WPFlint\Database\ORM\HasMany::class, $comment->meta() );
	}

	// ---------------------------------------------------------------
	// CommentMeta
	// ---------------------------------------------------------------

	public function testCommentMetaTableName(): void {
		$this->assertSame( 'wp_commentmeta', CommentMeta::get_table() );
	}

	public function testCommentMetaPrimaryKey(): void {
		$this->assertSame( 'meta_id', CommentMeta::get_primary_key() );
	}

	public function testCommentMetaRelationshipExists(): void {
		$meta = new CommentMeta( array( 'meta_id' => 1, 'comment_id' => 5 ) );
		$this->assertInstanceOf( \WPFlint\Database\ORM\BelongsTo::class, $meta->comment() );
	}

	// ---------------------------------------------------------------
	// Term
	// ---------------------------------------------------------------

	public function testTermTableName(): void {
		$this->assertSame( 'wp_terms', Term::get_table() );
	}

	public function testTermPrimaryKey(): void {
		$this->assertSame( 'term_id', Term::get_primary_key() );
	}

	public function testTermRelationshipsExist(): void {
		$term = new Term( array( 'term_id' => 1, 'name' => 'News' ) );

		$this->assertInstanceOf( \WPFlint\Database\ORM\HasMany::class, $term->taxonomies() );
		$this->assertInstanceOf( \WPFlint\Database\ORM\HasOne::class, $term->taxonomy() );
	}

	// ---------------------------------------------------------------
	// TermTaxonomy
	// ---------------------------------------------------------------

	public function testTermTaxonomyTableName(): void {
		$this->assertSame( 'wp_term_taxonomy', TermTaxonomy::get_table() );
	}

	public function testTermTaxonomyPrimaryKey(): void {
		$this->assertSame( 'term_taxonomy_id', TermTaxonomy::get_primary_key() );
	}

	public function testTermTaxonomyScopeInTaxonomy(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = TermTaxonomy::in_taxonomy( 'category' )->get_models();
		$this->assertIsArray( $results );
	}

	public function testTermTaxonomyRelationshipsExist(): void {
		$tt = new TermTaxonomy( array( 'term_taxonomy_id' => 1, 'term_id' => 3 ) );

		$this->assertInstanceOf( \WPFlint\Database\ORM\BelongsTo::class, $tt->term() );
		$this->assertInstanceOf( \WPFlint\Database\ORM\HasMany::class, $tt->relationships() );
	}

	// ---------------------------------------------------------------
	// TermRelationship
	// ---------------------------------------------------------------

	public function testTermRelationshipTableName(): void {
		$this->assertSame( 'wp_term_relationships', TermRelationship::get_table() );
	}

	public function testTermRelationshipPrimaryKey(): void {
		$this->assertSame( 'object_id', TermRelationship::get_primary_key() );
	}

	public function testTermRelationshipRelationshipExists(): void {
		$tr = new TermRelationship( array( 'object_id' => 1, 'term_taxonomy_id' => 2 ) );
		$this->assertInstanceOf( \WPFlint\Database\ORM\BelongsTo::class, $tr->term_taxonomy() );
	}

	// ---------------------------------------------------------------
	// Option
	// ---------------------------------------------------------------

	public function testOptionTableName(): void {
		$this->assertSame( 'wp_options', Option::get_table() );
	}

	public function testOptionPrimaryKey(): void {
		$this->assertSame( 'option_id', Option::get_primary_key() );
	}

	public function testOptionScopeAutoloaded(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = Option::autoloaded()->get_models();
		$this->assertIsArray( $results );
	}

	public function testOptionScopeNotAutoloaded(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = Option::not_autoloaded()->get_models();
		$this->assertIsArray( $results );
	}

	// ---------------------------------------------------------------
	// Link
	// ---------------------------------------------------------------

	public function testLinkTableName(): void {
		$this->assertSame( 'wp_links', Link::get_table() );
	}

	public function testLinkPrimaryKey(): void {
		$this->assertSame( 'link_id', Link::get_primary_key() );
	}

	public function testLinkScopeVisible(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$results = Link::visible()->get_models();
		$this->assertIsArray( $results );
	}

	// ---------------------------------------------------------------
	// Extendability — user can extend WP models
	// ---------------------------------------------------------------

	public function testExtendedPostModelInheritsTableAndPrimaryKey(): void {
		$this->assertSame( 'wp_posts', TestProduct::get_table() );
		$this->assertSame( 'ID', TestProduct::get_primary_key() );
	}

	public function testExtendedPostScopeOverridesWork(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturnUsing( function ( $sql ) { return $sql; } );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array( 'ID' => 1, 'post_type' => 'product', 'post_status' => 'publish' ),
				)
			);

		$results = TestProduct::active()->get_models();
		$this->assertCount( 1, $results );
		$this->assertInstanceOf( TestProduct::class, $results[0] );
	}
}

// ---------------------------------------------------------------------------
// In-test stub: extended Post model for the extendability assertion
// ---------------------------------------------------------------------------

/**
 * Stub product model extending Post — simulates a plugin extending a WP model.
 */
class TestProduct extends Post {

	/**
	 * Scope: active products (published, post_type = product).
	 *
	 * @param \WPFlint\Database\ORM\ModelQueryBuilder $q Query builder.
	 * @return \WPFlint\Database\ORM\ModelQueryBuilder
	 */
	public function scope_active( \WPFlint\Database\ORM\ModelQueryBuilder $q ): \WPFlint\Database\ORM\ModelQueryBuilder {
		return $q->where( 'post_type', '=', 'product' )
				->where( 'post_status', '=', 'publish' );
	}
}
