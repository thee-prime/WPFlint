<?php
/**
 * Abstract base class for all queue jobs.
 *
 * @package WPFlint\Queue
 */

declare(strict_types=1);

namespace WPFlint\Queue;

/**
 * Base job class.
 *
 * Subclass and implement handle() to define the job's work.
 * Override $queue, $max_attempts, and $delay_seconds as needed.
 *
 * Usage:
 *
 *     class SendWelcomeEmail extends Job {
 *         public function __construct( private int $user_id ) {}
 *
 *         public function handle(): void {
 *             wp_mail( get_userdata( $this->user_id )->user_email, 'Welcome!', '...' );
 *         }
 *     }
 *
 *     // Dispatch:
 *     Queue::dispatch( new SendWelcomeEmail( $user_id ) );
 *     Queue::dispatch( new SendWelcomeEmail( $user_id ) )->delay( 60 )->on( 'emails' );
 */
abstract class Job {

	/**
	 * Queue name to push this job onto.
	 *
	 * @var string
	 */
	protected string $queue = 'default';

	/**
	 * Maximum number of attempts before the job is marked as failed.
	 *
	 * @var int
	 */
	protected int $max_attempts = 3;

	/**
	 * Seconds to wait before the job becomes available.
	 *
	 * @var int
	 */
	protected int $delay_seconds = 0;

	/**
	 * Current attempt number (set by QueueWorker).
	 *
	 * @var int
	 */
	protected int $attempts = 0;

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	abstract public function handle(): void;

	/**
	 * Called when all attempts have been exhausted.
	 *
	 * Override to send alerts, log failures, or compensate.
	 *
	 * @param \Throwable $exception The last exception thrown by handle().
	 * @return void
	 */
	public function failed( \Throwable $exception ): void {}

	// ---------------------------------------------------------------
	// Fluent configuration — returns a PendingDispatch
	// ---------------------------------------------------------------

	/**
	 * Set the queue name.
	 *
	 * @param string $queue Queue name.
	 * @return $this
	 */
	public function on_queue( string $queue ): self {
		$this->queue = $queue;
		return $this;
	}

	/**
	 * Set the delay in seconds before the job becomes available.
	 *
	 * @param int $seconds Delay in seconds.
	 * @return $this
	 */
	public function delay( int $seconds ): self {
		$this->delay_seconds = $seconds;
		return $this;
	}

	/**
	 * Set the maximum number of attempts.
	 *
	 * @param int $attempts Max attempts.
	 * @return $this
	 */
	public function tries( int $attempts ): self {
		$this->max_attempts = $attempts;
		return $this;
	}

	// ---------------------------------------------------------------
	// Accessors
	// ---------------------------------------------------------------

	/**
	 * Get the queue name.
	 *
	 * @return string
	 */
	public function get_queue(): string {
		return $this->queue;
	}

	/**
	 * Get the max attempts.
	 *
	 * @return int
	 */
	public function get_max_attempts(): int {
		return $this->max_attempts;
	}

	/**
	 * Get the delay in seconds.
	 *
	 * @return int
	 */
	public function get_delay(): int {
		return $this->delay_seconds;
	}

	/**
	 * Get the current attempt number.
	 *
	 * @return int
	 */
	public function get_attempts(): int {
		return $this->attempts;
	}

	/**
	 * Set the current attempt number (called by QueueWorker).
	 *
	 * @param int $attempts Attempt number.
	 * @return void
	 */
	public function set_attempts( int $attempts ): void {
		$this->attempts = $attempts;
	}
}
