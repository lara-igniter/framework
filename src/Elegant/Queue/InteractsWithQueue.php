<?php

namespace Elegant\Queue;

use Elegant\Contracts\Queue\Job as JobContract;
use Throwable;

trait InteractsWithQueue
{
    /**
     * The underlying queue job instance.
     *
     * @var \Elegant\Contracts\Queue\Job
     */
    protected JobContract $job;

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        return $this->job ? $this->job->attempts() : 0;
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        if ($this->job) {
            $this->job->delete();
        }
    }

    /**
     * Fail the job from the queue.
     *
     * @param \Throwable|null $exception
     * @return void
     */
    public function fail(Throwable $exception = null)
    {
        if ($this->job) {
            $this->job->fail($exception);
        }
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param int $delay
     * @return void
     */
    public function release(int $delay = 0)
    {
        if ($this->job) {
            $this->job->release($delay);
        }
    }

    /**
     * Set the base queue job instance.
     *
     * @param \Elegant\Contracts\Queue\Job $job
     * @return $this
     */
    public function setJob(JobContract $job): self
    {
        $this->job = $job;

        return $this;
    }
}
