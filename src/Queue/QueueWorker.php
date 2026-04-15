<?php
/**
 * Processes jobs from the queue.
 *
 * @package WPFlint\Queue
 */

declare(strict_types=1);

namespace WPFlint\Queue;

use WPFlint\Logging\LoggerInterface;
use WPFlint\Logging\NullLogger;

/**
 * Fetches and executes jobs from a queue, handling retries and failures.
 *
 * Usage:
 *
 *     $worker = new QueueWorker( $queue_manager, $logger );
 *     $worker->process( 'default' );       // process one job
 *     $worker->process_all( 'default' );   // drain the queue
 */
class QueueWorker {

	/**
	 * Queue manager instance.
	 *
	 * @var QueueManager
	 */
	protected QueueManager $manager;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Create a QueueWorker.
	 *
	 * @param QueueManager         $manager Queue manager.
	 * @param LoggerInterface|null $logger  Logger (NullLogger used if omitted).
	 */
	public function __construct( QueueManager $manager, ?LoggerInterface $logger = null ) {
		$this->manager = $manager;
		$this->logger  = $logger ?? new NullLogger();
	}

	/**
	 * Process the next available job from the queue.
	 *
	 * Returns true if a job was processed (successfully or failed),
	 * false when the queue is empty.
	 *
	 * @param string $queue Queue name.
	 * @return bool
	 */
	public function process( string $queue = 'default' ): bool {
		$record = $this->manager->reserve( $queue );

		if ( null === $record ) {
			return false;
		}

		$this->run( $record );
		return true;
	}

	/**
	 * Process all available jobs in the queue.
	 *
	 * Stops when no more jobs are available. Limit prevents infinite loops
	 * if jobs keep re-queuing themselves.
	 *
	 * @param string $queue Queue name.
	 * @param int    $limit Maximum number of jobs to process (0 = unlimited).
	 * @return int Number of jobs processed.
	 */
	public function process_all( string $queue = 'default', int $limit = 0 ): int {
		$processed = 0;

		while ( $this->process( $queue ) ) {
			++$processed;
			if ( $limit > 0 && $processed >= $limit ) {
				break;
			}
		}

		return $processed;
	}

	// ---------------------------------------------------------------
	// Internals
	// ---------------------------------------------------------------

	/**
	 * Run a single job record: execute handle(), then delete or retry/fail.
	 *
	 * @param JobRecord $record Reserved job record.
	 * @return void
	 */
	protected function run( JobRecord $record ): void {
		try {
			$job = $record->unserialize_job();
			$job->set_attempts( $record->attempts );

			$this->logger->debug(
				'Processing job {id} ({class}) attempt {attempt}/{max}.',
				array(
					'id'      => $record->id,
					'class'   => get_class( $job ),
					'attempt' => $record->attempts,
					'max'     => $job->get_max_attempts(),
				)
			);

			$job->handle();

			$this->manager->delete( $record );

			$this->logger->info(
				'Job {id} ({class}) completed successfully.',
				array(
					'id'    => $record->id,
					'class' => get_class( $job ),
				)
			);
		} catch ( \Throwable $e ) {
			$this->handle_failure( $record, $e );
		}
	}

	/**
	 * Handle a failed job attempt: retry or mark as permanently failed.
	 *
	 * @param JobRecord  $record    The job record that failed.
	 * @param \Throwable $exception The exception thrown by handle().
	 * @return void
	 */
	protected function handle_failure( JobRecord $record, \Throwable $exception ): void {
		$this->logger->warning(
			'Job {id} failed (attempt {attempt}): {message}',
			array(
				'id'        => $record->id,
				'attempt'   => $record->attempts,
				'message'   => $exception->getMessage(),
				'exception' => $exception,
			)
		);

		try {
			$job = $record->unserialize_job();
			$max = $job->get_max_attempts();
		} catch ( \Throwable $unserialize_exception ) {
			// Cannot even unserialize — move to failed table immediately.
			$this->manager->fail( $record, $exception );
			return;
		}

		if ( $record->attempts >= $max ) {
			// All attempts exhausted — call failed() hook and move to failed table.
			$this->logger->error(
				'Job {id} ({class}) permanently failed after {attempts} attempt(s).',
				array(
					'id'        => $record->id,
					'class'     => get_class( $job ),
					'attempts'  => $record->attempts,
					'exception' => $exception,
				)
			);

			try {
				$job->failed( $exception );
			} catch ( \Throwable $hook_exception ) {
				// failed() itself threw — log but continue moving to failed table.
				$this->logger->error(
					'Job::failed() hook threw for job {id}: {message}',
					array(
						'id'      => $record->id,
						'message' => $hook_exception->getMessage(),
					)
				);
			}

			$this->manager->fail( $record, $exception );
		} else {
			// Retry with exponential back-off: 2^attempt seconds.
			$delay = (int) pow( 2, $record->attempts );
			$this->manager->release( $record, $delay );

			$this->logger->notice(
				'Job {id} released for retry in {delay}s.',
				array(
					'id'    => $record->id,
					'delay' => $delay,
				)
			);
		}
	}
}
