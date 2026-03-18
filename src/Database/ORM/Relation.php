<?php
/**
 * Abstract base relation.
 *
 * @package WPFlint\Database\ORM
 */

declare(strict_types=1);

namespace WPFlint\Database\ORM;

/**
 * Base class for model relationships.
 *
 * Relationships resolve related models via the QueryBuilder and support
 * eager loading with WHERE IN batching.
 */
abstract class Relation {

	/**
	 * The related model class name.
	 *
	 * @var string
	 */
	protected string $related;

	/**
	 * The foreign key column.
	 *
	 * @var string
	 */
	protected string $foreign_key;

	/**
	 * The local key column.
	 *
	 * @var string
	 */
	protected string $local_key;

	/**
	 * The parent model instance.
	 *
	 * @var Model
	 */
	protected Model $parent;

	/**
	 * Constructor.
	 *
	 * @param Model  $parent      Parent model instance.
	 * @param string $related     Related model class name.
	 * @param string $foreign_key Foreign key column on the related table.
	 * @param string $local_key   Local key column on the parent table.
	 */
	public function __construct( Model $parent, string $related, string $foreign_key, string $local_key ) {
		$this->parent      = $parent;
		$this->related     = $related;
		$this->foreign_key = $foreign_key;
		$this->local_key   = $local_key;
	}

	/**
	 * Get the results of the relationship for a single parent.
	 *
	 * @return mixed
	 */
	abstract public function get_results();

	/**
	 * Eager load the relationship for a collection of models.
	 *
	 * @param array  $models     Array of parent Model instances.
	 * @param string $relation   Relation name.
	 * @return array The models with the relation loaded.
	 */
	abstract public function eager_load( array $models, string $relation ): array;

	/**
	 * Create a new query builder for the related model.
	 *
	 * @return QueryBuilder
	 */
	protected function new_query(): QueryBuilder {
		$related_class = $this->related;
		return $related_class::query();
	}

	/**
	 * Get the related model class name.
	 *
	 * @return string
	 */
	public function get_related(): string {
		return $this->related;
	}

	/**
	 * Get the foreign key column.
	 *
	 * @return string
	 */
	public function get_foreign_key(): string {
		return $this->foreign_key;
	}

	/**
	 * Get the local key column.
	 *
	 * @return string
	 */
	public function get_local_key(): string {
		return $this->local_key;
	}
}
