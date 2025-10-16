<?php

namespace Elegant\Queue\Jobs;

use Error;
use Exception;
use Throwable;

abstract class Job
{
    /**
     * The job handler instance.
     *
     * @var mixed
     */
    protected $instance;

    /**
     * The connection name for the job.
     *
     * @var string
     */
    protected $connectionName;

    /**
     * The queue that the job belongs to.
     *
     * @var string
     */
    protected $queue;

    /**
     * Indicates if the job has been deleted.
     *
     * @var bool
     */
    protected $deleted = false;

    /**
     * Indicates if the job has been released.
     *
     * @var bool
     */
    protected $released = false;

    /**
     * Indicates if the job has failed.
     *
     * @var bool
     */
    protected $failed = false;

    /**
     * The name of the connection the job should be sent to.
     *
     * @var string|null
     */
    protected $connection;

    /**
     * Get the job identifier.
     *
     * @return string
     */
    abstract public function getJobId(): string;

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    abstract public function getRawBody(): string;

    /**
     * Fire the job.
     *
     * @return void
     * @throws Exception
     */
    public function fire()
    {
        $payload = $this->payload();

        [$class, $method] = $this->parseJob($payload['job']);

        $instance = $this->resolve($class);

        // If the job has data, we need to restore its properties from the payload
        if (isset($payload['data']) && is_array($payload['data'])) {
            foreach ($payload['data'] as $property => $value) {
                if (property_exists($instance, $property)) {
                    $instance->$property = $value;
                }
            }
        }

        // Call the method with just the job instance, not the data separately
        // The job instance should have all the data already restored as properties
        if (method_exists($instance, $method)) {
            $instance->{$method}();
        } else {
            throw new \Exception("Method {$method} does not exist on class {$class}");
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
        return strpos($job, '@') !== false ? explode('@', $job, 2) : [$job, 'handle'];
    }

    /**
     * Resolve the given class.
     *
     * @param string $class
     * @return mixed
     * @throws Exception
     */
    protected function resolve(string $class)
    {
        // Try to create instance without constructor parameters first
        try {
            return new $class();
        } catch (Error $e) {
            // Handle ArgumentCountError and other PHP errors
            return $this->createInstanceWithReflection($class);
        } catch (Exception $e) {
            // Handle other exceptions
            return $this->createInstanceWithReflection($class);
        }
    }

    /**
     * Create an instance using reflection when the constructor requires parameters
     *
     * @param string $class
     * @return mixed
     * @throws Exception
     */
    private function createInstanceWithReflection(string $class)
    {
        try {
            $reflection = new \ReflectionClass($class);
            $constructor = $reflection->getConstructor();

            if ($constructor) {
                $parameters = $constructor->getParameters();
                $args = [];

                foreach ($parameters as $param) {
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } elseif ($param->allowsNull()) {
                        $args[] = null;
                    } else {
                        // For required parameters without defaults, use dummy values
                        $type = $param->getType();
                        if ($type && $type->getName() === 'int') {
                            $args[] = 0;
                        } elseif ($type && $type->getName() === 'string') {
                            $args[] = '';
                        } elseif ($type && $type->getName() === 'array') {
                            $args[] = [];
                        } else {
                            $args[] = null;
                        }
                    }
                }

                return $reflection->newInstanceArgs($args);
            }

            return new $class();
        } catch (Exception $e) {
            throw new Exception("Cannot instantiate job class {$class}: " . $e->getMessage());
        }
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
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        return (int)$this->payload()['attempts'] ?? 0;
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
        return $this->payload()['retryUntil'] ?? null;
    }

    /**
     * Get the name of the queued job class.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->payload()['job'];
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
     * Delete the job, call the "failed" method, and raise the failed job event.
     *
     * @param Throwable|null $e
     * @return void
     */
    public function fail(Throwable $e = null)
    {
        $this->markAsFailed();

        if ($this->isDeleted()) {
            return;
        }

        try {
            // Call the failed method if it exists on the job class
            $this->failed($e);
        } catch (Exception $e) {

        }
    }

    /**
     * Process an exception that caused the job to fail.
     *
     * @param Throwable|null $e
     * @return void
     * @throws Exception
     */
    protected function failed(?Throwable $e)
    {
        $payload = $this->payload();

        [$class, $method] = $this->parseJob($payload['job']);

        if (method_exists($this->instance = $this->resolve($class), 'failed')) {
            $this->instance->failed($payload['data'], $e, $payload['uuid'] ?? '');
        }
    }
}
