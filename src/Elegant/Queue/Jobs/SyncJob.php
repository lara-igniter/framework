<?php

namespace Elegant\Queue\Jobs;

use Elegant\Contracts\Queue\Job as JobContract;
use Exception;

class SyncJob extends Job implements JobContract
{
    /**
     * The sync job payload.
     *
     * @var mixed
     */
    protected $job;

    /**
     * The job payload data.
     *
     * @var array
     */
    protected array $payload;

    /**
     * Create a new sync job instance.
     *
     * @param  mixed  $job
     * @param array $payload
     * @param string $queue
     * @return void
     */
    public function __construct($job, array $payload = [], string $queue = 'default')
    {
        $this->job = $job;
        $this->payload = $payload;
        $this->connectionName = 'sync';
        $this->queue = $queue;
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param  int  $delay
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
        return json_encode([
            'job' => get_class($this->job),
            'data' => $this->payload
        ]);
    }

    /**
     * Get the decoded body of the job.
     *
     * @return array
     */
    public function payload(): array
    {
        return [
            'job' => get_class($this->job),
            'data' => $this->payload
        ];
    }

    /**
     * Execute the sync job immediately.
     *
     * @return mixed
     */
    public function handle()
    {
        return $this->job->handle();
    }

    /**
     * Fire the job (execute immediately for sync jobs).
     *
     * @return void
     * @throws Exception
     */
    public function fire()
    {
        try {
            $this->handle();

            $this->delete();
        } catch (Exception $e) {
            $this->failed = true;

            throw $e;
        }
    }
}
