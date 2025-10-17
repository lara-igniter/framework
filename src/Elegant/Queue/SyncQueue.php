<?php

namespace Elegant\Queue;

use Elegant\Contracts\Queue\Job;
use Elegant\Queue\Jobs\SyncJob;
use Throwable;

class SyncQueue extends Queue
{
    /**
     * Get the size of the queue.
     *
     * @param string|null $queue
     * @return int
     */
    public function size($queue = null): int
    {
        return 0;
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     * @throws \Throwable
     */
    public function push($job, $data = '', $queue = null)
    {
        $queueJob = $this->resolveJob($this->createPayload($job, $queue ?: 'default', $data), $queue);

        try {
            $this->raiseBeforeJobEvent($queueJob);

            $queueJob->fire();

            $this->raiseAfterJobEvent($queueJob);
        } catch (Throwable $e) {
            $this->handleException($queueJob, $e);
        }

        return 0;
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string|null $queue
     * @param array $options
     * @return mixed
     * @throws Throwable
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $queueJob = $this->resolveJob($payload, $queue);

        try {
            $this->raiseBeforeJobEvent($queueJob);
            $queueJob->fire();
            $this->raiseAfterJobEvent($queueJob);
        } catch (Throwable $e) {
            $this->handleException($queueJob, $e);
        }

        return 0;
    }

    /**
     * Push a new job onto the queue after (n) seconds.
     *
     * @param \DateTimeInterface|int $delay
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     * @throws Throwable
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->push($job, $data, $queue);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     * @return \Elegant\Contracts\Queue\Job|null
     */
    public function pop($queue = null): ?Job
    {
        return null;
    }

    /**
     * Resolve a Sync job instance.
     *
     * @param string $payload
     * @return \Elegant\Queue\Jobs\SyncJob
     */
    protected function resolveJob(string $payload): SyncJob
    {
        return new SyncJob($payload);
    }

    /**
     * Raise the before queue job event.
     *
     * @param \Elegant\Contracts\Queue\Job $job
     * @return void
     */
    protected function raiseBeforeJobEvent(Job $job)
    {
        if ($this->container && method_exists($this->container, 'bound') && $this->container->bound('events')) {
            $this->container['events']->dispatch(new Events\JobProcessing($this->connectionName, $job));
        }
    }

    /**
     * Raise the after queue job event.
     *
     * @param \Elegant\Contracts\Queue\Job $job
     * @return void
     */
    protected function raiseAfterJobEvent(Job $job)
    {
        if ($this->container && method_exists($this->container, 'bound') && $this->container->bound('events')) {
            $this->container['events']->dispatch(new Events\JobProcessed($this->connectionName, $job));
        }
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param \Elegant\Contracts\Queue\Job $job
     * @param \Throwable $e
     * @return void
     */
    protected function raiseExceptionOccurredJobEvent(Job $job, Throwable $e)
    {
        if ($this->container && method_exists($this->container, 'bound') && $this->container->bound('events')) {
            $this->container['events']->dispatch(new Events\JobExceptionOccurred($this->connectionName, $job, $e));
        }
    }

    /**
     * Handle an exception that occurred while processing a job.
     *
     * @param \Elegant\Contracts\Queue\Job $queueJob
     * @param \Throwable $e
     * @return void
     * @throws \Throwable
     */
    protected function handleException(Job $queueJob, Throwable $e)
    {
        $this->raiseExceptionOccurredJobEvent($queueJob, $e);

        $queueJob->fail($e);

        throw $e;
    }
}
