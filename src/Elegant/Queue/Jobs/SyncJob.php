<?php

namespace Elegant\Queue\Jobs;

use Elegant\Contracts\Queue\Job as JobContract;

class SyncJob extends Job implements JobContract
{
    /**
     * The class name of the job.
     *
     * @var string
     */
    protected string $job;

    /**
     * The queue job payload.
     *
     * @var string
     */
    protected string $payload;

    /**
     * Create a new job instance.
     *
     * @param string $payload
     */
    public function __construct(string $payload)
    {
        $this->payload = $payload;
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
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        return 1;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId(): string
    {
        return '';
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody(): string
    {
        return $this->payload;
    }

    /**
     * Get the name of the queue the job belongs to.
     *
     * @return string
     */
    public function getQueue(): string
    {
        return 'sync';
    }
}
