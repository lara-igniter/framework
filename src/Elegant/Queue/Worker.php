<?php

namespace Elegant\Queue;

use Elegant\Console\OutputStyle;
use Elegant\Support\Facades\Date;
use Exception;
use Throwable;

class Worker
{
    /**
     * Exit code constants.
     */
    const EXIT_SUCCESS = 0;
    const EXIT_ERROR = 1;
    const EXIT_MEMORY_LIMIT = 12;

    /**
     * The name of the worker.
     *
     * @var string
     */
    protected string $name = 'default';

    /**
     * The queue manager instance.
     *
     * @var \Elegant\Queue\QueueManager
     */
    protected QueueManager $manager;

    /**
     * The cache instance (null for now since we don't have cache).
     *
     * @var mixed
     */
    protected $cache = null;

    /**
     * Indicates if the worker should exit.
     *
     * @var bool
     */
    public bool $shouldQuit = false;

    /**
     * Indicates if the worker is paused.
     *
     * @var bool
     */
    public bool $paused = false;

    /**
     * The callbacks used to pop jobs from queues.
     *
     * @var callable[]
     */
    protected static array $popCallbacks = [];

    /**
     * Create a new queue worker instance.
     *
     * @param \Elegant\Queue\QueueManager|null $manager
     */
    public function __construct(QueueManager $manager = null)
    {
        $this->manager = $manager ?: new QueueManager();
    }

    /**
     * Listen to the given queue in a loop.
     *
     * @param string $connectionName
     * @param string $queue
     * @param \Elegant\Queue\WorkerOptions $options
     * @return int
     */
    public function daemon(string $connectionName, string $queue, WorkerOptions $options): int
    {
        if ($supportsAsyncSignals = $this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        $lastRestart = $this->getTimestampOfLastQueueRestart();

        [$startTime, $jobsProcessed] = [hrtime(true) / 1e9, 0];

        OutputStyle::write(
            OutputStyle::color("  ", 'white') . OutputStyle::color(" INFO ", 'white', 'blue') . " Processing jobs from the [{$queue}] queue.",
            'white'
        );
        OutputStyle::newLine();

        while (true) {
            // Before reserving any jobs, we will make sure this queue is not paused and
            // if it is we will just pause this worker for a given amount of time and
            // make sure we do not need to kill this worker process off completely.
            if (!$this->daemonShouldRun($options, $connectionName, $queue)) {
                $status = $this->pauseWorker($options, $lastRestart);

                if (!is_null($status)) {
                    return $this->stop($status);
                }

                continue;
            }

            // First, we will attempt to get the next job off of the queue. We will also
            // register the timeout handler and reset the alarm for this job so it is
            // not stuck in a frozen state forever. Then, we can fire off this job.
            $job = $this->getNextJob(
                $this->manager->connection($connectionName), $queue
            );

            if ($supportsAsyncSignals) {
                $this->registerTimeoutHandler($job, $options);
            }

            // If the daemon should run (not in maintenance mode, etc.), then we can run
            // fire off this job for processing. Otherwise, we will need to sleep the
            // worker so no more jobs are processed until they should be processed.
            if ($job) {
                $jobsProcessed++;

                $this->runJob($job, $connectionName, $options);

                // Always sleep between jobs, either with rest or sleep value
                if ($options->rest > 0) {
                    $this->sleep($options->rest);
                } else {
                    $this->sleep($options->sleep);
                }
            } else {
                $this->sleep($options->sleep);
            }

            if ($supportsAsyncSignals) {
                $this->resetTimeoutHandler();
            }

            // Finally, we will check to see if we have exceeded our memory limits or if
            // the queue should restart based on other indications. If so, we'll stop
            // this worker and let whatever is "monitoring" it restart the process.
            $status = $this->stopIfNecessary(
                $options, $lastRestart, $startTime, $jobsProcessed, $job
            );

            if (!is_null($status)) {
                return $this->stop($status);
            }
        }
    }

    /**
     * Register the worker timeout handler.
     *
     * @param \Elegant\Queue\Jobs\Job|null $job
     * @param \Elegant\Queue\WorkerOptions $options
     * @return void
     */
    protected function registerTimeoutHandler($job, WorkerOptions $options)
    {
        // We will register a signal handler for the alarm signal so that we can kill this
        // process if it is running too long because it has frozen. This uses the async
        // signals supported in recent versions of PHP to accomplish it conveniently.
        pcntl_signal(SIGALRM, function () use ($job, $options) {
            if ($job) {
                $this->markJobAsFailedIfWillExceedMaxAttempts(
                    $job->getConnectionName(), $job, (int) $options->maxTries, $e = $this->maxAttemptsExceededException($job)
                );

                $this->markJobAsFailedIfItShouldFailOnTimeout(
                    $job->getConnectionName(), $job, $e
                );
            }

            $this->kill(static::EXIT_ERROR);
        });

        pcntl_alarm(
            max($this->timeoutForJob($job, $options), 0)
        );
    }

    /**
     * Reset the worker timeout handler.
     *
     * @return void
     */
    protected function resetTimeoutHandler()
    {
        pcntl_alarm(0);
    }

    /**
     * Get the appropriate timeout for the given job.
     *
     * @param \Elegant\Queue\Jobs\Job|null $job
     * @param \Elegant\Queue\WorkerOptions $options
     * @return int
     */
    protected function timeoutForJob(?Jobs\Job $job, WorkerOptions $options): int
    {
        return $job && !is_null($job->timeout()) ? $job->timeout() : $options->timeout;
    }

    /**
     * Determine if the daemon should process on this iteration.
     *
     * @param \Elegant\Queue\WorkerOptions $options
     * @param string $connectionName
     * @param string $queue
     * @return bool
     */
    protected function daemonShouldRun(WorkerOptions $options, string $connectionName, string $queue): bool
    {
        return !($this->paused || $this->shouldQuit);
    }

    /**
     * Pause the worker for the current loop.
     *
     * @param \Elegant\Queue\WorkerOptions $options
     * @param int $lastRestart
     * @return int|null
     */
    protected function pauseWorker(WorkerOptions $options, int $lastRestart): ?int
    {
        $this->sleep($options->sleep > 0 ? $options->sleep : 1);

        return $this->stopIfNecessary($options, $lastRestart);
    }

    /**
     * Determine the exit code to stop the process if necessary.
     *
     * @param \Elegant\Queue\WorkerOptions $options
     * @param int|null $lastRestart
     * @param int $startTime
     * @param int $jobsProcessed
     * @param mixed $job
     * @return int|null
     */
    protected function stopIfNecessary(WorkerOptions $options, ?int $lastRestart, int $startTime = 0, int $jobsProcessed = 0, $job = null): ?int
    {
        if ($this->shouldQuit) {
            return static::EXIT_SUCCESS;
        } elseif ($this->memoryExceeded($options->memory)) {
            return static::EXIT_MEMORY_LIMIT;
        } elseif ($this->queueShouldRestart($lastRestart)) {
            return static::EXIT_SUCCESS;
        } elseif ($options->stopWhenEmpty && is_null($job)) {
            return static::EXIT_SUCCESS;
        } elseif ($options->maxTime && hrtime(true) / 1e9 - $startTime >= $options->maxTime) {
            return static::EXIT_SUCCESS;
        } elseif ($options->maxJobs && $jobsProcessed >= $options->maxJobs) {
            return static::EXIT_SUCCESS;
        }

        return null;
    }

    /**
     * Process the next job on the queue.
     *
     * @param string $connectionName
     * @param string $queue
     * @param \Elegant\Queue\WorkerOptions $options
     * @return void
     */
    public function runNextJob(string $connectionName, string $queue, WorkerOptions $options)
    {
        $job = $this->getNextJob(
            $this->manager->connection($connectionName), $queue
        );

        // If we're able to pull a job off of the stack, we will process it and then return
        // from this method. If there is no job on the queue, we will "sleep" the worker
        // for the specified number of seconds, then keep processing jobs after sleep.
        if ($job) {
            return $this->runJob($job, $connectionName, $options);
        }

        $this->sleep($options->sleep);
    }

    /**
     * Get the next job from the queue connection.
     *
     * @param \Elegant\Queue\Queue $connection
     * @param string $queue
     * @return \Elegant\Queue\Jobs\Job|null
     */
    protected function getNextJob(Queue $connection, string $queue): ?Jobs\Job
    {
        $popJobCallback = function ($queue) use ($connection) {
            return $connection->pop($queue);
        };

        try {
            if (isset(static::$popCallbacks[$this->name])) {
                return (static::$popCallbacks[$this->name])($popJobCallback, $queue);
            }

            foreach (explode(',', $queue) as $queue) {
                if (!is_null($job = $popJobCallback($queue))) {
                    return $job;
                }
            }
        } catch (Exception|Throwable $e) {
            $this->stopWorkerIfLostConnection($e);
            $this->sleep(1);
        }

        return null;
    }

    /**
     * Process the given job.
     *
     * @param \Elegant\Queue\Jobs\Job $job
     * @param string $connectionName
     * @param \Elegant\Queue\WorkerOptions $options
     * @return void
     */
    protected function runJob(Jobs\Job $job, string $connectionName, WorkerOptions $options)
    {
        try {
            $this->process($connectionName, $job, $options);
        } catch (Throwable $e) {
            $this->stopWorkerIfLostConnection($e);
        }
    }

    /**
     * Stop the worker if we have lost connection to a database.
     *
     * @param \Exception $e
     * @return void
     */
    protected function stopWorkerIfLostConnection($e)
    {
        if ($this->causedByLostConnection($e)) {
            $this->shouldQuit = true;
        }
    }

    /**
     * Process the given job from the queue.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @param \Elegant\Queue\WorkerOptions $options
     * @return void
     *
     * @throws \Throwable
     */
    public function process(string $connectionName, Jobs\Job $job, WorkerOptions $options)
    {
        try {
            // First, we will raise the before job event and determine if the job has already ran
            // over its maximum attempt limits, which could primarily happen if this job is
            // continually timing out and not actually throwing any exceptions from itself.
            $this->raiseBeforeJobEvent($connectionName, $job);

            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
                $connectionName, $job, (int) $options->maxTries
            );

            if ($job->isDeleted()) {
                $this->raiseAfterJobEvent($connectionName, $job);
            }

            // Here we will fire off the job and let it process. We will catch any exceptions so
            // they can be reported to the developers logs, etc. Once the job is finished the
            // proper events will be fired to let any listeners know this job has finished.
            $job->fire();

            if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                $job->delete();
            }

            $this->raiseAfterJobEvent($connectionName, $job);
        } catch (Throwable $e) {
            $this->handleJobException($connectionName, $job, $options, $e);
        }
    }

    /**
     * Handle an exception that occurred while the job was running.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @param \Elegant\Queue\WorkerOptions $options
     * @param \Throwable $e
     * @return void
     *
     * @throws \Throwable
     */
    protected function handleJobException(string $connectionName, Jobs\Job $job, WorkerOptions $options, Throwable $e)
    {
        try {
            // First, we will go ahead and mark the job as failed if it will exceed the maximum
            // attempts it is allowed to run the next time we process it. If so we will just
            // go ahead and mark it as failed now so we do not have to release this again.
            if (!$job->hasFailed()) {
                $this->markJobAsFailedIfWillExceedMaxAttempts(
                    $connectionName, $job, (int) $options->maxTries, $e
                );
            }

            $this->raiseExceptionOccurredJobEvent($connectionName, $job, $e, $options);
        } finally {
            // If we catch an exception, we will attempt to release the job back onto the queue
            // so it is not lost entirely. This'll let the job be retried at a later time by
            // another listener (or this same one). We will re-throw this exception after.
            // BUT ONLY if the job hasn't been marked as failed and hasn't exceeded max attempts
            if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                // Double check if job should be failed before releasing
                $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $options->maxTries;
                $currentAttempts = $job->attempts();

                // If this would be the next attempt and it exceeds maxTries, fail the job instead of releasing
                if ($maxTries > 0 && ($currentAttempts + 1) > $maxTries) {
                    $this->failJob($job, $e);
                } else {
                    $job->release($this->calculateBackoff($job, $options));
                }
            }
        }

        throw $e;
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * This will likely be because the job previously exceeded a timeout.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @param int $maxTries
     * @return void
     *
     * @throws \Throwable
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts(string $connectionName, Jobs\Job $job, int $maxTries)
    {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        $retryUntil = $job->retryUntil();

        if ($retryUntil && Date::now()->getTimestamp() <= $retryUntil) {
            return;
        }

        if (!$retryUntil && ($maxTries === 0 || $job->attempts() <= $maxTries)) {
            return;
        }

        $this->failJob($job, $e = $this->maxAttemptsExceededException($job));

        throw $e;
    }

    /**
     * Mark the given job as failed if it will exceed the maximum allowed attempts.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @param int $maxTries
     * @param \Throwable $e
     * @return void
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts(string $connectionName, Jobs\Job $job, int $maxTries, Throwable $e)
    {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        if ($job->retryUntil() && $job->retryUntil() <= Date::now()->getTimestamp()) {
            $this->failJob($job, $e);
        }

        if (!$job->retryUntil() && $maxTries > 0 && $job->attempts() >= $maxTries) {
            $this->failJob($job, $e);
        }
    }

    /**
     * Mark the given job as failed if it should fail on timeouts.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @param \Throwable $e
     * @return void
     */
    protected function markJobAsFailedIfItShouldFailOnTimeout(string $connectionName, Jobs\Job $job, Throwable $e)
    {
        if (method_exists($job, 'shouldFailOnTimeout') && $job->shouldFailOnTimeout()) {
            $this->failJob($job, $e);
        }
    }

    /**
     * Mark the given job as failed and raise the relevant event.
     *
     * @param \Elegant\Queue\Jobs\Job $job
     * @param \Throwable $e
     * @return void
     */
    protected function failJob(Jobs\Job $job, Throwable $e)
    {
        $job->fail($e);
    }

    /**
     * Calculate the backoff for the given job.
     *
     * @param \Elegant\Queue\Jobs\Job $job
     * @param \Elegant\Queue\WorkerOptions $options
     * @return int
     */
    protected function calculateBackoff(Jobs\Job $job, WorkerOptions $options): int
    {
        $backoff = explode(
            ',',
            method_exists($job, 'backoff') && !is_null($job->backoff())
                ? $job->backoff()
                : $options->backoff
        );

        return (int) ($backoff[$job->attempts() - 1] ?? last($backoff));
    }

    /**
     * Raise the before queue job event.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @return void
     */
    protected function raiseBeforeJobEvent(string $connectionName, Jobs\Job $job)
    {
        $payload = $job->payload();
        $jobName = $payload['job'] ?? 'Unknown Job';

        OutputStyle::write(
            OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Processing: ", 'yellow') . "{$jobName}",
            'white'
        );
    }

    /**
     * Raise the after queue job event.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @return void
     */
    protected function raiseAfterJobEvent(string $connectionName, Jobs\Job $job)
    {
        $payload = $job->payload();
        $jobName = $payload['job'] ?? 'Unknown Job';

        OutputStyle::write(
            OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Processed:  ", 'green') . "{$jobName}",
            'white'
        );
        OutputStyle::newLine();
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @param \Throwable $e
     * @param \Elegant\Queue\WorkerOptions $options
     * @return void
     */
    protected function raiseExceptionOccurredJobEvent(string $connectionName, Jobs\Job $job, Throwable $e, WorkerOptions $options)
    {
        $payload = $job->payload();
        $jobName = $payload['job'] ?? 'Unknown Job';

        // Check if this job has failed permanently
        $hasFailedPermanently = $job->hasFailed();

        if ($hasFailedPermanently) {
            // Show final failure message without attempt number
            OutputStyle::write(
                OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Failed:     ", 'red') . "{$jobName} (Job has been marked as failed)",
                'white'
            );
        } else {
            // For jobs that will be retried, calculate the reverse attempt number
            $currentAttempt = $job->attempts();
            $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $options->maxTries;
            $reverseAttempt = $maxTries - $currentAttempt;

            // Show failure message with reverse attempt number
            OutputStyle::write(
                OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Failed:     ", 'red') . "{$jobName} (attempt: {$reverseAttempt})",
                'white'
            );
        }
        OutputStyle::newLine();
    }

    /**
     * Determine if the queue worker should restart.
     *
     * @param int|null $lastRestart
     * @return bool
     */
    protected function queueShouldRestart(?int $lastRestart): bool
    {
        return $this->getTimestampOfLastQueueRestart() != $lastRestart;
    }

    /**
     * Get the last queue restart timestamp, or null.
     *
     * @return int|null
     */
    protected function getTimestampOfLastQueueRestart(): ?int
    {
        return null; // For now, always return null since we don't have cache
    }

    /**
     * Enable async signals for the process.
     *
     * @return void
     */
    protected function listenForSignals()
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->shouldQuit = true;
        });

        pcntl_signal(SIGUSR2, function () {
            $this->paused = true;
        });

        pcntl_signal(SIGCONT, function () {
            $this->paused = false;
        });
    }

    /**
     * Determine if "async" signals are supported.
     *
     * @return bool
     */
    protected function supportsAsyncSignals(): bool
    {
        return extension_loaded('pcntl');
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param int $memoryLimit
     * @return bool
     */
    public function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @param int $status
     * @return int
     */
    public function stop(int $status = 0): int
    {
        OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Worker stopped with status: {$status}", 'red');

        return $status;
    }

    /**
     * Kill the process.
     *
     * @param int $status
     * @return void
     */
    public function kill(int $status = 0): void
    {
        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
    }

    /**
     * Create an instance of MaxAttemptsExceededException.
     *
     * @param \Elegant\Queue\Jobs\Job $job
     * @return \Exception
     */
    protected function maxAttemptsExceededException(Jobs\Job $job): Exception
    {
        return new \Exception($job->resolveName() . ' has been attempted too many times or run too long. The job may have previously timed out.');
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @param int|float $seconds
     * @return void
     */
    public function sleep($seconds)
    {
        if ($seconds < 1) {
            usleep($seconds * 1000000);
        } else {
            sleep($seconds);
        }
    }

    /**
     * Set the name of the worker.
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Register a callback to be executed to pick jobs.
     *
     * @param string $workerName
     * @param callable $callback
     * @return void
     */
    public static function popUsing(string $workerName, callable $callback)
    {
        if (is_null($callback)) {
            unset(static::$popCallbacks[$workerName]);
        } else {
            static::$popCallbacks[$workerName] = $callback;
        }
    }

    /**
     * Get the queue manager instance.
     *
     * @return \Elegant\Queue\QueueManager
     */
    public function getManager(): QueueManager
    {
        return $this->manager;
    }

    /**
     * Set the queue manager instance.
     *
     * @param \Elegant\Queue\QueueManager $manager
     * @return void
     */
    public function setManager(QueueManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Determine if the given exception was caused by a lost connection.
     *
     * @param \Exception|\Throwable $e
     * @return bool
     */
    protected function causedByLostConnection($e): bool
    {
        $message = $e->getMessage();

        $patterns = [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
            'query_wait_timeout',
            'reset by peer',
            'Physical connection is not usable',
            'TCP Provider: Error code 0x68',
            'Name or service not known',
            'ORA-03114',
            'Packets out of order. Expected',
            'Adaptive Server connection failed',
            'Communication link failure',
            'connection is no longer usable',
            'Login timeout expired',
            'SQLSTATE[HY000] [2002]',
            'SQLSTATE[HY000] [2006]',
        ];

        foreach ($patterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
