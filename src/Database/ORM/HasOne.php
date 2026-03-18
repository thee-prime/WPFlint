<?php
/**
 * Has-one relationship.
 *
 * @package WPFlint\Database\ORM
 */

declare(strict_types=1);

namespace WPFlint\Database\ORM;

/**
 * Defines a one-to-one relationship where the foreign key is on the related table.
 *
 * Example: User has_one Profile (profiles.user_id references users.id).
 */
class HasOne extends Relation {

	/**
	 * Get the result for a single parent.
	 *
	 * @return Model|null
	 */
	public function get_results() {
		$local_value   = $this->parent->get_attribute( $this->local_key );
		$related_class = $this->related;

		return $related_class::where( $this->foreign_key, $local_value )->first_model();
	}

	/**
	 * Eager load the relationship for a collection of models.
	 *
	 * Uses WHERE IN batching for efficient loading.
	 *
	 * @param array  $models   Array of parent Model instances.
	 * @param string $relation Relation name.
	 * @return array The models with the relation loaded.
	 */
	public function eager_load( array $models, string $relation ): array {
		$keys = array();
		foreach ( $models as $model ) {
			$key = $model->get_attribute( $this->local_key );
			if ( null !== $key ) {
				$keys[] = $key;
			}
		}

		if ( empty( $keys ) ) {
			return $models;
		}

		$related_class = $this->related;
		$results       = $related_class::where_in( $this->foreign_key, array_unique( $keys ) )->get_models();

		$dictionary = array();
		foreach ( $results as $result ) {
			$fk_value                = $result->get_attribute( $this->foreign_key );
			$dictionary[ $fk_value ] = $result;
		}

		foreach ( $models as $model ) {
			$key   = $model->get_attribute( $this->local_key );
			$value = $dictionary[ $key ] ?? null;
			$model->set_relation( $relation, $value );
		}

		return $models;
	}
}
