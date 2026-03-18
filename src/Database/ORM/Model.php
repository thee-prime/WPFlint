<?php
/**
 * Abstract base model — Active Record pattern over $wpdb.
 *
 * @package WPFlint\Database\ORM
 */

declare(strict_types=1);

namespace WPFlint\Database\ORM;

use RuntimeException;

/**
 * Base class for all database models.
 *
 * Provides static finders, instance persistence, attribute casting,
 * automatic timestamps, scopes, and relationships.
 */
abstract class Model {

	/**
	 * Table name (unprefixed). Override in subclass or auto-inferred.
	 *
	 * @var string
	 */
	protected static string $table = '';

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected static string $primary_key = 'id';

	/**
	 * Whether to auto-manage timestamps.
	 *
	 * @var bool
	 */
	protected static bool $timestamps = true;

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array
	 */
	protected array $fillable = array();

	/**
	 * Attributes hidden from toArray/toJson.
	 *
	 * @var array
	 */
	protected array $hidden = array();

	/**
	 * Attribute casting map.
	 *
	 * Supported: int, integer, float, double, string, bool, boolean, array, json, datetime.
	 *
	 * @var array
	 */
	protected array $casts = array();

	/**
	 * Model attributes.
	 *
	 * @var array
	 */
	protected array $attributes = array();

	/**
	 * Original attribute values (from DB load).
	 *
	 * @var array
	 */
	protected array $original = array();

	/**
	 * Whether this model exists in the database.
	 *
	 * @var bool
	 */
	protected bool $exists = false;

	/**
	 * Loaded relations.
	 *
	 * @var array
	 */
	protected array $relations = array();

	// ---------------------------------------------------------------
	// Constructor
	// ---------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * @param array $attributes Initial attributes.
	 */
	public function __construct( array $attributes = array() ) {
		$this->fill( $attributes );
	}

	// ---------------------------------------------------------------
	// Table name resolution
	// ---------------------------------------------------------------

	/**
	 * Get the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function get_table(): string {
		global $wpdb;

		$table = static::$table;

		if ( '' === $table ) {
			$table = static::infer_table_name();
		}

		return $wpdb->prefix . $table;
	}

	/**
	 * Get the unprefixed table name.
	 *
	 * @return string
	 */
	public static function get_unprefixed_table(): string {
		$table = static::$table;

		if ( '' === $table ) {
			$table = static::infer_table_name();
		}

		return $table;
	}

	/**
	 * Infer table name from the class name.
	 *
	 * UserOrder -> user_orders (snake_case + simple pluralize).
	 *
	 * @return string
	 */
	protected static function infer_table_name(): string {
		$class = ( new \ReflectionClass( static::class ) )->getShortName();
		$snake = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1_$2', $class ) );

		// Simple pluralization.
		if ( 'y' === substr( $snake, -1 ) && ! in_array( substr( $snake, -2, 1 ), array( 'a', 'e', 'i', 'o', 'u' ), true ) ) {
			return substr( $snake, 0, -1 ) . 'ies';
		}

		if ( 's' === substr( $snake, -1 ) ) {
			return $snake . 'es';
		}

		return $snake . 's';
	}

	/**
	 * Get the primary key column name.
	 *
	 * @return string
	 */
	public static function get_primary_key(): string {
		return static::$primary_key;
	}

	// ---------------------------------------------------------------
	// Static query starters
	// ---------------------------------------------------------------

	/**
	 * Start a new query builder for this model.
	 *
	 * @return QueryBuilder
	 */
	public static function query(): QueryBuilder {
		return new QueryBuilder( static::get_table() );
	}

	/**
	 * Find a model by primary key.
	 *
	 * @param mixed $id Primary key value.
	 * @return static|null
	 */
	public static function find( $id ) {
		$row = static::query()->find( $id, static::$primary_key );

		return null !== $row ? static::hydrate_one( $row ) : null;
	}

	/**
	 * Find a model by primary key or throw.
	 *
	 * @param mixed $id Primary key value.
	 * @return static
	 * @throws ModelNotFoundException If not found.
	 */
	public static function find_or_fail( $id ) {
		$model = static::find( $id );

		if ( null === $model ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
			throw new ModelNotFoundException(
				sprintf(
					/* translators: 1: model class name 2: primary key value */
					__( 'No %1$s found with key %2$s.', 'wpflint' ),
					static::class,
					$id
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $model;
	}

	/**
	 * Get all models.
	 *
	 * @return array Array of static instances.
	 */
	public static function all(): array {
		$rows = static::query()->get();

		return static::hydrate_many( $rows );
	}

	/**
	 * Start a WHERE query.
	 *
	 * @param string $column   Column name.
	 * @param string $operator Operator or value.
	 * @param mixed  $value    Value.
	 * @return ModelQueryBuilder
	 */
	public static function where( string $column, $operator = null, $value = null ): ModelQueryBuilder {
		$builder = new ModelQueryBuilder( static::get_table(), static::class );

		return $builder->where( $column, $operator, $value );
	}

	/**
	 * Start a WHERE IN query.
	 *
	 * @param string $column Column name.
	 * @param array  $values Values.
	 * @return ModelQueryBuilder
	 */
	public static function where_in( string $column, array $values ): ModelQueryBuilder {
		$builder = new ModelQueryBuilder( static::get_table(), static::class );

		return $builder->where_in( $column, $values );
	}

	/**
	 * Create a new model and persist it.
	 *
	 * @param array $attributes Attributes to fill.
	 * @return static
	 */
	public static function create( array $attributes ) {
		$model = new static( $attributes );
		$model->save();

		return $model;
	}

	/**
	 * Find a model matching attributes, or create it.
	 *
	 * @param array $attributes Attributes to search by.
	 * @param array $values     Additional values for creation.
	 * @return static
	 */
	public static function first_or_create( array $attributes, array $values = array() ) {
		$builder = new ModelQueryBuilder( static::get_table(), static::class );

		foreach ( $attributes as $column => $value ) {
			$builder->where( $column, '=', $value );
		}

		$model = $builder->first_model();

		if ( null !== $model ) {
			return $model;
		}

		return static::create( array_merge( $attributes, $values ) );
	}

	/**
	 * Find a model matching attributes, or create a new instance (not persisted).
	 *
	 * @param array $attributes Attributes to search by.
	 * @param array $values     Additional values for the new instance.
	 * @return static
	 */
	public static function first_or_new( array $attributes, array $values = array() ) {
		$builder = new ModelQueryBuilder( static::get_table(), static::class );

		foreach ( $attributes as $column => $value ) {
			$builder->where( $column, '=', $value );
		}

		$model = $builder->first_model();

		if ( null !== $model ) {
			return $model;
		}

		return new static( array_merge( $attributes, $values ) );
	}

	/**
	 * Update or create a model.
	 *
	 * @param array $attributes Attributes to search by.
	 * @param array $values     Values to update or create with.
	 * @return static
	 */
	public static function update_or_create( array $attributes, array $values = array() ) {
		$model = static::first_or_new( $attributes );
		$model->fill( $values );
		$model->save();

		return $model;
	}

	/**
	 * Delete a model by primary key.
	 *
	 * @param mixed $id Primary key value.
	 * @return int|false
	 */
	public static function destroy( $id ) {
		return static::query()
			->where( static::$primary_key, '=', $id )
			->delete();
	}

	// ---------------------------------------------------------------
	// Cache integration
	// ---------------------------------------------------------------

	/**
	 * Find a model by primary key, caching the result.
	 *
	 * @param mixed $id  Primary key value.
	 * @param int   $ttl Time to live in seconds (default 3600).
	 * @return static|null
	 */
	public static function cached( $id, int $ttl = 3600 ) {
		$cache = static::resolve_cache();

		if ( null === $cache ) {
			return static::find( $id );
		}

		$key = static::get_unprefixed_table() . '_' . $id;

		return $cache->remember(
			$key,
			$ttl,
			function () use ( $id ) {
				return static::find( $id );
			}
		);
	}

	/**
	 * Find a model by primary key, bypassing cache.
	 *
	 * @param mixed $id Primary key value.
	 * @return static|null
	 */
	public static function fresh_find( $id ) {
		$cache = static::resolve_cache();

		if ( null !== $cache ) {
			$key = static::get_unprefixed_table() . '_' . $id;
			$cache->forget( $key );
		}

		return static::find( $id );
	}

	/**
	 * Resolve the CacheManager from the Application container.
	 *
	 * @return \WPFlint\Cache\CacheManager|null
	 */
	protected static function resolve_cache() {
		if ( ! class_exists( '\\WPFlint\\Application' ) ) {
			return null;
		}

		try {
			return \WPFlint\Application::get_instance()->make( 'cache' );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	// ---------------------------------------------------------------
	// Scopes — __callStatic dispatches scopeX methods
	// ---------------------------------------------------------------

	/**
	 * Handle dynamic static method calls for scopes.
	 *
	 * Model::pending() calls scopePending()/scope_pending() on a new builder.
	 *
	 * @param string $method    Method name.
	 * @param array  $arguments Arguments.
	 * @return ModelQueryBuilder
	 * @throws RuntimeException If scope method not found.
	 */
	public static function __callStatic( string $method, array $arguments ) {
		$scope_method = 'scope_' . $method;
		$instance     = new static();

		if ( method_exists( $instance, $scope_method ) ) {
			$builder = new ModelQueryBuilder( static::get_table(), static::class );
			return $instance->{$scope_method}( $builder, ...$arguments );
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
		throw new RuntimeException(
			sprintf(
				/* translators: 1: method name 2: class name */
				__( 'Method %1$s does not exist on %2$s.', 'wpflint' ),
				$method,
				static::class
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	// ---------------------------------------------------------------
	// Instance — attribute access
	// ---------------------------------------------------------------

	/**
	 * Fill the model with an array of attributes.
	 *
	 * Only fillable attributes are set if $fillable is non-empty.
	 *
	 * @param array $attributes Attribute key-value pairs.
	 * @return $this
	 */
	public function fill( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			if ( $this->is_fillable( $key ) ) {
				$this->attributes[ $key ] = $value;
			}
		}

		return $this;
	}

	/**
	 * Check if an attribute is mass-assignable.
	 *
	 * @param string $key Attribute name.
	 * @return bool
	 */
	protected function is_fillable( string $key ): bool {
		if ( empty( $this->fillable ) ) {
			return true;
		}

		return in_array( $key, $this->fillable, true );
	}

	/**
	 * Get an attribute value (with casting).
	 *
	 * @param string $key Attribute name.
	 * @return mixed
	 */
	public function get_attribute( string $key ) {
		$value = $this->attributes[ $key ] ?? null;

		return $this->cast_attribute( $key, $value );
	}

	/**
	 * Set an attribute value.
	 *
	 * @param string $key   Attribute name.
	 * @param mixed  $value Value.
	 * @return $this
	 */
	public function set_attribute( string $key, $value ): self {
		$this->attributes[ $key ] = $value;
		return $this;
	}

	/**
	 * Get all raw attributes.
	 *
	 * @return array
	 */
	public function get_attributes(): array {
		return $this->attributes;
	}

	/**
	 * Get only dirty (changed) attributes.
	 *
	 * @return array
	 */
	public function get_dirty(): array {
		$dirty = array();

		foreach ( $this->attributes as $key => $value ) {
			if ( ! array_key_exists( $key, $this->original ) || $this->original[ $key ] !== $value ) {
				$dirty[ $key ] = $value;
			}
		}

		return $dirty;
	}

	/**
	 * Check if the model or a specific attribute is dirty.
	 *
	 * @param string|null $key Optional specific attribute.
	 * @return bool
	 */
	public function is_dirty( ?string $key = null ): bool {
		if ( null !== $key ) {
			return array_key_exists( $key, $this->get_dirty() );
		}

		return ! empty( $this->get_dirty() );
	}

	/**
	 * Magic getter.
	 *
	 * @param string $key Attribute name.
	 * @return mixed
	 */
	public function __get( string $key ) {
		if ( array_key_exists( $key, $this->relations ) ) {
			return $this->relations[ $key ];
		}

		return $this->get_attribute( $key );
	}

	/**
	 * Magic setter.
	 *
	 * @param string $key   Attribute name.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function __set( string $key, $value ): void {
		$this->set_attribute( $key, $value );
	}

	/**
	 * Magic isset.
	 *
	 * @param string $key Attribute name.
	 * @return bool
	 */
	public function __isset( string $key ): bool {
		return array_key_exists( $key, $this->attributes )
			|| array_key_exists( $key, $this->relations );
	}

	// ---------------------------------------------------------------
	// Casting
	// ---------------------------------------------------------------

	/**
	 * Cast an attribute to its declared type.
	 *
	 * @param string $key   Attribute name.
	 * @param mixed  $value Raw value.
	 * @return mixed Cast value.
	 */
	protected function cast_attribute( string $key, $value ) {
		if ( null === $value || ! isset( $this->casts[ $key ] ) ) {
			return $value;
		}

		switch ( $this->casts[ $key ] ) {
			case 'int':
			case 'integer':
				return (int) $value;

			case 'float':
			case 'double':
				return (float) $value;

			case 'string':
				return (string) $value;

			case 'bool':
			case 'boolean':
				return (bool) $value;

			case 'array':
			case 'json':
				if ( is_array( $value ) ) {
					return $value;
				}
				$decoded = json_decode( $value, true );
				return is_array( $decoded ) ? $decoded : array();

			case 'datetime':
				return $value;

			default:
				return $value;
		}
	}

	/**
	 * Serialize an attribute for storage.
	 *
	 * @param string $key   Attribute name.
	 * @param mixed  $value Value to serialize.
	 * @return mixed Serialized value.
	 */
	protected function serialize_attribute( string $key, $value ) {
		if ( ! isset( $this->casts[ $key ] ) ) {
			return $value;
		}

		switch ( $this->casts[ $key ] ) {
			case 'array':
			case 'json':
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- internal serialization.
				return is_array( $value ) ? json_encode( $value ) : $value;

			case 'bool':
			case 'boolean':
				return $value ? 1 : 0;

			default:
				return $value;
		}
	}

	// ---------------------------------------------------------------
	// Persistence
	// ---------------------------------------------------------------

	/**
	 * Save the model (insert or update).
	 *
	 * @return bool
	 */
	public function save(): bool {
		if ( $this->exists ) {
			return $this->perform_update();
		}

		return $this->perform_insert();
	}

	/**
	 * Insert a new row.
	 *
	 * @return bool
	 */
	protected function perform_insert(): bool {
		if ( static::$timestamps ) {
			$now                            = current_time( 'mysql' );
			$this->attributes['created_at'] = $now;
			$this->attributes['updated_at'] = $now;
		}

		$data = $this->get_insertable_attributes();

		$insert_id = static::query()->insert( $data );

		if ( false === $insert_id ) {
			return false;
		}

		$this->attributes[ static::$primary_key ] = $insert_id;
		$this->exists                             = true;
		$this->sync_original();

		return true;
	}

	/**
	 * Update an existing row.
	 *
	 * @return bool
	 */
	protected function perform_update(): bool {
		$dirty = $this->get_dirty();

		if ( empty( $dirty ) ) {
			return true;
		}

		if ( static::$timestamps ) {
			$dirty['updated_at']            = current_time( 'mysql' );
			$this->attributes['updated_at'] = $dirty['updated_at'];
		}

		$data = array();
		foreach ( $dirty as $key => $value ) {
			$data[ $key ] = $this->serialize_attribute( $key, $value );
		}

		$pk_value = $this->attributes[ static::$primary_key ];

		$result = static::query()
			->where( static::$primary_key, '=', $pk_value )
			->update( $data );

		if ( false === $result ) {
			return false;
		}

		$this->sync_original();

		return true;
	}

	/**
	 * Delete this model from the database.
	 *
	 * @return bool
	 */
	public function delete(): bool {
		if ( ! $this->exists ) {
			return false;
		}

		$pk_value = $this->attributes[ static::$primary_key ];

		$result = static::query()
			->where( static::$primary_key, '=', $pk_value )
			->delete();

		if ( false !== $result && $result > 0 ) {
			$this->exists = false;
			return true;
		}

		return false;
	}

	/**
	 * Reload the model from the database.
	 *
	 * @return $this
	 */
	public function fresh(): self {
		if ( ! $this->exists ) {
			return $this;
		}

		$pk_value = $this->attributes[ static::$primary_key ];
		$row      = static::query()->find( $pk_value, static::$primary_key );

		if ( null !== $row ) {
			$this->attributes = $row;
			$this->sync_original();
		}

		return $this;
	}

	/**
	 * Get attributes ready for insert (serialized, without PK).
	 *
	 * @return array
	 */
	protected function get_insertable_attributes(): array {
		$data = array();

		foreach ( $this->attributes as $key => $value ) {
			if ( $key === static::$primary_key ) {
				continue;
			}
			$data[ $key ] = $this->serialize_attribute( $key, $value );
		}

		return $data;
	}

	/**
	 * Sync original attributes with current.
	 *
	 * @return void
	 */
	protected function sync_original(): void {
		$this->original = $this->attributes;
	}

	/**
	 * Check if this model exists in the database.
	 *
	 * @return bool
	 */
	public function exists(): bool {
		return $this->exists;
	}

	// ---------------------------------------------------------------
	// Relations
	// ---------------------------------------------------------------

	/**
	 * Define a has-one relationship.
	 *
	 * @param string $related     Related model class.
	 * @param string $foreign_key Foreign key on related table.
	 * @param string $local_key   Local key on this table.
	 * @return HasOne
	 */
	public function has_one( string $related, string $foreign_key = '', string $local_key = '' ): HasOne {
		if ( '' === $foreign_key ) {
			$foreign_key = $this->get_foreign_key_name();
		}
		if ( '' === $local_key ) {
			$local_key = static::$primary_key;
		}

		return new HasOne( $this, $related, $foreign_key, $local_key );
	}

	/**
	 * Define a has-many relationship.
	 *
	 * @param string $related     Related model class.
	 * @param string $foreign_key Foreign key on related table.
	 * @param string $local_key   Local key on this table.
	 * @return HasMany
	 */
	public function has_many( string $related, string $foreign_key = '', string $local_key = '' ): HasMany {
		if ( '' === $foreign_key ) {
			$foreign_key = $this->get_foreign_key_name();
		}
		if ( '' === $local_key ) {
			$local_key = static::$primary_key;
		}

		return new HasMany( $this, $related, $foreign_key, $local_key );
	}

	/**
	 * Define a belongs-to relationship.
	 *
	 * @param string $related     Related (parent) model class.
	 * @param string $foreign_key Foreign key on this table.
	 * @param string $local_key   Primary key on the related table.
	 * @return BelongsTo
	 */
	public function belongs_to( string $related, string $foreign_key = '', string $local_key = '' ): BelongsTo {
		if ( '' === $foreign_key ) {
			$short_name  = ( new \ReflectionClass( $related ) )->getShortName();
			$foreign_key = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1_$2', $short_name ) ) . '_id';
		}
		if ( '' === $local_key ) {
			$local_key = $related::get_primary_key();
		}

		return new BelongsTo( $this, $related, $foreign_key, $local_key );
	}

	/**
	 * Infer foreign key name from this model's class.
	 *
	 * @return string E.g. 'user_id' for class User.
	 */
	protected function get_foreign_key_name(): string {
		$class = ( new \ReflectionClass( static::class ) )->getShortName();
		return strtolower( preg_replace( '/([a-z])([A-Z])/', '$1_$2', $class ) ) . '_id';
	}

	/**
	 * Get a loaded relation.
	 *
	 * @param string $name Relation name.
	 * @return mixed
	 */
	public function get_relation( string $name ) {
		return $this->relations[ $name ] ?? null;
	}

	/**
	 * Set a loaded relation.
	 *
	 * @param string $name  Relation name.
	 * @param mixed  $value Loaded models/model.
	 * @return $this
	 */
	public function set_relation( string $name, $value ): self {
		$this->relations[ $name ] = $value;
		return $this;
	}

	/**
	 * Check if a relation is loaded.
	 *
	 * @param string $name Relation name.
	 * @return bool
	 */
	public function relation_loaded( string $name ): bool {
		return array_key_exists( $name, $this->relations );
	}

	// ---------------------------------------------------------------
	// Serialization
	// ---------------------------------------------------------------

	/**
	 * Convert the model to an array (respecting $hidden).
	 *
	 * @return array
	 */
	public function to_array(): array {
		$attributes = array();

		foreach ( $this->attributes as $key => $value ) {
			if ( in_array( $key, $this->hidden, true ) ) {
				continue;
			}
			$attributes[ $key ] = $this->cast_attribute( $key, $value );
		}

		foreach ( $this->relations as $key => $relation ) {
			if ( in_array( $key, $this->hidden, true ) ) {
				continue;
			}
			if ( is_array( $relation ) ) {
				$attributes[ $key ] = array_map(
					function ( Model $m ): array {
						return $m->to_array();
					},
					$relation
				);
			} elseif ( $relation instanceof Model ) {
				$attributes[ $key ] = $relation->to_array();
			} else {
				$attributes[ $key ] = $relation;
			}
		}

		return $attributes;
	}

	/**
	 * Convert the model to JSON.
	 *
	 * @param int $options JSON encode options.
	 * @return string
	 */
	public function to_json( int $options = 0 ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- internal serialization.
		return json_encode( $this->to_array(), $options );
	}

	// ---------------------------------------------------------------
	// Hydration
	// ---------------------------------------------------------------

	/**
	 * Create a model instance from a database row.
	 *
	 * @param array $row Associative array from DB.
	 * @return static
	 */
	public static function hydrate_one( array $row ) {
		$model             = new static();
		$model->attributes = $row;
		$model->exists     = true;
		$model->sync_original();

		return $model;
	}

	/**
	 * Create model instances from multiple database rows.
	 *
	 * @param array $rows Array of associative arrays.
	 * @return array Array of static instances.
	 */
	public static function hydrate_many( array $rows ): array {
		$models = array();

		foreach ( $rows as $row ) {
			$models[] = static::hydrate_one( $row );
		}

		return $models;
	}

	/**
	 * Eager load relations on a collection of models.
	 *
	 * @param array $models    Array of Model instances.
	 * @param array $relations Relation names to load.
	 * @return array
	 */
	public static function eager_load_relations( array $models, array $relations ): array {
		if ( empty( $models ) ) {
			return $models;
		}

		$instance = $models[0];

		foreach ( $relations as $relation_name ) {
			if ( method_exists( $instance, $relation_name ) ) {
				$relation = $instance->{$relation_name}();
				if ( $relation instanceof Relation ) {
					$models = $relation->eager_load( $models, $relation_name );
				}
			}
		}

		return $models;
	}
}
