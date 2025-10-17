<?php

namespace Elegant\Queue;

use DateTimeInterface;
use Elegant\Support\Str;

abstract class Queue
{
    /**
     * The connection name for the queue.
     *
     * @var string
     */
    protected string $connectionName;

    /**
     * The container instance.
     *
     * @var mixed
     */
    protected $container;

    /**
     * Indicates that jobs should be dispatched after all database transactions have committed.
     *
     * @var bool
     */
    protected $dispatchAfterCommit;

    /**
     * The creation payload callbacks.
     *
     * @var callable[]
     */
    protected static array $createPayloadCallbacks = [];

    /**
     * Push a new job onto the queue.
     *
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     */
    abstract public function push($job, $data = '', $queue = null);

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string|null $queue
     * @param array $options
     * @return mixed
     */
    abstract public function pushRaw($payload, $queue = null, array $options = []);

    /**
     * Push a new job onto the queue after (n) seconds.
     *
     * @param \DateTimeInterface|int $delay
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     */
    abstract public function later($delay, $job, $data = '', $queue = null);

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     * @return \Elegant\Queue\Jobs\Job|null
     */
    abstract public function pop($queue = null);

    /**
     * Get the size of the queue.
     *
     * @param string|null $queue
     * @return int
     */
    abstract public function size($queue = null);

    /**
     * Push a new job onto a specific queue.
     *
     * @param string $queue
     * @param string $job
     * @param mixed $data
     * @return mixed
     */
    public function pushOn($queue, $job, $data = '')
    {
        return $this->push($job, $data, $queue);
    }

    /**
     * Push a new job onto a specific queue after (n) seconds.
     *
     * @param string $queue
     * @param \DateTimeInterface|int $delay
     * @param string $job
     * @param mixed $data
     * @return mixed
     */
    public function laterOn($queue, $delay, $job, $data = '')
    {
        return $this->later($delay, $job, $data, $queue);
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param array $jobs
     * @param mixed $data
     * @param string|null $queue
     * @return void
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        foreach ((array) $jobs as $job) {
            $this->push($job, $data, $queue);
        }
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param \Closure|string|object $job
     * @param string $queue
     * @param mixed $data
     * @return string
     * @throws \ReflectionException
     */
    protected function createPayload($job, $queue, $data = '')
    {
        $payload = $this->createPayloadArray($job, $queue, $data);

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Create a payload array from the given job and data.
     *
     * @param string|object $job
     * @param string $queue
     * @param mixed $data
     * @return array
     * @throws \ReflectionException
     */
    protected function createPayloadArray($job, string $queue, $data = ''): array
    {
        return is_object($job)
            ? $this->createObjectPayload($job, $queue)
            : $this->createStringPayload($job, $queue, $data);
    }

    /**
     * Create a payload for an object-based queue handler.
     *
     * @param object $job
     * @param string $queue
     * @return array
     * @throws \ReflectionException
     */
    protected function createObjectPayload(object $job, string $queue): array
    {
        return $this->withCreatePayloadHooks($queue, [
            'uuid' => (string)Str::uuid(),
            'displayName' => $this->getDisplayName($job),
            'job' => get_class($job),
            'maxTries' => $this->getJobTries($job),
            'maxExceptions' => $job->maxExceptions ?? null,
            'failOnTimeout' => $job->failOnTimeout ?? false,
            'backoff' => $this->getJobBackoff($job),
            'timeout' => $job->timeout ?? null,
            'retryUntil' => $this->getJobExpiration($job),
            'data' => $this->extractJobData($job),
        ]);
    }

    /**
     * Create a typical, string based queue payload array.
     *
     * @param string $job
     * @param string $queue
     * @param mixed $data
     * @return array
     */
    protected function createStringPayload(string $job, string $queue, $data): array
    {
        return $this->withCreatePayloadHooks($queue, [
            'uuid' => (string)Str::uuid(),
            'displayName' => is_string($job) ? explode('@', $job)[0] : null,
            'job' => $job,
            'maxTries' => null,
            'maxExceptions' => null,
            'failOnTimeout' => false,
            'backoff' => null,
            'timeout' => null,
            'data' => $data,
        ]);
    }

    /**
     * Get the display name for the given job.
     *
     * @param mixed $job
     * @return string
     */
    protected function getDisplayName($job): string
    {
        return method_exists($job, 'displayName')
            ? $job->displayName() : get_class($job);
    }

    /**
     * Extract data from the job command, excluding queue-specific properties.
     *
     * @param mixed $command
     * @return array
     * @throws \ReflectionException
     */
    protected function extractJobData($command): array
    {
        $data = [];
        $reflection = new \ReflectionClass($command);

        $excludeProperties = [
            'connection',
            'queue',
            'tries',
            'backoff',
            'maxExceptions',
            'failOnTimeout',
            'retryUntil',
        ];

        foreach ($reflection->getProperties() as $property) {
            if (in_array($property->getName(), $excludeProperties)) {
                continue;
            }

            if ($property->isPublic() ||
                ($property->isProtected() && method_exists($command, 'get' . ucfirst($property->getName())))) {
                $property->setAccessible(true);
                $data[$property->getName()] = $property->getValue($command);
            }
        }

        return $data;
    }

    /**
     * Get the maximum number of attempts for an object-based queue handler.
     *
     * @param mixed $job
     * @return mixed
     */
    public function getJobTries($job)
    {
        if (!method_exists($job, 'tries') && !isset($job->tries)) {
            return;
        }

        return $job->tries ?? $job->tries();
    }

    /**
     * Get the backoff for an object-based queue handler.
     *
     * @param mixed $job
     * @return mixed
     */
    public function getJobBackoff($job)
    {
        if (!method_exists($job, 'backoff') && !isset($job->backoff)) {
            return;
        }

        return $job->backoff ?? $job->backoff();
    }

    /**
     * Get the expiration timestamp for an object-based queue handler.
     *
     * @param mixed $job
     * @return mixed
     */
    public function getJobExpiration($job)
    {
        if (!method_exists($job, 'retryUntil') && !isset($job->retryUntil)) {
            return;
        }

        $expiration = $job->retryUntil ?? $job->retryUntil();

        return $expiration instanceof DateTimeInterface
            ? $expiration->getTimestamp() : $expiration;
    }

    /**
     * Create the given payload using any registered payload hooks.
     *
     * @param string $queue
     * @param array $payload
     * @return array
     */
    protected function withCreatePayloadHooks(string $queue, array $payload): array
    {
        if (!empty(static::$createPayloadCallbacks)) {
            foreach (static::$createPayloadCallbacks as $callback) {
                $payload = array_merge($payload, $callback($this->getConnectionName(), $queue, $payload));
            }
        }

        return $payload;
    }

    /**
     * Get the connection name for the queue.
     *
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connectionName ?? 'default';
    }

    /**
     * Set the connection name for the queue.
     *
     * @param string $name
     * @return $this
     */
    public function setConnectionName(string $name)
    {
        $this->connectionName = $name;
        return $this;
    }

    /**
     * Get the container instance being used by the connection.
     *
     * @return mixed
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the IoC container instance.
     *
     * @param mixed $container
     * @return void
     */
    public function setContainer($container)
    {
        $this->container = $container;
    }

    /**
     * Register a callback to be executed when creating job payloads.
     *
     * @param callable|null $callback
     * @return void
     */
    public static function createPayloadUsing($callback)
    {
        if (is_null($callback)) {
            static::$createPayloadCallbacks = [];
        } else {
            static::$createPayloadCallbacks[] = $callback;
        }
    }
}
