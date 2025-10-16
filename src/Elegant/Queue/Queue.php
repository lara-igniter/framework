<?php

namespace Elegant\Queue;

use DateTimeInterface;
use Elegant\Support\Str;
use Exception;
use Throwable;

class Queue
{
    /**
     * The database connection instance.
     */
    protected $db;

    /**
     * The table name for storing jobs.
     */
    protected string $table = 'jobs';

    /**
     * The connection name for the queue.
     *
     * @var string
     */
    protected string $connectionName;

    /**
     * The creation payload callbacks.
     *
     * @var callable[]
     */
    protected static array $createPayloadCallbacks = [];

    public function __construct()
    {
        $this->db = app('db');
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string $job
     * @param mixed $data
     * @param string $queue
     * @param int $delay
     * @return mixed
     * @throws \ReflectionException
     */
    public function push(string $job, $data = '', string $queue = 'default', int $delay = 0)
    {
        $payload = $this->createPayload($job, $data, $queue);
        $availableAt = $delay > 0 ? time() + $delay : time();

        return $this->db->insert($this->table, [
            'queue' => $queue,
            'payload' => json_encode($payload),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => time()
        ]);
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param \Closure|string|object $job
     * @param mixed $data
     * @param string $queue
     * @return array
     * @throws \ReflectionException
     */
    protected function createPayload($job, $data, string $queue)
    {
        // If $job is a string and $data is an object (our case),
        // then $data is actually the job object
        if (is_string($job) && is_object($data)) {
            return $this->createObjectPayload($data, $queue);
        }

        // If $job is an object, use it directly
        if (is_object($job)) {
            return $this->createObjectPayload($job, $queue);
        }

        // Otherwise create string payload
        return $this->createStringPayload($job, $queue, $data);
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

        if (is_null($tries = $job->tries ?? $job->tries())) {
            return;
        }

        return $tries;
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

        if (is_null($backoff = $job->backoff ?? $job->backoff())) {
            return;
        }

        return $backoff;
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
            'displayName' => is_string($job) ? end(explode('\\', $job)[0]) : null,
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
     * Pop the next job off of the queue.
     *
     * @param string $queue
     * @return \Elegant\Queue\Jobs\DatabaseJob|null
     */
    public function pop(string $queue = 'default'): ?Jobs\DatabaseJob
    {
        $job = $this->getNextAvailableJob($queue);

        if ($job) {
            $this->markJobAsReserved($job['id']);

            return new Jobs\DatabaseJob($this, $job);
        }

        return null;
    }

    /**
     * Get the next available job for the given queue.
     *
     * @param string $queue
     * @return array|null
     */
    protected function getNextAvailableJob(string $queue): ?array
    {
        return $this->db->where('queue', $queue)
            ->where('reserved_at IS NULL')
            ->where('available_at <=', time())
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Mark the given job ID as reserved.
     *
     * @param int $id
     * @return void
     */
    protected function markJobAsReserved(int $id)
    {
        $this->db->where('id', $id)
            ->update($this->table, ['reserved_at' => time()]);
    }

    /**
     * Delete a job from the queue.
     *
     * @param int $id
     * @return void
     */
    public function deleteJob(int $id)
    {
        $this->db->where('id', $id)->delete($this->table);
    }

    /**
     * Release a job back to the queue.
     *
     * @param int $id
     * @param int $delay
     * @return void
     */
    public function releaseJob(int $id, int $delay = 0)
    {
        $availableAt = $delay > 0 ? time() + $delay : time();

        $this->db->where('id', $id)
            ->set('reserved_at', null)
            ->set('attempts', 'attempts + 1', false)
            ->set('available_at', $availableAt)
            ->update($this->table);
    }

    /**
     * Increment the attempts for a job.
     *
     * @param int $id
     * @return void
     */
    public function incrementAttempts(int $id)
    {
        $this->db->where('id', $id)
            ->set('attempts', 'attempts + 1', false)
            ->update($this->table);
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
    public function setConnectionName(string $name): Queue
    {
        $this->connectionName = $name;
        return $this;
    }

    /**
     * Log a failed job to the failed_jobs table.
     *
     * @param string $connectionName
     * @param string $queue
     * @param string $payload
     * @param Exception|Throwable $exception
     * @return void
     */
    public function logFailedJob(string $connectionName, string $queue, string $payload, $exception)
    {
        try {
            $this->db->insert('failed_jobs', [
                'connection' => $connectionName,
                'queue' => $queue,
                'payload' => $payload,
                'exception' => (string)$exception,
                'failed_at' => now()->toDateTimeString(),
            ]);
        } catch (Exception $e) {
            //
        }
    }
}
