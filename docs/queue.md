# Job Queue

WPFlint ships a DB-backed job queue that persists jobs in `{prefix}wpflint_jobs`, dispatches them asynchronously via WordPress cron (`wp_schedule_single_event`), and handles retries with exponential back-off.

## Setup

### 1. Run the migrations

Create the queue tables by running the bundled migration:

```bash
wp wpflint migrate
```

This creates `{prefix}wpflint_jobs` and `{prefix}wpflint_failed_jobs`.

### 2. Register the provider

```php
use WPFlint\Queue\QueueServiceProvider;

$app->register( QueueServiceProvider::class );
```

This binds `'queue'` (`QueueManager`) and `'queue.worker'` (`QueueWorker`) in the container, and hooks `wpflint_process_queue` into WP-Cron so jobs are processed asynchronously.

## Defining a job

Extend `Job` and implement `handle()`:

```php
use WPFlint\Queue\Job;

class SendWelcomeEmail extends Job {

    public function __construct( private int $user_id ) {}

    public function handle(): void {
        $user = get_userdata( $this->user_id );
        wp_mail( $user->user_email, 'Welcome!', 'Thanks for signing up.' );
    }

    // Optional: called when all retries are exhausted.
    public function failed( \Throwable $exception ): void {
        // Alert, log, compensate...
    }
}
```

### Job configuration

Override the protected properties or use the fluent setters:

```php
class SlowJob extends Job {
    protected string $queue        = 'slow';   // queue name
    protected int    $max_attempts = 5;         // retries before failed
    protected int    $delay_seconds = 0;        // seconds before available
    ...
}
```

## Dispatching

```php
// Via container
$app->make( 'queue' )->dispatch( new SendWelcomeEmail( $user_id ) );

// Via global helper
wpflint_dispatch( new SendWelcomeEmail( $user_id ) );

// Fluent — override config at dispatch time
wpflint_dispatch(
    ( new SendWelcomeEmail( $user_id ) )
        ->on_queue( 'emails' )
        ->delay( 30 )   // available in 30 seconds
        ->tries( 5 )
);
```

## Processing

Jobs are processed automatically in the background via WP-Cron when the site receives traffic after a dispatch. For faster processing (AJAX-heavy sites), you can process manually:

```php
$worker = $app->make( 'queue.worker' );

$worker->process( 'default' );          // process one job
$worker->process_all( 'default' );      // drain the queue
$worker->process_all( 'emails', 10 );   // up to 10 jobs from 'emails'
```

### WP-CLI integration

Process queues from the command line:

```bash
wp eval 'wpflint_app("queue.worker")->process_all();'
wp eval 'wpflint_app("queue.worker")->process_all("emails", 50);'
```

## Retries and back-off

When `handle()` throws, the job is retried automatically:

- Attempt 1 fails → retry after **2s**
- Attempt 2 fails → retry after **4s**
- Attempt 3 fails → retry after **8s** (exponential: `2^attempt`)
- After `$max_attempts` → job moves to `failed_jobs`, `failed()` is called

The default is 3 attempts. Change it per job:

```php
protected int $max_attempts = 5;
// or fluently:
( new MyJob() )->tries( 5 );
```

## Stale reservation recovery

If a worker process dies mid-job, the reservation times out after **90 seconds** (`QueueManager::RESERVATION_TIMEOUT`). The next `reserve()` call automatically releases stale reservations back to the queue so they can be retried.

## Failed jobs

Failed jobs are stored in `{prefix}wpflint_failed_jobs` with the exception message and stack trace. You can:

```php
$queue = $app->make( 'queue' );

// View all failed jobs
$failed = $queue->get_failed();

// View for a specific queue
$failed = $queue->get_failed( 'emails' );

// Retry a failed job (re-queues with attempts = 0)
$queue->retry( $failed_job_id );
```

## Maintenance

```php
$queue = $app->make( 'queue' );

$queue->pending_count( 'default' );  // jobs available now
$queue->total_count( 'default' );    // including reserved + delayed
$queue->clear( 'default' );          // delete all jobs from a queue
$queue->clear( '*' );                // clear all queues
```

## Multiple queues

Use named queues to separate jobs by priority or type:

```php
class UrgentNotification extends Job {
    protected string $queue = 'urgent';
}

class BackgroundReport extends Job {
    protected string $queue = 'slow';
}

// Process urgent first, then slow
$worker->process_all( 'urgent' );
$worker->process_all( 'slow', 5 );
```

## Custom queue prefix (multi-plugin)

```php
if ( ! function_exists( 'zplane_dispatch' ) ) {
    function zplane_dispatch( \WPFlint\Queue\Job $job ): int {
        return \WPFlint\Support\dispatch_job( $job );
    }
}
```

Or generate with:

```bash
wp wpflint make:helper --prefix=zplane
```
