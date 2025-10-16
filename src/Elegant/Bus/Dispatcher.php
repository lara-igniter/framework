<?php

namespace Elegant\Bus;

use Elegant\Contracts\Bus\Dispatcher as DispatcherContract;
use Elegant\Queue\Jobs\SyncJob;
use Elegant\Queue\Queue;
use ReflectionClass;

class Dispatcher implements DispatcherContract
{
    /**
     * The queue instance.
     *
     * @var \Elegant\Queue\Queue
     */
    protected $queue;

    /**
     * Create a new queue dispatcher instance.
     */
    public function __construct()
    {
        $this->queue = new Queue();
    }

    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param mixed $command
     * @return mixed
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function dispatch($command)
    {
        if ($this->shouldRunSynchronously($command)) {
            return $this->dispatchSync($command);
        } elseif ($this->shouldQueue($command)) {
            return $this->dispatchToQueue($command);
        }

        return $this->dispatchNow($command);
    }

    /**
     * Dispatch a job using SyncJob for immediate execution.
     *
     * @param mixed $command
     * @throws \Exception
     */
    public function dispatchSync($command)
    {
        $queue = $command->queue ?? 'default';

        $data = $this->extractJobData($command);

        $syncJob = new SyncJob($command, $data, $queue);

        return $syncJob->fire();
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @param mixed $command
     * @return mixed
     */
    public function dispatchNow($command)
    {
        return $command->handle();
    }

    /**
     * Extract data from the job command.
     *
     * @param mixed $command
     * @return array
     * @throws \ReflectionException
     */
    protected function extractJobData($command): array
    {
        $data = [];

        $reflection = new ReflectionClass($command);

        $excludeProperties = ['queue', 'delay', 'connection', 'timeout'];

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
     * Determine if the job should run synchronously (immediately).
     *
     * @param mixed $command
     * @return bool
     */
    protected function shouldRunSynchronously($command): bool
    {
        if ((property_exists($command, 'connection') && $command->connection === 'sync') ||
            (property_exists($command, 'connectionName') && $command->connectionName === 'sync')) {
            return true;
        }

        $envConnection = config('queue.default') ?? 'sync';

        return $envConnection === 'sync';
    }

    /**
     * Determine if the given command should be queued.
     *
     * @param mixed $command
     * @return bool
     */
    protected function shouldQueue($command): bool
    {
        return in_array(Queueable::class, class_uses_recursive($command));
    }

    /**
     * Dispatch a command to its appropriate handler behind a queue.
     *
     * @param mixed $command
     * @return mixed
     * @throws \ReflectionException
     */
    public function dispatchToQueue($command)
    {
        $queue = $command->queue ?? 'default';

        // Get delay from the job instance if it was set via delay() method
        $delay = 0;
        if (property_exists($command, 'delay') && $command->delay > 0) {
            $delay = $command->delay;
        }

        return $this->queue->push(get_class($command), $command, $queue, $delay);
    }

    /**
     * Get the queue instance.
     *
     * @return \Elegant\Queue\Queue
     */
    public function getQueue(): Queue
    {
        return $this->queue;
    }
}
