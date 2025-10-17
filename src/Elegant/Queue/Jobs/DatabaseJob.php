<?php

namespace Elegant\Queue\Jobs;

use Elegant\Contracts\Queue\Job as JobContract;
use Elegant\Queue\DatabaseQueue;

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
     * @var array
     */
    protected array $job;

    /**
     * Create a new job instance.
     *
     * @param \Elegant\Queue\DatabaseQueue $database
     * @param array $job
     * @param string $connectionName
     * @param string $queue
     * @return void
     */
    public function __construct(DatabaseQueue $database, array $job, string $connectionName, string $queue)
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

        $this->job['attempts'] = $this->job['attempts'] + 1;

        $this->database->releaseJob($this->job['id'], $delay);
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
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        return (int) $this->job['attempts'];
    }

    /**
     * Get the job identifier.
     *
     * @return string|int
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
     * Increment the number of times the job has been attempted.
     *
     * @return void
     */
    public function incrementAttempts()
    {
        $this->database->incrementAttempts($this->job['id']);
    }
}
