<?php
/**
 * Model-aware query builder.
 *
 * @package WPFlint\Database\ORM
 */

declare(strict_types=1);

namespace WPFlint\Database\ORM;

/**
 * Extends QueryBuilder to return hydrated Model instances.
 */
class ModelQueryBuilder extends QueryBuilder {

	/**
	 * The model class to hydrate.
	 *
	 * @var string
	 */
	protected string $model_class;

	/**
	 * Relations to eager load.
	 *
	 * @var array
	 */
	protected array $eager_loads = array();

	/**
	 * Constructor.
	 *
	 * @param string $table       Fully prefixed table name.
	 * @param string $model_class Model class name.
	 */
	public function __construct( string $table, string $model_class ) {
		parent::__construct( $table );
		$this->model_class = $model_class;
	}

	/**
	 * Get results as hydrated Model instances.
	 *
	 * @return array Array of Model instances.
	 */
	public function get_models(): array {
		$rows   = $this->get();
		$class  = $this->model_class;
		$models = $class::hydrate_many( $rows );

		if ( ! empty( $this->eager_loads ) ) {
			$models = $class::eager_load_relations( $models, $this->eager_loads );
		}

		return $models;
	}

	/**
	 * Get the first result as a Model instance.
	 *
	 * @return Model|null
	 */
	public function first_model() {
		$this->limit_value = 1;
		$models            = $this->get_models();

		return ! empty( $models ) ? $models[0] : null;
	}

	/**
	 * Specify relations to eager load.
	 *
	 * @param array $relations Relation names.
	 * @return $this
	 */
	public function with( array $relations ): self {
		$this->eager_loads = array_merge( $this->eager_loads, $relations );
		return $this;
	}

	/**
	 * Override where to return $this (ModelQueryBuilder) not parent.
	 *
	 * @param string $column   Column name.
	 * @param mixed  $operator Operator or value.
	 * @param mixed  $value    Value.
	 * @return $this
	 */
	public function where( string $column, $operator = null, $value = null ): self {
		parent::where( $column, $operator, $value );
		return $this;
	}

	/**
	 * Override or_where to return $this (ModelQueryBuilder).
	 *
	 * @param string $column   Column name.
	 * @param mixed  $operator Operator or value.
	 * @param mixed  $value    Value.
	 * @return $this
	 */
	public function or_where( string $column, $operator = null, $value = null ): self {
		parent::or_where( $column, $operator, $value );
		return $this;
	}

	/**
	 * Override where_in to return $this (ModelQueryBuilder).
	 *
	 * @param string $column Column name.
	 * @param array  $values Values.
	 * @return $this
	 */
	public function where_in( string $column, array $values ): self {
		parent::where_in( $column, $values );
		return $this;
	}

	/**
	 * Override where_null to return $this.
	 *
	 * @param string $column Column name.
	 * @return $this
	 */
	public function where_null( string $column ): self {
		parent::where_null( $column );
		return $this;
	}

	/**
	 * Override where_not_null to return $this.
	 *
	 * @param string $column Column name.
	 * @return $this
	 */
	public function where_not_null( string $column ): self {
		parent::where_not_null( $column );
		return $this;
	}

	/**
	 * Override order_by to return $this.
	 *
	 * @param string $column    Column name.
	 * @param string $direction Direction.
	 * @return $this
	 */
	public function order_by( string $column, string $direction = 'ASC' ): self {
		parent::order_by( $column, $direction );
		return $this;
	}

	/**
	 * Override limit to return $this.
	 *
	 * @param int $value Limit.
	 * @return $this
	 */
	public function limit( int $value ): self {
		parent::limit( $value );
		return $this;
	}

	/**
	 * Override offset to return $this.
	 *
	 * @param int $value Offset.
	 * @return $this
	 */
	public function offset( int $value ): self {
		parent::offset( $value );
		return $this;
	}

	/**
	 * Override select to return $this.
	 *
	 * @param array $columns Columns.
	 * @return $this
	 */
	public function select( array $columns ): self {
		parent::select( $columns );
		return $this;
	}

	/**
	 * Override latest to return $this.
	 *
	 * @param string $column Column name.
	 * @return $this
	 */
	public function latest( string $column = 'created_at' ): self {
		parent::latest( $column );
		return $this;
	}

	/**
	 * Override oldest to return $this.
	 *
	 * @param string $column Column name.
	 * @return $this
	 */
	public function oldest( string $column = 'created_at' ): self {
		parent::oldest( $column );
		return $this;
	}
}
