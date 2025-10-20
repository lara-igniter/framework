<?php

namespace Elegant\Queue;

use Elegant\Queue\Jobs\DatabaseJob;
use Elegant\Queue\Jobs\DatabaseJobRecord;
use Elegant\Support\Carbon;
use Exception;
use stdClass;
use Throwable;

class DatabaseQueue extends Queue
{
    /**
     * The database connection instance.
     */
    protected $database;

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

    public function __construct($database = null,
                                string $table = 'jobs',
                                string $default = 'default',
                                int $retryAfter = 60)
    {
        $this->database = $database ?: app('db');
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
        return $this->database->where('queue', $this->getQueue($queue))
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

        $now = $this->availableAt();

        $records = [];
        foreach ((array)$jobs as $job) {
            $records[] = $this->buildDatabaseRecord(
                $queue,
                $this->createPayload($job, $this->getQueue($queue), $data),
                isset($job->delay) ? $this->availableAt($job->delay) : $now,
            );
        }

        if (!empty($records)) {
            $this->database->insert_batch($this->table, $records);
        }
    }

    /**
     * Release a reserved job back onto the queue after (n) seconds.
     *
     * @param string $queue
     * @param \stdClass $job
     * @param int $delay
     * @return mixed
     */
    public function release(string $queue, stdClass $job, int $delay)
    {
        return $this->pushToDatabase($queue, $job->payload, $delay, $job->attempts);
    }

    /**
     * Push a raw payload to the database with a given delay of (n) seconds.
     *
     * @param string|null $queue
     * @param string $payload
     * @param int $delay
     * @param int $attempts
     * @return mixed
     */
    protected function pushToDatabase(?string $queue, string $payload, int $delay = 0, int $attempts = 0)
    {
        $this->database->insert($this->table, $this->buildDatabaseRecord(
            $this->getQueue($queue), $payload, $this->availableAt($delay), $attempts
        ));

        return $this->database->insert_id();
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
            'attempts' => $attempts,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => $this->currentTime(),
            'payload' => $payload,
        ];
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

        if ($job = $this->getNextAvailableJob($queue)) {
            return $this->marshalJob($queue, $job);
        }

        return null;
    }

    /**
     * Get the next available job for the given queue.
     *
     * @param string $queue
     * @return \Elegant\Queue\Jobs\DatabaseJobRecord|null
     */
    protected function getNextAvailableJob(string $queue): ?DatabaseJobRecord
    {
        $expiration = Carbon::now()->subSeconds($this->retryAfter)->getTimestamp();

        $job = $this->database
            ->where('queue', $this->getQueue($queue))
            ->group_start()
            ->where('reserved_at IS NULL')
            ->where('available_at <=', $this->currentTime())
            ->group_end()
            ->or_group_start()
            ->where('reserved_at <=', $expiration)
            ->group_end()
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get($this->table)
            ->row_array();

        return $job ? new DatabaseJobRecord((object)$job) : null;
    }

    /**
     * Marshal the reserved job into a DatabaseJob instance.
     *
     * @param string $queue
     * @param \Elegant\Queue\Jobs\DatabaseJobRecord $job
     * @return \Elegant\Queue\Jobs\DatabaseJob
     */
    protected function marshalJob(string $queue, DatabaseJobRecord $job): DatabaseJob
    {
        $job = $this->markJobAsReserved($job);

        return new DatabaseJob(
            $this, $job, $this->connectionName, $queue
        );
    }

    /**
     * Mark the given job ID as reserved.
     *
     * @param \Elegant\Queue\Jobs\DatabaseJobRecord $job
     * @return \Elegant\Queue\Jobs\DatabaseJobRecord
     */
    protected function markJobAsReserved(DatabaseJobRecord $job): DatabaseJobRecord
    {
        $this->database->where('id', $job->id)
            ->update($this->table, ['reserved_at' => $job->touch()]);

        return $job;
    }

    /**
     * Delete a reserved job from the queue.
     *
     * @param int $id
     * @return void
     */
    public function deleteReserved(int $id)
    {
        $this->database->where('id', $id)->delete($this->table);
    }

    /**
     * Delete a reserved job from the reserved queue and release it.
     *
     * @param string $queue
     * @param \Elegant\Queue\Jobs\DatabaseJob $job
     * @param int $delay
     * @return void
     */
    public function deleteAndRelease(string $queue, DatabaseJob $job, int $delay)
    {
        if ($this->database->where('id', $job->getJobId())->get($this->table)) {
            $this->database->where('id', $job->getJobId())->delete($this->table);
        }

        $this->releaseReserved($job, $delay);
    }

    /**
     * Release a job back to the queue.
     *
     * @param \Elegant\Queue\Jobs\DatabaseJob $job
     * @param int $delay
     * @return void
     */
    public function releaseReserved(DatabaseJob $job, int $delay = 0)
    {
        $this->job->attempts = $this->job->attempts + 1;

        $this->database->where('id', $job->getJobId())
            ->set('reserved_at', null)
            ->set('attempts', 'attempts + 1', false)
            ->set('available_at', $this->availableAt($delay))
            ->update($this->table);
    }

    /**
     * Delete all of the jobs from the queue.
     *
     * @param string $queue
     * @return int
     */
    public function clear(string $queue): int
    {
        return $this->database->table($this->table)->where('queue', $this->getQueue($queue))->delete($this->table);
    }

    /**
     * Get the queue or return the default.
     *
     * @param string|null $queue
     * @return string
     */
    public function getQueue(?string $queue): string
    {
        return $queue ?: $this->default;
    }

    /**
     * Get the underlying database instance.
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * TODO: Remove function bellow
     */

    /**
     * Increment the attempts for a job.
     *
     * @param int $id
     * @return void
     */
    public function incrementAttempts(int $id)
    {
        $this->database->where('id', $id)
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
            $this->database->insert('failed_jobs', [
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
}
