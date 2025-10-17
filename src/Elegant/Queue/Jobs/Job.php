<?php

namespace Elegant\Queue\Jobs;

use Elegant\Contracts\Queue\Job as JobContract;
use Exception;
use Throwable;

abstract class Job implements JobContract
{
    /**
     * The job handler instance.
     *
     * @var mixed
     */
    protected $instance;

    /**
     * The name of the queue the job belongs to.
     *
     * @var string
     */
    protected string $queue;

    /**
     * The name of the connection the job belongs to.
     *
     * @var string
     */
    protected string $connectionName;

    /**
     * Indicates if the job has been deleted.
     *
     * @var bool
     */
    protected bool $deleted = false;

    /**
     * Indicates if the job has been released.
     *
     * @var bool
     */
    protected bool $released = false;

    /**
     * Indicates if the job has failed.
     *
     * @var bool
     */
    protected bool $failed = false;

    /**
     * Get the job identifier.
     *
     * @return string
     */
    abstract public function getJobId(): string;

    /**
     * Get the raw body of the job.
     *
     * @return string
     */
    abstract public function getRawBody(): string;

    /**
     * Get the UUID of the job.
     *
     * @return string|null
     */
    public function uuid(): ?string
    {
        return $this->payload()['uuid'] ?? null;
    }

    /**
     * Fire the job.
     *
     * @return void
     * @throws Throwable
     */
    public function fire()
    {
        try {
            $payload = $this->payload();

            if (!$payload) {
                throw new Exception('Invalid job payload: unable to decode JSON');
            }

            if (!isset($payload['job'])) {
                throw new Exception('Job payload missing "job" key');
            }

            $this->instance = $this->resolve($payload);
        } catch (Throwable $e) {
            error_log("Job fire error: " . $e->getMessage());
            error_log("Job payload: " . $this->getRawBody());
            throw $e;
        }
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        $this->deleted = true;
    }

    /**
     * Determine if the job has been deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param int $delay
     * @return void
     */
    public function release(int $delay = 0)
    {
        $this->released = true;
    }

    /**
     * Determine if the job was released back into the queue.
     *
     * @return bool
     */
    public function isReleased(): bool
    {
        return $this->released;
    }

    /**
     * Determine if the job has been deleted or released.
     *
     * @return bool
     */
    public function isDeletedOrReleased(): bool
    {
        return $this->isDeleted() || $this->isReleased();
    }

    /**
     * Determine if the job has been marked as a failure.
     *
     * @return bool
     */
    public function hasFailed(): bool
    {
        return $this->failed;
    }

    /**
     * Mark the job as "failed".
     *
     * @return void
     */
    public function markAsFailed()
    {
        $this->failed = true;
    }

    /**
     * Mark the job as "failed".
     *
     * @param Throwable|null $e
     * @return void
     */
    public function fail(Throwable $e = null)
    {
        $this->markAsFailed();

        /**
         * OLD CODE
         * if ($e && method_exists($this, 'failed')) {
         *   $this->failed($e);
         * }
         */

        if ($this->isDeleted()) {
            return;
        }

        try {
            $this->failed($e);
        } catch (Throwable $e) {
            //
        }
    }

    /**
     * Process an exception that caused the job to fail.
     *
     * @param Throwable|null $e
     * @return void
     * @throws Exception
     * @throws Throwable
     */
    protected function failed(?Throwable $e)
    {
        $payload = $this->payload();

        if (method_exists($this->instance = $this->resolve($payload), 'failed')) {
            $this->instance->failed($payload['data'], $e, $payload['uuid'] ?? '');
        }
    }

    /**
     * Resolve the given class.
     *
     * @param array $payload
     * @return mixed
     * @throws Throwable
     * @throws \ReflectionException
     */
    protected function resolve(array $payload)
    {
        $jobClass = $payload['job'];
        $jobData = $payload['data'] ?? [];

        [$class, $method] = $this->parseJob($jobClass);

        try {
            if (!class_exists($class)) {
                throw new Exception("Job class {$class} not found");
            }

            if (method_exists($class, 'handle')) {
                $reflection = new \ReflectionClass($class);
                $constructor = $reflection->getConstructor();

                if ($constructor && $constructor->getNumberOfParameters() > 0) {
                    $instance = $reflection->newInstanceArgs(array_values($jobData));
                } else {
                    $instance = new $class;

                    foreach ($jobData as $key => $value) {
                        if (property_exists($instance, $key)) {
                            $instance->{$key} = $value;
                        }
                    }
                }
            } else {
                $instance = new $class;
            }

            if (method_exists($instance, 'handle')) {
                return $instance->handle();
            } elseif (method_exists($instance, $method)) {
                return $instance->{$method}($this, $jobData);
            } else {
                throw new Exception("Job class {$class} does not have a handle() or {$method}() method");
            }

        } catch (Throwable $e) {
            error_log("Failed to execute job {$class}: " . $e->getMessage());
            error_log("Job data: " . print_r($jobData, true));
            throw $e;
        }
    }

    /**
     * Parse the job declaration into class and method.
     *
     * @param string $job
     * @return array
     */
    protected function parseJob(string $job): array
    {
        return str_contains($job, '@') ? explode('@', $job, 2) : [$job, 'handle'];
    }

    /**
     * Get the resolved job handler instance.
     *
     * @return mixed
     */
    public function getResolvedJob()
    {
        return $this->instance;
    }

    /**
     * Get the job method from payload.
     *
     * @param array $payload
     * @return string
     */
    protected function getJobMethod(array $payload): string
    {
        return $payload['method'] ?? 'handle';
    }

    /**
     * Get the decoded body of the job.
     *
     * @return array
     */
    public function payload(): array
    {
        return json_decode($this->getRawBody(), true);
    }

    /**
     * Get the number of times to attempt a job.
     *
     * @return int|null
     */
    public function maxTries(): ?int
    {
        return $this->payload()['maxTries'] ?? null;
    }

    /**
     * Get the number of times to attempt a job after an exception.
     *
     * @return int|null
     */
    public function maxExceptions(): ?int
    {
        return $this->payload()['maxExceptions'] ?? null;
    }

    /**
     * Determine if the job should fail when it timeouts.
     *
     * @return bool
     */
    public function shouldFailOnTimeout(): bool
    {
        return $this->payload()['failOnTimeout'] ?? false;
    }

    /**
     * The number of seconds to wait before retrying a job that encountered an uncaught exception.
     *
     * @return int|int[]|null
     */
    public function backoff()
    {
        return $this->payload()['backoff'] ?? $this->payload()['delay'] ?? null;
    }

    /**
     * Get the number of seconds the job can run.
     *
     * @return int|null
     */
    public function timeout(): ?int
    {
        return $this->payload()['timeout'] ?? null;
    }

    /**
     * Get the timestamp indicating when the job should timeout.
     *
     * @return int|null
     */
    public function retryUntil(): ?int
    {
        return $this->payload()['retryUntil'] ?? $this->payload()['timeoutAt'] ?? null;
    }

    /**
     * Get the name of the queued job class.
     *
     * @return string
     */
    public function getName(): string
    {
        $payload = $this->payload();

        return $payload['displayName'] ?? $payload['job'] ?? 'UnknownJob';
    }

    /**
     * Get the resolved name of the queued job class.
     *
     * Resolves the name of "wrapped" jobs such as class-based handlers.
     *
     * @return string
     */
    public function resolveName(): string
    {
        return $this->getName();
    }

    /**
     * Get the name of the connection the job belongs to.
     *
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Get the name of the queue the job belongs to.
     *
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }
}
