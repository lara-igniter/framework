<?php

namespace Elegant\Queue\Jobs;

use Elegant\Contracts\Queue\Job as JobContract;
use Elegant\Queue\Queue;
use Exception;
use Throwable;

class DatabaseJob extends Job implements JobContract
{
    /**
     * The database queue instance.
     *
     * @var \Elegant\Queue\Queue
     */
    protected Queue $database;

    /**
     * The database job payload.
     *
     * @var array
     */
    protected $job;

    /**
     * Create a new job instance.
     *
     * @param \Elegant\Queue\Queue $database
     * @param array $job
     * @return void
     */
    public function __construct(Queue $database, array $job)
    {
        $this->database = $database;
        $this->job = $job;
        $this->connectionName = 'database';
        $this->queue = $job['queue'];
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId(): string
    {
        return $this->job['id'];
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody(): string
    {
        return $this->job['payload'];
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        return (int)$this->job['attempts'];
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();
        $this->database->deleteJob($this->job['id']);
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param int $delay
     * @return void
     */
    public function release(int $delay = 0)
    {
        parent::release($delay);
        $this->database->releaseJob($this->job['id'], $delay);
    }

    /**
     * Delete the job, call the "failed" method, and raise the failed job event.
     *
     * @param Throwable|null $e
     * @return void
     */
    public function fail(Throwable $e = null)
    {
        parent::markAsFailed();

        $this->database->logFailedJob(
            $this->connectionName,
            $this->queue,
            $this->getRawBody(),
            $e
        );

        $this->delete();

        try {
            $payload = $this->payload();
            [$class, $method] = $this->parseJob($payload['job']);
            $instance = $this->resolve($class);

            // Restore job data
            if (isset($payload['data']) && is_array($payload['data'])) {
                foreach ($payload['data'] as $property => $value) {
                    if (property_exists($instance, $property)) {
                        $instance->$property = $value;
                    }
                }
            }

            // Call failed method if it exists
            if (method_exists($instance, 'failed')) {
                $instance->failed($e);
            }
        } catch (Exception $failedException) {
            //
        }
    }

    /**
     * Mark the job as failed in the database.
     *
     * @param Exception|null $e
     * @return void
     */
    public function markAsFailed(Exception $e = null)
    {
        $this->database->logFailedJob(
            $this->connectionName,
            $this->queue,
            $this->getRawBody(),
            $e
        );
    }

    /**
     * Get the name of the queue the job belongs to.
     *
     * @return string
     */
    public function getQueue(): string
    {
        return $this->job['queue'];
    }
}
