<?php

namespace Elegant\Queue;

use Elegant\Queue\Jobs\DatabaseJob;
use Exception;
use Throwable;

class DatabaseQueue extends Queue
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
     * The name of the default queue.
     */
    protected string $default = 'default';

    /**
     * The expiration time of a job.
     */
    protected int $retryAfter = 60;

    public function __construct($database = null, string $table = 'jobs', string $default = 'default', int $retryAfter = 60)
    {
        $this->db = $database ?: app('db');
        $this->table = $table;
        $this->default = $default;
        $this->retryAfter = $retryAfter;
    }

    /**
     * Get the size of the queue.
     *
     * @param string|null $queue
     * @return int
     */
    public function size($queue = null): int
    {
        $queue = $queue ?: $this->default;

        return $this->db->where('queue', $queue)
            ->where('reserved_at IS NULL')
            ->count_all_results($this->table);
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     * @throws \ReflectionException
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            function ($payload, $queue) {
                return $this->pushToDatabase($queue, $payload);
            }
        );
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string|null $queue
     * @param array $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->pushToDatabase($queue, $payload);
    }

    /**
     * Push a new job onto the queue after (n) seconds.
     *
     * @param \DateTimeInterface|int $delay
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     * @throws \ReflectionException
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            $delay,
            function ($payload, $queue, $delay) {
                return $this->pushToDatabase($queue, $payload, $delay);
            }
        );
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param array $jobs
     * @param mixed $data
     * @param string|null $queue
     * @return void
     * @throws \ReflectionException
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        $queue = $this->getQueue($queue);
        $now = time();

        $records = [];
        foreach ((array) $jobs as $job) {
            $payload = $this->createPayload($job, $queue, $data);
            $availableAt = isset($job->delay) ? $now + $job->delay : $now;

            $records[] = $this->buildDatabaseRecord($queue, $payload, $availableAt);
        }

        if (!empty($records)) {
            $this->db->insert_batch($this->table, $records);
        }
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     * @return \Elegant\Queue\Jobs\DatabaseJob|null
     */
    public function pop($queue = null): ?DatabaseJob
    {
        $queue = $this->getQueue($queue);

        $job = $this->getNextAvailableJob($queue);

        if ($job) {
            $this->markJobAsReserved($job['id']);
            return new DatabaseJob($this, $job, $this->connectionName, $queue);
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
     * Release a reserved job back onto the queue after (n) seconds.
     *
     * @param string $queue
     * @param array $job
     * @param int $delay
     * @return mixed
     */
    public function release(string $queue, array $job, int $delay)
    {
        return $this->pushToDatabase($queue, $job['payload'], $delay, $job['attempts']);
    }

    /**
     * Push a raw payload to the database with a given delay.
     *
     * @param string|null $queue
     * @param string $payload
     * @param int $delay
     * @param int $attempts
     * @return mixed
     */
    protected function pushToDatabase(?string $queue, string $payload, int $delay = 0, int $attempts = 0)
    {
        $availableAt = $delay > 0 ? time() + $delay : time();

        $record = $this->buildDatabaseRecord(
            $this->getQueue($queue),
            $payload,
            $availableAt,
            $attempts
        );

        $this->db->insert($this->table, $record);

        return $this->db->insert_id();
    }

    /**
     * Create an array to insert for the given job.
     *
     * @param string $queue
     * @param string $payload
     * @param int $availableAt
     * @param int $attempts
     * @return array
     */
    protected function buildDatabaseRecord(string $queue, string $payload, int $availableAt, int $attempts = 0): array
    {
        return [
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => $attempts,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => time(),
        ];
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
                'failed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            //
        }
    }

    /**
     * Get the queue or return the default.
     *
     * @param string|null $queue
     * @return string
     */
    protected function getQueue(?string $queue): string
    {
        return $queue ?: $this->default;
    }

    /**
     * Enqueue a job using the given callback.
     *
     * @param \Closure|string|object $job
     * @param string $payload
     * @param string $queue
     * @param \DateTimeInterface|int|null $delay
     * @param callable $callback
     * @return mixed
     */
    protected function enqueueUsing($job, string $payload, string $queue, $delay, callable $callback)
    {
        if ($this->shouldDispatchAfterCommit($job) && $this->container && method_exists($this->container, 'bound')) {
            return $callback($payload, $queue, $delay);
        }

        return $callback($payload, $queue, $delay);
    }

    /**
     * Determine if the job should be dispatched after all database transactions have committed.
     *
     * @param \Closure|string|object $job
     * @return bool
     */
    protected function shouldDispatchAfterCommit($job): bool
    {
        if (is_object($job) && isset($job->afterCommit)) {
            return $job->afterCommit;
        }

        if (isset($this->dispatchAfterCommit)) {
            return $this->dispatchAfterCommit;
        }

        return false;
    }
}
