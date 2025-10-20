<?php

namespace Elegant\Queue\Jobs;

use Elegant\Contracts\Queue\Job as JobContract;
use Elegant\Queue\DatabaseQueue;
use stdClass;
use Throwable;

class DatabaseJob extends Job implements JobContract
{
    /**
     * The database queue instance.
     *
     * @var \Elegant\Queue\DatabaseQueue
     */
    protected DatabaseQueue $database;

    /**
     * The database job payload.
     *
     * @var \Elegant\Queue\Jobs\DatabaseJobRecord
     */
    protected DatabaseJobRecord $job;

    /**
     * Create a new job instance.
     *
     * @param \Elegant\Queue\DatabaseQueue $database
     * @param \Elegant\Queue\Jobs\DatabaseJobRecord $job
     * @param string $connectionName
     * @param string $queue
     * @return void
     */
    public function __construct(DatabaseQueue $database, DatabaseJobRecord $job, string $connectionName, string $queue)
    {
        $this->database = $database;
        $this->job = $job;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
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

        $this->database->releaseReserved($this, $delay);
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->database->deleteReserved($this->job->id);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        return (int)$this->job->attempts;
    }

    /**
     * Get the job identifier.
     *
     * @return string|int
     */
    public function getJobId(): string
    {
        return $this->job->id;
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody(): string
    {
        return $this->job->payload;
    }

    /**
     * Get the database job record.
     *
     * @return \Elegant\Queue\Jobs\DatabaseJobRecord
     */
    public function getJobRecord(): stdClass
    {
        return $this->job;
    }


    /**
     * TODO: Remove function bellow
     */

    /**
     * Mark the job as "failed".
     *
     * @param \Throwable|null $e
     * @return void
     * @throws \Throwable
     */
    public function fail(Throwable $e = null)
    {
        $this->markAsFailed();

        if ($this->isDeleted()) {
            return;
        }

        try {
            $this->database->logFailedJob(
                $this->connectionName,
                $this->queue,
                $this->getRawBody(),
                $e
            );

            $this->delete();

            $this->failed($e);
        } catch (Throwable $failedException) {
            // If failed() method throws an exception, we still want to log the original failure
        }
    }

    /**
     * Increment the number of times the job has been attempted.
     *
     * @return void
     */
    public function incrementAttempts()
    {
        $this->job->attempts = $this->job->attempts + 1;

        $this->database->incrementAttempts($this->job->id);
    }
}
