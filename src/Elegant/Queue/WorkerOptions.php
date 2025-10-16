<?php

namespace Elegant\Queue;

class WorkerOptions
{
    /**
     * The number of seconds to wait before retrying a job that encountered an uncaught exception.
     *
     * @var int|int[]
     */
    public $backoff;

    /**
     * The maximum amount of RAM the worker may consume.
     *
     * @var int
     */
    public int $memory;

    /**
     * The maximum number of seconds a child worker may run.
     *
     * @var int
     */
    public int $timeout;

    /**
     * The number of seconds to wait in between polling the queue.
     *
     * @var int
     */
    public int $sleep;

    /**
     * The number of seconds to rest between jobs.
     *
     * @var int
     */
    public int $rest;

    /**
     * The maximum number of times a job may be attempted.
     *
     * @var int
     */
    public int $maxTries;

    /**
     * Indicates if the worker should run in maintenance mode.
     *
     * @var bool
     */
    public bool $force;

    /**
     * Indicates if the worker should stop when the queue is empty.
     *
     * @var bool
     */
    public bool $stopWhenEmpty;

    /**
     * The maximum number of jobs to run.
     *
     * @var int
     */
    public int $maxJobs;

    /**
     * The maximum number of seconds a worker may live.
     *
     * @var int
     */
    public int $maxTime;

    /**
     * Create a new worker option instance.
     *
     * @param int|int[] $backoff
     * @param int $memory
     * @param int $timeout
     * @param int $sleep
     * @param int $maxTries
     * @param bool $force
     * @param bool $stopWhenEmpty
     * @param int $maxJobs
     * @param int $maxTime
     * @param int $rest
     * @return void
     */
    public function __construct($backoff = 0, int $memory = 128, int $timeout = 60, int $sleep = 3, int $maxTries = 1,
                                bool $force = false, bool $stopWhenEmpty = false, int $maxJobs = 0, int $maxTime = 0, int $rest = 0)
    {
        $this->backoff = $backoff;
        $this->sleep = $sleep;
        $this->rest = $rest;
        $this->force = $force;
        $this->memory = $memory;
        $this->timeout = $timeout;
        $this->maxTries = $maxTries;
        $this->stopWhenEmpty = $stopWhenEmpty;
        $this->maxJobs = $maxJobs;
        $this->maxTime = $maxTime;
    }
}
