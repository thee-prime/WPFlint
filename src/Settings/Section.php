<?php
/**
 * A settings section containing one or more fields.
 *
 * @package WPFlint\Settings
 */

declare(strict_types=1);

namespace WPFlint\Settings;

/**
 * Represents one settings section registered via add_settings_section().
 *
 * @internal Created via Settings::section(), not directly.
 */
class Section {

	/**
	 * Section ID.
	 *
	 * @var string
	 */
	protected string $id;

	/**
	 * Human-readable section heading.
	 *
	 * @var string
	 */
	protected string $title;

	/**
	 * Optional description rendered below the heading.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Fields belonging to this section.
	 *
	 * @var array<int, Field>
	 */
	protected array $fields = array();

	/**
	 * Create a Section.
	 *
	 * @param string $id    Section ID.
	 * @param string $title Section heading.
	 */
	public function __construct( string $id, string $title ) {
		$this->id    = $id;
		$this->title = $title;
	}

	// ---------------------------------------------------------------
	// Fluent setters
	// ---------------------------------------------------------------

	/**
	 * Set the optional description shown below the section heading.
	 *
	 * @param string $description Description text.
	 * @return $this
	 */
	public function description( string $description ): self {
		$this->description = $description;
		return $this;
	}

	/**
	 * Add a field to this section.
	 *
	 * @param string $id    Field ID.
	 * @param string $label Field label.
	 * @return Field The new field builder (chain further setters on the field).
	 */
	public function field( string $id, string $label ): Field {
		$field          = new Field( $id, $label );
		$this->fields[] = $field;
		return $field;
	}

	// ---------------------------------------------------------------
	// Getters
	// ---------------------------------------------------------------

	/**
	 * Get the section ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Get the section heading.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Get the section description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Get all fields registered in this section.
	 *
	 * @return array<int, Field>
	 */
	public function get_fields(): array {
		return $this->fields;
	}
}
