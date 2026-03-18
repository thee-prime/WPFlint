<?php
/**
 * Raw SQL expression wrapper.
 *
 * @package WPFlint\Database\ORM
 */

declare(strict_types=1);

namespace WPFlint\Database\ORM;

/**
 * Wraps a raw SQL fragment so the query builder inserts it verbatim
 * instead of using a placeholder.
 *
 * Only for developer-controlled expressions (e.g. column arithmetic).
 */
class RawExpression {

	/**
	 * The raw SQL expression.
	 *
	 * @var string
	 */
	private string $expression;

	/**
	 * Constructor.
	 *
	 * @param string $expression Raw SQL fragment.
	 */
	public function __construct( string $expression ) {
		$this->expression = $expression;
	}

	/**
	 * Get the raw expression.
	 *
	 * @return string
	 */
	public function get_expression(): string {
		return $this->expression;
	}

	/**
	 * String representation.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->expression;
	}
}
