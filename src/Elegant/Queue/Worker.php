<?php

namespace Elegant\Queue;

use Elegant\Console\OutputStyle;
use Elegant\Support\Facades\Date;
use Exception;
use Throwable;

class Worker
{
    /**
     * The queue manager instance.
     *
     * @var \Elegant\Queue\Queue
     */
    protected Queue $manager;

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
     * @param \Elegant\Queue\Queue|null $manager
     */
    public function __construct(Queue $manager = null)
    {
        $this->manager = $manager ?: new Queue();
    }

    /**
     * Listen to the given queues in a loop.
     *
     * @param string $connectionName
     * @param string $queue
     * @param \Elegant\Queue\WorkerOptions $options
     * @return int
     */
    public function daemon(string $connectionName, string $queue, WorkerOptions $options): int
    {
        set_time_limit(0);

        if ($this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        $lastRestart = $this->getTimestampOfLastQueueRestart();

        [$startTime, $jobsProcessed] = [time(), 0];

        OutputStyle::write("  Processing jobs from the [{$queue}] queue.");
        OutputStyle::newLine();

        while (true) {
            // The worker should not run if the application is in maintenance mode...
            if ($this->shouldQuit) {
                OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Worker stopping...", 'red');
                $this->stop($options, $lastRestart, $startTime, $jobsProcessed);
                break;
            }

            // If the daemon should restart, stop listening and let the process restart...
            if ($this->daemonShouldRun($options, $connectionName, $queue) === false) {
                $this->pauseWorker($options, $lastRestart);
                continue;
            }

            // If we have exceeded our limits, we'll stop this worker
            if ($this->memoryExceeded($options->memory) ||
                $this->queueShouldRestart($lastRestart) ||
                $this->daemonExceedsLimits($options, $startTime, $jobsProcessed))
            {
                OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Worker limits exceeded, stopping...", 'red');

                $this->stop($options, $lastRestart, $startTime, $jobsProcessed);

                break;
            }

            // Process the next job on the queue - no logging here
            $this->runNextJob($connectionName, $queue, $options);

            if ($options->stopWhenEmpty && $this->queueIsEmpty($connectionName, $queue)) {
                OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Queue is empty, stopping...", 'red');

                $this->stop($options, $lastRestart, $startTime, $jobsProcessed);
                break;
            }

            $status = $this->stopIfNecessary($options, $lastRestart, $startTime, $jobsProcessed);

            if ($status !== null) {
                return $this->stop($options, $lastRestart, $startTime, $jobsProcessed, $status);
            }

            $jobsProcessed++;
        }

        return 0;
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
        $job = $this->getNextJob($connectionName, $queue);

        // If we're able to pull a job off of the stack, we will process it and then return
        // from this method. If there is no job on the queue, we will "sleep" the worker
        // for the specified number of seconds, then keep processing jobs after sleep.
        if ($job) {
            $this->process($connectionName, $job, $options);
        }

        $this->sleep($options->sleep);
    }

    /**
     * Get the next job from the queue connection.
     *
     * @param string $connectionName
     * @param string $queue
     * @return \Elegant\Queue\Jobs\Job|null
     */
    protected function getNextJob(string $connectionName, string $queue): ?Jobs\Job
    {
        try {
            foreach (explode(',', $queue) as $queue) {
                if (!is_null($job = $this->manager->pop($queue))) {
                    return $job;
                }
            }
        } catch (Exception|Throwable $e) {
            $payload = $job->payload();
            $jobName = $payload['job'] ?? 'Unknown Job';

            OutputStyle::write(
                OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Failed:     ", 'red') . "{$jobName}",
                'white'
            );
            OutputStyle::newLine();

            $this->stopWorkerIfLostConnection($e);
        }

        return null;
    }

    /**
     * Process the given job.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @param \Elegant\Queue\WorkerOptions $options
     * @return void
     * @throws Exception
     */
    protected function process(string $connectionName, Jobs\Job $job, WorkerOptions $options)
    {
        try {
            // First, we will raise the before job event and determine if the job has already ran
            // over its maximum attempt limits, which could primarily happen if this job is
            // continually timing out and not actually throwing any exceptions from itself.
            $this->raiseBeforeJobEvent($connectionName, $job);

            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
                $connectionName, $job, (int)$options->maxTries
            );

            if ($job->isDeleted()) {
                $this->raiseAfterJobEvent($connectionName, $job);
            }

            // Here we will fire off the job and let it process. We will catch any exceptions so
            // they can be reported to the developers logs, etc. Once the job is finished the
            // proper events will be fired to let any listeners know this job has finished.
            $this->runJob($job, $connectionName, $options);
        } catch (Exception $e) {
            $payload = $job->payload();
            $jobName = $payload['job'] ?? 'Unknown Job';

            OutputStyle::write(
                OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Failed:     ", 'red') . "{$jobName}",
                'white'
            );

            $this->handleJobException($connectionName, $job, $options, $e);
        }
    }

    /**
     * Handle an exception that occurred while the job was running.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @param \Elegant\Queue\WorkerOptions $options
     * @param \Exception $e
     * @return void
     * @throws Exception
     */
    protected function handleJobException(string $connectionName, Jobs\Job $job, WorkerOptions $options, Exception $e)
    {
        try {
            // Get the max tries for this job
            $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $options->maxTries;

            // Check if this will be the final attempt
            $currentAttempts = $job->attempts();
            $nextAttempt = $currentAttempts + 1;

            $attempts = $maxTries - $currentAttempts;

            $payload = $job->payload();
            $jobName = $payload['job'] ?? 'Unknown Job';

            OutputStyle::write(
                OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Processing: ", 'yellow') . "{$jobName} (attempt: {$attempts})",
                'white'
            );

            // If we've reached max attempts, mark as permanently failed
            if ($nextAttempt >= $maxTries) {
                OutputStyle::write(
                    OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Failed:     ", 'red') . "{$jobName} (Job has been marked as failed)",
                    'white'
                );
                $this->failJob($job, $e);
            } else {
                // Otherwise, release for retry
                $backoffDelay = $this->calculateBackoff($job, $options);
                $job->release($backoffDelay);
            }

            $this->raiseExceptionOccurredJobEvent($connectionName, $job, $e);

        } catch (Exception $failException) {
            //
        }
    }

    /**
     * Run the given job.
     *
     * @param \Elegant\Queue\Jobs\Job $job
     * @param string $connectionName
     * @param \Elegant\Queue\WorkerOptions $options
     * @return void
     * @throws Exception
     */
    protected function runJob(Jobs\Job $job, string $connectionName, WorkerOptions $options)
    {
        try {
            // Set job timeout
            if ($options->timeout > 0) {
                set_time_limit($options->timeout);
            }

            $payload = $job->payload();
            $jobName = $payload['job'] ?? 'Unknown Job';

            // Log processing start
            OutputStyle::write(
                OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Processing: ", 'yellow') . "{$jobName}",
                'white'
            );

            // Fire the job
            $job->fire();

            // Mark job as completed if it hasn't failed or been released
            if (!$job->hasFailed() && !$job->isReleased() && !$job->isDeleted()) {
                $job->delete();
            }

            // Log processing completion
            OutputStyle::write(
                OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Processed:  ", 'green') . "{$jobName}",
                'white'
            );
            OutputStyle::newLine();

        } finally {
            $this->raiseAfterJobEvent($connectionName, $job);
        }
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @param int $maxTries
     * @return void
     * @throws Exception
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts(string $connectionName, Jobs\Job $job, int $maxTries)
    {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        $retryUntil = $job->retryUntil();

        if ($retryUntil && Date::now()->getTimestamp() <= $retryUntil) {
            return;
        }

        // Fix: Check if attempts is already >= maxTries (not <=)
        if (!$retryUntil && ($maxTries === 0 || $job->attempts() < $maxTries)) {
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
     * @param \Exception $e
     * @return void
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts(string $connectionName, Jobs\Job $job, int $maxTries, Exception $e)
    {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        if ($job->retryUntil() && $job->retryUntil() <= Date::now()->getTimestamp()) {
            $this->failJob($job, $e);
        }

        // Fix: Check if the NEXT attempt will exceed maxTries
        if (!$job->retryUntil() && ($job->attempts() + 1) >= $maxTries) {
            $this->failJob($job, $e);
        }
    }

    /**
     * Fail the given job.
     *
     * @param \Elegant\Queue\Jobs\Job $job
     * @param \Exception $e
     * @return void
     */
    protected function failJob(Jobs\Job $job, Exception $e)
    {
        $job->fail($e);
    }

    /**
     * Calculate the backoff for the given job.
     *
     * @param \Elegant\Queue\Jobs\Job $job
     * @param  \Elegant\Queue\WorkerOptions  $options
     * @return int
     */
    protected function calculateBackoff(Jobs\Job $job, WorkerOptions $options): int
    {
        $backoff = explode(
            ',',
            method_exists($job, 'backoff') && ! is_null($job->backoff())
                ? $job->backoff()
                : $options->backoff
        );

        return (int) ($backoff[$job->attempts() - 1] ?? last($backoff));
    }

    /**
     * Create an instance of MaxAttemptsExceededException.
     *
     * @param \Elegant\Queue\Jobs\Job $job
     * @return \Exception
     */
    protected function maxAttemptsExceededException(Jobs\Job $job): Exception
    {
        return new \Exception($job->resolveName().' has been attempted too many times or run too long. The job may have previously timed out.');
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @param int $seconds
     * @return void
     */
    public function sleep(int $seconds)
    {
        if ($seconds < 1) {
            usleep($seconds * 1000000);
        } else {
            sleep($seconds);
        }
    }

    /**
     * Stop the process if necessary.
     *
     * @param \Elegant\Queue\WorkerOptions $options
     * @param int|null $lastRestart
     * @param int $startTime
     * @param int $jobsProcessed
     * @return int|null
     */
    protected function stopIfNecessary(WorkerOptions $options, ?int $lastRestart, int $startTime = 0, int $jobsProcessed = 0): ?int
    {
        if ($this->shouldQuit) {
            return 12;
        } elseif ($this->memoryExceeded($options->memory)) {
            return 12;
        } elseif ($this->queueShouldRestart($lastRestart)) {
            return 12;
        } elseif ($this->daemonExceedsLimits($options, $startTime, $jobsProcessed)) {
            return 12;
        }

        return null;
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @param \Elegant\Queue\WorkerOptions $options
     * @param int $lastRestart
     * @param int $startTime
     * @param int $jobsProcessed
     * @param int $status
     * @return int
     */
    public function stop(WorkerOptions $options, int $lastRestart = 0, int $startTime = 0, int $jobsProcessed = 0, int $status = 0): int
    {
        OutputStyle::color("[" . now()->format('Y-m-d H:i:s') . "] Worker stopped. Jobs processed: {$jobsProcessed}", 'red');

        return $status;
    }

    /**
     * Pause the worker for the current loop.
     *
     * @param \Elegant\Queue\WorkerOptions $options
     * @param int $lastRestart
     * @return void
     */
    protected function pauseWorker(WorkerOptions $options, int $lastRestart)
    {
        $this->sleep($options->sleep > 0 ? $options->sleep : 1);

        $this->stopIfNecessary($options, $lastRestart);
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
        return !(($this->paused && !$this->shouldQuit));
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
     * Determine if the worker has exceeded any of the limits.
     *
     * @param \Elegant\Queue\WorkerOptions $options
     * @param int $startTime
     * @param int $jobsProcessed
     * @return bool
     */
    protected function daemonExceedsLimits(WorkerOptions $options, int $startTime, int $jobsProcessed): bool
    {
        return $options->stopWhenEmpty ||
            $this->queueShouldRestart($this->getTimestampOfLastQueueRestart()) ||
            $this->memoryExceeded($options->memory) ||
            ($options->maxTime && (time() - $startTime) >= $options->maxTime) ||
            ($options->maxJobs && $jobsProcessed >= $options->maxJobs);
    }

    /**
     * Determine if the queue is completely empty.
     *
     * @param string $connectionName
     * @param string $queue
     * @return bool
     */
    protected function queueIsEmpty(string $connectionName, string $queue): bool
    {
        return is_null($this->manager->pop($queue));
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
     * Raise the before queue job event.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @return void
     */
    protected function raiseBeforeJobEvent(string $connectionName, Jobs\Job $job)
    {
        // Events can be implemented later if needed
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
        // Events can be implemented later if needed
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param string $connectionName
     * @param \Elegant\Queue\Jobs\Job $job
     * @param \Exception $e
     * @return void
     */
    protected function raiseExceptionOccurredJobEvent(string $connectionName, Jobs\Job $job, Exception $e)
    {
        // Events can be implemented later if needed
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
     * Determine if the given exception was caused by a lost connection.
     *
     * @param \Exception $e
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
