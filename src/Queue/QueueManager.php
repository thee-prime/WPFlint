<?php
/**
 * Manages job dispatching and retrieval from the DB-backed queue.
 *
 * @package WPFlint\Queue
 */

declare(strict_types=1);

namespace WPFlint\Queue;

/**
 * DB-backed queue manager.
 *
 * Stores jobs in {prefix}jobs, retrieves them FIFO per queue name,
 * reserves jobs to prevent double-processing, and schedules async
 * processing via wp_schedule_single_event().
 *
 * Table schema (created by QueueTableMigration):
 *
 *   id           BIGINT UNSIGNED AUTO_INCREMENT PK
 *   queue        VARCHAR(255) NOT NULL DEFAULT 'default'
 *   payload      LONGTEXT NOT NULL           -- serialized Job object
 *   attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0
 *   available_at INT UNSIGNED NOT NULL       -- Unix timestamp
 *   reserved_at  INT UNSIGNED DEFAULT NULL   -- set when worker picks up
 *   created_at   INT UNSIGNED NOT NULL
 *
 * Failed jobs are stored in {prefix}failed_jobs.
 */
class QueueManager {

	/**
	 * WordPress $wpdb instance.
	 *
	 * @var \wpdb
	 */
	protected \wpdb $db;

	/**
	 * Jobs table name (with prefix).
	 *
	 * @var string
	 */
	protected string $jobs_table;

	/**
	 * Failed jobs table name (with prefix).
	 *
	 * @var string
	 */
	protected string $failed_table;

	/**
	 * WP action hook used to trigger async processing.
	 *
	 * @var string
	 */
	const PROCESS_HOOK = 'wpflint_process_queue';

	/**
	 * Seconds before a reserved job is considered stale (re-queued).
	 *
	 * @var int
	 */
	const RESERVATION_TIMEOUT = 90;

	/**
	 * Create a new QueueManager.
	 *
	 * @param \wpdb $db WordPress database instance.
	 */
	public function __construct( \wpdb $db ) {
		$this->db           = $db;
		$this->jobs_table   = $db->prefix . 'wpflint_jobs';
		$this->failed_table = $db->prefix . 'wpflint_failed_jobs';
	}

	// ---------------------------------------------------------------
	// Dispatching
	// ---------------------------------------------------------------

	/**
	 * Push a job onto the queue.
	 *
	 * Serializes the job, inserts a row, then schedules an async WP-Cron
	 * event to process the queue (fires once, immediately).
	 *
	 * @param Job $job The job to dispatch.
	 * @return int Inserted row ID.
	 */
	public function dispatch( Job $job ): int {
		$now          = time();
		$available_at = $now + $job->get_delay();

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- payload serialization; controlled input.
		$payload = serialize( $job );

		$this->db->insert(
			$this->jobs_table,
			array(
				'queue'        => $job->get_queue(),
				'payload'      => $payload,
				'attempts'     => 0,
				'available_at' => $available_at,
				'reserved_at'  => null,
				'created_at'   => $now,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%d' )
		);

		$id = (int) $this->db->insert_id;

		// Fire an async cron event to process this queue (best-effort).
		if ( $job->get_delay() > 0 ) {
			wp_schedule_single_event( $available_at, static::PROCESS_HOOK, array( $job->get_queue() ) );
		} else {
			wp_schedule_single_event( $now, static::PROCESS_HOOK, array( $job->get_queue() ) );
		}

		return $id;
	}

	// ---------------------------------------------------------------
	// Retrieval
	// ---------------------------------------------------------------

	/**
	 * Reserve the next available job from a queue.
	 *
	 * Marks the row as reserved (sets reserved_at) atomically and increments
	 * the attempt counter. Returns null when the queue is empty or all jobs
	 * are reserved / not yet available.
	 *
	 * Also re-queues stale reserved jobs (timed out) before fetching.
	 *
	 * @param string $queue Queue name.
	 * @return JobRecord|null
	 */
	public function reserve( string $queue = 'default' ): ?JobRecord {
		$this->release_stale_reservations( $queue );

		$now = time();

		// Fetch the next available, unreserved job.
		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM `{$this->jobs_table}`
				 WHERE `queue` = %s
				   AND `reserved_at` IS NULL
				   AND `available_at` <= %d
				 ORDER BY `available_at` ASC, `id` ASC
				 LIMIT 1",
				$queue,
				$now
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$new_attempts = (int) $row['attempts'] + 1;

		// Reserve the job.
		$this->db->update(
			$this->jobs_table,
			array(
				'reserved_at' => $now,
				'attempts'    => $new_attempts,
			),
			array( 'id' => (int) $row['id'] ),
			array( '%d', '%d' ),
			array( '%d' )
		);

		$row['reserved_at'] = $now;
		$row['attempts']    = $new_attempts;

		return new JobRecord( $row );
	}

	/**
	 * Get a count of pending (unreserved, available) jobs for a queue.
	 *
	 * @param string $queue Queue name.
	 * @return int
	 */
	public function pending_count( string $queue = 'default' ): int {
		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM `{$this->jobs_table}`
				 WHERE `queue` = %s
				   AND `reserved_at` IS NULL
				   AND `available_at` <= %d",
				$queue,
				time()
			)
		);
	}

	/**
	 * Get a count of all jobs in a queue (including reserved and delayed).
	 *
	 * @param string $queue Queue name.
	 * @return int
	 */
	public function total_count( string $queue = 'default' ): int {
		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM `{$this->jobs_table}` WHERE `queue` = %s",
				$queue
			)
		);
	}

	// ---------------------------------------------------------------
	// Completion / failure
	// ---------------------------------------------------------------

	/**
	 * Delete a job after successful processing.
	 *
	 * @param JobRecord $record Completed job record.
	 * @return void
	 */
	public function delete( JobRecord $record ): void {
		$this->db->delete(
			$this->jobs_table,
			array( 'id' => $record->id ),
			array( '%d' )
		);
	}

	/**
	 * Release a reserved job back to the queue (for retry after failure).
	 *
	 * Clears reserved_at so the job can be picked up again.
	 *
	 * @param JobRecord $record   Job to release.
	 * @param int       $delay    Additional seconds to wait before retry.
	 * @return void
	 */
	public function release( JobRecord $record, int $delay = 0 ): void {
		$available_at = time() + $delay;

		$this->db->update(
			$this->jobs_table,
			array(
				'reserved_at'  => null,
				'available_at' => $available_at,
			),
			array( 'id' => $record->id ),
			array( '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Move a job to the failed_jobs table and delete it from jobs.
	 *
	 * @param JobRecord  $record    Failed job record.
	 * @param \Throwable $exception The exception that caused the failure.
	 * @return void
	 */
	public function fail( JobRecord $record, \Throwable $exception ): void {
		$this->db->insert(
			$this->failed_table,
			array(
				'queue'     => $record->queue,
				'payload'   => $record->payload,
				'exception' => $exception->getMessage() . "\n" . $exception->getTraceAsString(),
				'failed_at' => time(),
			),
			array( '%s', '%s', '%s', '%d' )
		);

		$this->db->delete(
			$this->jobs_table,
			array( 'id' => $record->id ),
			array( '%d' )
		);
	}

	// ---------------------------------------------------------------
	// Maintenance
	// ---------------------------------------------------------------

	/**
	 * Release all stale reservations for a queue (timed out jobs).
	 *
	 * A reservation is stale when reserved_at is older than RESERVATION_TIMEOUT.
	 *
	 * @param string $queue Queue name.
	 * @return void
	 */
	protected function release_stale_reservations( string $queue ): void {
		$cutoff = time() - static::RESERVATION_TIMEOUT;

		$this->db->query(
			$this->db->prepare(
				"UPDATE `{$this->jobs_table}`
				 SET `reserved_at` = NULL
				 WHERE `queue` = %s
				   AND `reserved_at` IS NOT NULL
				   AND `reserved_at` < %d",
				$queue,
				$cutoff
			)
		);
	}

	/**
	 * Clear all jobs from a queue (pending and reserved).
	 *
	 * @param string $queue Queue name. Pass '*' to clear all queues.
	 * @return int Number of rows deleted.
	 */
	public function clear( string $queue = 'default' ): int {
		if ( '*' === $queue ) {
			$this->db->query( "DELETE FROM `{$this->jobs_table}`" );
		} else {
			$this->db->query(
				$this->db->prepare(
					"DELETE FROM `{$this->jobs_table}` WHERE `queue` = %s",
					$queue
				)
			);
		}
		return (int) $this->db->rows_affected;
	}

	/**
	 * Get all failed jobs (optionally filtered by queue).
	 *
	 * @param string|null $queue Queue name filter. Null = all queues.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_failed( ?string $queue = null ): array {
		if ( null !== $queue ) {
			$rows = $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM `{$this->failed_table}` WHERE `queue` = %s ORDER BY `failed_at` DESC",
					$queue
				),
				ARRAY_A
			);
		} else {
			$rows = $this->db->get_results(
				"SELECT * FROM `{$this->failed_table}` ORDER BY `failed_at` DESC",
				ARRAY_A
			);
		}

		return $rows ?? array();
	}

	/**
	 * Retry a failed job by moving it back to the jobs table.
	 *
	 * @param int $failed_id Row ID in failed_jobs.
	 * @return bool True if the job was re-queued.
	 */
	public function retry( int $failed_id ): bool {
		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM `{$this->failed_table}` WHERE `id` = %d",
				$failed_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return false;
		}

		$now = time();

		$this->db->insert(
			$this->jobs_table,
			array(
				'queue'        => $row['queue'],
				'payload'      => $row['payload'],
				'attempts'     => 0,
				'available_at' => $now,
				'reserved_at'  => null,
				'created_at'   => $now,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%d' )
		);

		$this->db->delete(
			$this->failed_table,
			array( 'id' => $failed_id ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Get the jobs table name.
	 *
	 * @return string
	 */
	public function get_jobs_table(): string {
		return $this->jobs_table;
	}

	/**
	 * Get the failed jobs table name.
	 *
	 * @return string
	 */
	public function get_failed_table(): string {
		return $this->failed_table;
	}
}
