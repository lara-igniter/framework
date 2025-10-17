<?php

namespace Elegant\Queue\Events;

class JobProcessing
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
     * Create a new event instance.
     *
     * @param string $connectionName
     * @param \Elegant\Contracts\Queue\Job $job
     * @return void
     */
    public function __construct($connectionName, $job)
    {
        $this->connectionName = $connectionName;
        $this->job = $job;
    }
}
