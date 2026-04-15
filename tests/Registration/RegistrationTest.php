<?php

declare(strict_types=1);

namespace WPFlint\Tests\Registration;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Registration\MetaField;
use WPFlint\Registration\PostType;
use WPFlint\Registration\Taxonomy;

/**
 * @covers \WPFlint\Registration\PostType
 * @covers \WPFlint\Registration\Taxonomy
 * @covers \WPFlint\Registration\MetaField
 */
class RegistrationTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// PostType — construction
	// ---------------------------------------------------------------

	public function test_post_type_make_returns_instance(): void {
		$this->assertInstanceOf( PostType::class, PostType::make( 'book' ) );
	}

	public function test_post_type_get_slug(): void {
		$this->assertSame( 'book', PostType::make( 'book' )->get_slug() );
	}

	// ---------------------------------------------------------------
	// PostType — fluent setters stored in get_args()
	// ---------------------------------------------------------------

	public function test_post_type_public_sets_arg(): void {
		$args = PostType::make( 'book' )->public()->get_args();
		$this->assertTrue( $args['public'] );
	}

	public function test_post_type_public_false(): void {
		$args = PostType::make( 'book' )->public( false )->get_args();
		$this->assertFalse( $args['public'] );
	}

	public function test_post_type_hierarchical(): void {
		$args = PostType::make( 'book' )->hierarchical()->get_args();
		$this->assertTrue( $args['hierarchical'] );
	}

	public function test_post_type_supports(): void {
		$args = PostType::make( 'book' )->supports( array( 'title', 'editor' ) )->get_args();
		$this->assertSame( array( 'title', 'editor' ), $args['supports'] );
	}

	public function test_post_type_icon(): void {
		$args = PostType::make( 'book' )->icon( 'dashicons-book-alt' )->get_args();
		$this->assertSame( 'dashicons-book-alt', $args['menu_icon'] );
	}

	public function test_post_type_menu_position(): void {
		$args = PostType::make( 'book' )->menu_position( 25 )->get_args();
		$this->assertSame( 25, $args['menu_position'] );
	}

	public function test_post_type_has_archive(): void {
		$args = PostType::make( 'book' )->has_archive()->get_args();
		$this->assertTrue( $args['has_archive'] );
	}

	public function test_post_type_has_archive_custom_slug(): void {
		$args = PostType::make( 'book' )->has_archive( 'library' )->get_args();
		$this->assertSame( 'library', $args['has_archive'] );
	}

	public function test_post_type_rewrite_false(): void {
		$args = PostType::make( 'book' )->rewrite( false )->get_args();
		$this->assertFalse( $args['rewrite'] );
	}

	public function test_post_type_rewrite_array(): void {
		$rewrite = array( 'slug' => 'books', 'with_front' => false );
		$args    = PostType::make( 'book' )->rewrite( $rewrite )->get_args();
		$this->assertSame( $rewrite, $args['rewrite'] );
	}

	public function test_post_type_show_in_rest(): void {
		$args = PostType::make( 'book' )->show_in_rest()->get_args();
		$this->assertTrue( $args['show_in_rest'] );
	}

	public function test_post_type_rest_base(): void {
		$args = PostType::make( 'book' )->rest_base( 'books' )->get_args();
		$this->assertSame( 'books', $args['rest_base'] );
	}

	public function test_post_type_exclude_from_search(): void {
		$args = PostType::make( 'book' )->exclude_from_search()->get_args();
		$this->assertTrue( $args['exclude_from_search'] );
	}

	public function test_post_type_publicly_queryable(): void {
		$args = PostType::make( 'book' )->publicly_queryable( false )->get_args();
		$this->assertFalse( $args['publicly_queryable'] );
	}

	public function test_post_type_show_in_menu(): void {
		$args = PostType::make( 'book' )->show_in_menu( false )->get_args();
		$this->assertFalse( $args['show_in_menu'] );
	}

	public function test_post_type_capability_type(): void {
		$args = PostType::make( 'book' )->capability_type( 'book' )->get_args();
		$this->assertSame( 'book', $args['capability_type'] );
		$this->assertTrue( $args['map_meta_cap'] );
	}

	public function test_post_type_taxonomies(): void {
		$args = PostType::make( 'book' )->taxonomies( array( 'genre', 'author' ) )->get_args();
		$this->assertSame( array( 'genre', 'author' ), $args['taxonomies'] );
	}

	public function test_post_type_args_merges(): void {
		$args = PostType::make( 'book' )
			->public()
			->args( array( 'query_var' => false ) )
			->get_args();

		$this->assertTrue( $args['public'] );
		$this->assertFalse( $args['query_var'] );
	}

	// ---------------------------------------------------------------
	// PostType — labels
	// ---------------------------------------------------------------

	public function test_post_type_label_builds_name_and_singular(): void {
		$args = PostType::make( 'book' )->label( 'Book', 'Books' )->get_args();

		$this->assertSame( 'Books', $args['labels']['name'] );
		$this->assertSame( 'Book', $args['labels']['singular_name'] );
	}

	public function test_post_type_label_defaults_plural_to_singular_plus_s(): void {
		$args = PostType::make( 'book' )->label( 'Book' )->get_args();
		$this->assertSame( 'Books', $args['labels']['name'] );
	}

	public function test_post_type_label_builds_add_new_item(): void {
		$args = PostType::make( 'book' )->label( 'Book', 'Books' )->get_args();
		$this->assertStringContainsString( 'Book', $args['labels']['add_new_item'] );
	}

	public function test_post_type_no_label_excludes_labels_key(): void {
		$args = PostType::make( 'book' )->public()->get_args();
		$this->assertArrayNotHasKey( 'labels', $args );
	}

	// ---------------------------------------------------------------
	// PostType — registration
	// ---------------------------------------------------------------

	public function test_post_type_register_calls_register_post_type(): void {
		$registered = null;
		WP_Mock::userFunction( 'register_post_type' )->andReturnUsing(
			function ( string $slug ) use ( &$registered ) {
				$registered = $slug;
			}
		);

		PostType::make( 'book' )->public()->register();

		$this->assertSame( 'book', $registered );
	}

	public function test_post_type_unregister_calls_unregister_post_type(): void {
		$unregistered = null;
		WP_Mock::userFunction( 'unregister_post_type' )->andReturnUsing(
			function ( string $slug ) use ( &$unregistered ) {
				$unregistered = $slug;
			}
		);

		PostType::make( 'book' )->unregister();

		$this->assertSame( 'book', $unregistered );
	}

	public function test_post_type_registered_delegates_to_post_type_exists(): void {
		WP_Mock::userFunction( 'post_type_exists' )
			->with( 'book' )
			->andReturn( true );

		$this->assertTrue( PostType::make( 'book' )->registered() );
	}

	// ---------------------------------------------------------------
	// Taxonomy — construction
	// ---------------------------------------------------------------

	public function test_taxonomy_make_returns_instance(): void {
		$this->assertInstanceOf( Taxonomy::class, Taxonomy::make( 'genre' ) );
	}

	public function test_taxonomy_get_slug(): void {
		$this->assertSame( 'genre', Taxonomy::make( 'genre' )->get_slug() );
	}

	// ---------------------------------------------------------------
	// Taxonomy — fluent setters
	// ---------------------------------------------------------------

	public function test_taxonomy_for_single_post_type(): void {
		$tax = Taxonomy::make( 'genre' )->for( 'book' );
		$this->assertSame( array( 'book' ), $tax->get_post_types() );
	}

	public function test_taxonomy_for_multiple_post_types(): void {
		$tax = Taxonomy::make( 'genre' )->for( array( 'book', 'movie' ) );
		$this->assertSame( array( 'book', 'movie' ), $tax->get_post_types() );
	}

	public function test_taxonomy_for_chained(): void {
		$tax = Taxonomy::make( 'genre' )->for( 'book' )->for( 'movie' );
		$this->assertSame( array( 'book', 'movie' ), $tax->get_post_types() );
	}

	public function test_taxonomy_public(): void {
		$args = Taxonomy::make( 'genre' )->public()->get_args();
		$this->assertTrue( $args['public'] );
	}

	public function test_taxonomy_hierarchical(): void {
		$args = Taxonomy::make( 'genre' )->hierarchical()->get_args();
		$this->assertTrue( $args['hierarchical'] );
	}

	public function test_taxonomy_show_in_rest(): void {
		$args = Taxonomy::make( 'genre' )->show_in_rest()->get_args();
		$this->assertTrue( $args['show_in_rest'] );
	}

	public function test_taxonomy_rest_base(): void {
		$args = Taxonomy::make( 'genre' )->rest_base( 'genres' )->get_args();
		$this->assertSame( 'genres', $args['rest_base'] );
	}

	public function test_taxonomy_show_admin_column(): void {
		$args = Taxonomy::make( 'genre' )->show_admin_column()->get_args();
		$this->assertTrue( $args['show_admin_column'] );
	}

	public function test_taxonomy_show_tagcloud(): void {
		$args = Taxonomy::make( 'genre' )->show_tagcloud( false )->get_args();
		$this->assertFalse( $args['show_tagcloud'] );
	}

	public function test_taxonomy_rewrite(): void {
		$args = Taxonomy::make( 'genre' )->rewrite( array( 'slug' => 'genres' ) )->get_args();
		$this->assertSame( array( 'slug' => 'genres' ), $args['rewrite'] );
	}

	public function test_taxonomy_args_merges(): void {
		$args = Taxonomy::make( 'genre' )
			->public()
			->args( array( 'query_var' => 'genre_q' ) )
			->get_args();

		$this->assertTrue( $args['public'] );
		$this->assertSame( 'genre_q', $args['query_var'] );
	}

	// ---------------------------------------------------------------
	// Taxonomy — labels
	// ---------------------------------------------------------------

	public function test_taxonomy_label_builds_name_and_singular(): void {
		$args = Taxonomy::make( 'genre' )->label( 'Genre', 'Genres' )->get_args();

		$this->assertSame( 'Genres', $args['labels']['name'] );
		$this->assertSame( 'Genre', $args['labels']['singular_name'] );
	}

	public function test_taxonomy_label_defaults_plural(): void {
		$args = Taxonomy::make( 'genre' )->label( 'Genre' )->get_args();
		$this->assertSame( 'Genres', $args['labels']['name'] );
	}

	public function test_taxonomy_label_builds_add_new_item(): void {
		$args = Taxonomy::make( 'genre' )->label( 'Genre', 'Genres' )->get_args();
		$this->assertStringContainsString( 'Genre', $args['labels']['add_new_item'] );
	}

	// ---------------------------------------------------------------
	// Taxonomy — registration
	// ---------------------------------------------------------------

	public function test_taxonomy_register_calls_register_taxonomy(): void {
		$registered = null;
		WP_Mock::userFunction( 'register_taxonomy' )->andReturnUsing(
			function ( string $slug ) use ( &$registered ) {
				$registered = $slug;
			}
		);

		Taxonomy::make( 'genre' )->for( 'book' )->public()->register();

		$this->assertSame( 'genre', $registered );
	}

	public function test_taxonomy_unregister_calls_unregister_taxonomy(): void {
		$unregistered = null;
		WP_Mock::userFunction( 'unregister_taxonomy' )->andReturnUsing(
			function ( string $slug ) use ( &$unregistered ) {
				$unregistered = $slug;
			}
		);

		Taxonomy::make( 'genre' )->unregister();

		$this->assertSame( 'genre', $unregistered );
	}

	public function test_taxonomy_registered_delegates_to_taxonomy_exists(): void {
		WP_Mock::userFunction( 'taxonomy_exists' )
			->with( 'genre' )
			->andReturn( true );

		$this->assertTrue( Taxonomy::make( 'genre' )->registered() );
	}

	// ---------------------------------------------------------------
	// MetaField — static factories
	// ---------------------------------------------------------------

	public function test_meta_post_sets_object_type_and_subtype(): void {
		$meta = MetaField::post( 'book', '_price' );

		$this->assertSame( 'post', $meta->get_object_type() );
		$this->assertSame( 'book', $meta->get_subtype() );
		$this->assertSame( '_price', $meta->get_key() );
	}

	public function test_meta_term_sets_object_type(): void {
		$meta = MetaField::term( 'genre', '_color' );

		$this->assertSame( 'term', $meta->get_object_type() );
		$this->assertSame( 'genre', $meta->get_subtype() );
	}

	public function test_meta_user_sets_object_type(): void {
		$meta = MetaField::user( '_bio_extra' );

		$this->assertSame( 'user', $meta->get_object_type() );
		$this->assertSame( '', $meta->get_subtype() );
		$this->assertSame( '_bio_extra', $meta->get_key() );
	}

	public function test_meta_comment_sets_object_type(): void {
		$meta = MetaField::comment( '_rating' );

		$this->assertSame( 'comment', $meta->get_object_type() );
		$this->assertSame( '', $meta->get_subtype() );
	}

	// ---------------------------------------------------------------
	// MetaField — fluent setters
	// ---------------------------------------------------------------

	public function test_meta_type_sets_arg(): void {
		$args = MetaField::post( 'book', '_price' )->type( 'number' )->get_args();
		$this->assertSame( 'number', $args['type'] );
	}

	public function test_meta_single_sets_arg(): void {
		$args = MetaField::post( 'book', '_price' )->single()->get_args();
		$this->assertTrue( $args['single'] );
	}

	public function test_meta_single_false(): void {
		$args = MetaField::post( 'book', '_price' )->single( false )->get_args();
		$this->assertFalse( $args['single'] );
	}

	public function test_meta_default_sets_arg(): void {
		$args = MetaField::post( 'book', '_price' )->default( 0.0 )->get_args();
		$this->assertSame( 0.0, $args['default'] );
	}

	public function test_meta_description_sets_arg(): void {
		$args = MetaField::post( 'book', '_price' )->description( 'Book price.' )->get_args();
		$this->assertSame( 'Book price.', $args['description'] );
	}

	public function test_meta_sanitize_sets_callback(): void {
		$cb   = 'floatval';
		$args = MetaField::post( 'book', '_price' )->sanitize( $cb )->get_args();
		$this->assertSame( $cb, $args['sanitize_callback'] );
	}

	public function test_meta_auth_callback_sets_arg(): void {
		$cb   = function () { return true; };
		$args = MetaField::post( 'book', '_price' )->auth_callback( $cb )->get_args();
		$this->assertSame( $cb, $args['auth_callback'] );
	}

	public function test_meta_show_in_rest_true(): void {
		$args = MetaField::post( 'book', '_price' )->show_in_rest()->get_args();
		$this->assertTrue( $args['show_in_rest'] );
	}

	public function test_meta_show_in_rest_schema(): void {
		$schema = array( 'type' => 'number' );
		$args   = MetaField::post( 'book', '_price' )->show_in_rest( $schema )->get_args();
		$this->assertSame( $schema, $args['show_in_rest'] );
	}

	public function test_meta_args_merges(): void {
		$args = MetaField::post( 'book', '_price' )
			->single()
			->args( array( 'revisions_enabled' => true ) )
			->get_args();

		$this->assertTrue( $args['single'] );
		$this->assertTrue( $args['revisions_enabled'] );
	}

	// ---------------------------------------------------------------
	// MetaField — registration routing
	// ---------------------------------------------------------------

	public function test_meta_post_calls_register_post_meta(): void {
		$called_with = null;
		WP_Mock::userFunction( 'register_post_meta' )->andReturnUsing(
			function ( string $post_type, string $key ) use ( &$called_with ) {
				$called_with = array( $post_type, $key );
				return true;
			}
		);

		MetaField::post( 'book', '_price' )->type( 'number' )->single()->register();

		$this->assertSame( array( 'book', '_price' ), $called_with );
	}

	public function test_meta_term_calls_register_term_meta(): void {
		$called_with = null;
		WP_Mock::userFunction( 'register_term_meta' )->andReturnUsing(
			function ( string $taxonomy, string $key ) use ( &$called_with ) {
				$called_with = array( $taxonomy, $key );
				return true;
			}
		);

		MetaField::term( 'genre', '_color' )->type( 'string' )->single()->register();

		$this->assertSame( array( 'genre', '_color' ), $called_with );
	}

	public function test_meta_user_calls_register_meta(): void {
		$called_type = null;
		WP_Mock::userFunction( 'register_meta' )->andReturnUsing(
			function ( string $object_type ) use ( &$called_type ) {
				$called_type = $object_type;
				return true;
			}
		);

		MetaField::user( '_bio_extra' )->type( 'string' )->single()->register();

		$this->assertSame( 'user', $called_type );
	}

	public function test_meta_comment_calls_register_meta(): void {
		$called_type = null;
		WP_Mock::userFunction( 'register_meta' )->andReturnUsing(
			function ( string $object_type ) use ( &$called_type ) {
				$called_type = $object_type;
				return true;
			}
		);

		MetaField::comment( '_rating' )->type( 'integer' )->single()->register();

		$this->assertSame( 'comment', $called_type );
	}

	public function test_meta_unregister_calls_unregister_meta_key(): void {
		$called = null;
		WP_Mock::userFunction( 'unregister_meta_key' )->andReturnUsing(
			function ( string $type, string $key ) use ( &$called ) {
				$called = array( $type, $key );
				return true;
			}
		);

		MetaField::post( 'book', '_price' )->unregister();

		$this->assertSame( array( 'post', '_price' ), $called );
	}
}
