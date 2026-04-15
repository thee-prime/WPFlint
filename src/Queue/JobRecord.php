<?php
/**
 * Value object representing a row in the jobs table.
 *
 * @package WPFlint\Queue
 */

declare(strict_types=1);

namespace WPFlint\Queue;

/**
 * Immutable snapshot of a queued job row.
 *
 * Used by QueueManager and QueueWorker to pass job metadata
 * without raw DB array access scattered across the codebase.
 */
class JobRecord {

	/**
	 * Row primary key.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Queue name.
	 *
	 * @var string
	 */
	public string $queue;

	/**
	 * Serialized job payload.
	 *
	 * @var string
	 */
	public string $payload;

	/**
	 * Number of times this job has been attempted.
	 *
	 * @var int
	 */
	public int $attempts;

	/**
	 * Unix timestamp when this job becomes available (0 = immediately).
	 *
	 * @var int
	 */
	public int $available_at;

	/**
	 * Unix timestamp when this job was created.
	 *
	 * @var int
	 */
	public int $created_at;

	/**
	 * Unix timestamp when this job was reserved (0 = not reserved).
	 *
	 * @var int
	 */
	public int $reserved_at;

	/**
	 * Create a JobRecord from a raw DB row array.
	 *
	 * @param array<string, mixed> $row DB row associative array.
	 */
	public function __construct( array $row ) {
		$this->id           = (int) $row['id'];
		$this->queue        = (string) $row['queue'];
		$this->payload      = (string) $row['payload'];
		$this->attempts     = (int) $row['attempts'];
		$this->available_at = (int) $row['available_at'];
		$this->created_at   = (int) $row['created_at'];
		$this->reserved_at  = (int) ( $row['reserved_at'] ?? 0 );
	}

	/**
	 * Unserialize and return the Job instance.
	 *
	 * @return Job
	 * @throws \InvalidArgumentException When the payload cannot be unserialized.
	 */
	public function unserialize_job(): Job {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- jobs are serialized by this framework only.
		$job = unserialize( $this->payload );

		if ( ! $job instanceof Job ) {
			$msg = sprintf( 'Job record %d does not contain a valid Job instance.', $this->id );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
			throw new \InvalidArgumentException( $msg );
		}

		return $job;
	}
}
