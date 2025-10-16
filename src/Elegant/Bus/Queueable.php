<?php

namespace Elegant\Bus;

trait Queueable
{
    /**
     * The name of the connection the job should be sent to.
     *
     * @var string|null
     */
    public ?string $connection;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public ?string $queue;

    /**
     * The number of seconds before the job should be made available.
     *
     * @var \DateTimeInterface|\DateInterval|int|null
     */
    public $delay;

    /**
     * Set the desired connection for the job.
     *
     * @param string|null $connection
     * @return $this
     */
    public function onConnection(?string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Set the desired queue for the job.
     *
     * @param string|null $queue
     * @return $this
     */
    public function onQueue(?string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Set the desired delay in seconds for the job.
     *
     * @param \DateTimeInterface|\DateInterval|int|null $delay
     * @return $this
     */
    public function delay($delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    /**
     * Set the delay for the job to zero seconds.
     *
     * @return $this
     */
    public function withoutDelay(): self
    {
        $this->delay = 0;

        return $this;
    }
}
