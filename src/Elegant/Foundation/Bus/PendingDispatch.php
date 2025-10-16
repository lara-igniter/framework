<?php

namespace Elegant\Foundation\Bus;

class PendingDispatch
{
    /**
     * The job instance.
     *
     * @var mixed
     */
    protected $job;

    /**
     * Create a new pending job dispatch.
     *
     * @param mixed $job
     * @return void
     */
    public function __construct($job)
    {
        $this->job = $job;
    }

    /**
     * Set the desired connection for the job.
     *
     * @param string|null $connection
     * @return $this
     */
    public function onConnection(?string $connection): PendingDispatch
    {
        $this->job->onConnection($connection);

        return $this;
    }

    /**
     * Set the desired queue for the job.
     *
     * @param string|null $queue
     * @return $this
     */
    public function onQueue(?string $queue): PendingDispatch
    {
        $this->job->onQueue($queue);

        return $this;
    }

    /**
     * Set the desired delay in seconds for the job.
     *
     * @param \DateTimeInterface|\DateInterval|int|null $delay
     * @return $this
     */
    public function delay($delay): PendingDispatch
    {
        $this->job->delay($delay);

        return $this;
    }

    /**
     * Set the delay for the job to zero seconds.
     *
     * @return $this
     */
    public function withoutDelay(): PendingDispatch
    {
        $this->job->withoutDelay();

        return $this;
    }

    /**
     * Get the underlying job instance.
     *
     * @return mixed
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * Dynamically proxy methods to the underlying job.
     *
     * @param string $method
     * @param array $parameters
     * @return $this
     */
    public function __call(string $method, array $parameters)
    {
        $this->job->{$method}(...$parameters);

        return $this;
    }

    /**
     * Handle the object's destruction.
     *
     * @return void
     * @throws \ReflectionException
     */
    public function __destruct()
    {
        dispatch($this->job);
    }
}
