<?php
/**
 * Belongs-to relationship.
 *
 * @package WPFlint\Database\ORM
 */

declare(strict_types=1);

namespace WPFlint\Database\ORM;

/**
 * Defines a many-to-one (inverse) relationship where the foreign key is on the parent table.
 *
 * Example: Order belongs_to User (orders.user_id references users.id).
 */
class BelongsTo extends Relation {

	/**
	 * Get the result for a single child.
	 *
	 * @return Model|null
	 */
	public function get_results() {
		$foreign_value = $this->parent->get_attribute( $this->foreign_key );
		$related_class = $this->related;

		if ( null === $foreign_value ) {
			return null;
		}

		return $related_class::where( $this->local_key, $foreign_value )->first_model();
	}

	/**
	 * Eager load the relationship for a collection of models.
	 *
	 * Uses WHERE IN batching for efficient loading.
	 *
	 * @param array  $models   Array of child Model instances.
	 * @param string $relation Relation name.
	 * @return array The models with the relation loaded.
	 */
	public function eager_load( array $models, string $relation ): array {
		$keys = array();
		foreach ( $models as $model ) {
			$key = $model->get_attribute( $this->foreign_key );
			if ( null !== $key ) {
				$keys[] = $key;
			}
		}

		if ( empty( $keys ) ) {
			return $models;
		}

		$related_class = $this->related;
		$results       = $related_class::where_in( $this->local_key, array_unique( $keys ) )->get_models();

		$dictionary = array();
		foreach ( $results as $result ) {
			$pk_value                = $result->get_attribute( $this->local_key );
			$dictionary[ $pk_value ] = $result;
		}

		foreach ( $models as $model ) {
			$fk_value = $model->get_attribute( $this->foreign_key );
			$value    = $dictionary[ $fk_value ] ?? null;
			$model->set_relation( $relation, $value );
		}

		return $models;
	}
}
