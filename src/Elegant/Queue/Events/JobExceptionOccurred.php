<?php

namespace Elegant\Queue\Events;

use Throwable;

class JobExceptionOccurred
{
    /**
     * The connection name.
     *
     * @var string
     */
    public $connectionName;

    /**
     * The job instance.
     *
     * @var \Elegant\Contracts\Queue\Job
     */
    public $job;

    /**
     * The exception that occurred.
     *
     * @var \Throwable
     */
    public $exception;

    /**
     * Create a new event instance.
     *
     * @param string $connectionName
     * @param \Elegant\Contracts\Queue\Job $job
     * @param \Throwable $exception
     * @return void
     */
    public function __construct($connectionName, $job, Throwable $exception)
    {
        $this->connectionName = $connectionName;
        $this->job = $job;
        $this->exception = $exception;
    }
}
