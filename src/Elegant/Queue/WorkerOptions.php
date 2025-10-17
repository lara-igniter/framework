<?php

namespace Elegant\Queue;

class WorkerOptions
{
    /**
     * The number of seconds to wait before retrying a failed job.
     *
     * @var int
     */
    public int $backoff;

    /**
     * The maximum amount of RAM the worker may consume.
     *
     * @var int
     */
    public int $memory;

    /**
     * The maximum number of seconds a job may run.
     *
     * @var int
     */
    public int $timeout;

    /**
     * The number of seconds to sleep when no job is available.
     *
     * @var int
     */
    public int $sleep;

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
     * Indicates if the worker should stop when queue is empty.
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
     * The number of seconds to rest between jobs.
     *
     * @var int
     */
    public int $rest;

    /**
     * Create a new worker options instance.
     *
     * @param  int  $backoff
     * @param  int  $memory
     * @param  int  $timeout
     * @param  int  $sleep
     * @param  int  $maxTries
     * @param  bool  $force
     * @param  bool  $stopWhenEmpty
     * @param  int  $maxJobs
     * @param  int  $maxTime
     * @param  int  $rest
     * @return void
     */
    public function __construct(
        int $backoff = 0,
        int $memory = 128,
        int $timeout = 60,
        int $sleep = 3,
        int $maxTries = 1,
        bool $force = false,
        bool $stopWhenEmpty = false,
        int $maxJobs = 0,
        int $maxTime = 0,
        int $rest = 0
    ) {
        $this->backoff = $backoff;
        $this->memory = $memory;
        $this->timeout = $timeout;
        $this->sleep = $sleep;
        $this->maxTries = $maxTries;
        $this->force = $force;
        $this->stopWhenEmpty = $stopWhenEmpty;
        $this->maxJobs = $maxJobs;
        $this->maxTime = $maxTime;
        $this->rest = $rest;
    }
}
