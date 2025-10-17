<?php

namespace Elegant\Bus;

use Elegant\Contracts\Bus\Dispatcher as DispatcherContract;
use Elegant\Queue\Jobs\SyncJob;
use Elegant\Queue\QueueManager;
use ReflectionClass;

class Dispatcher implements DispatcherContract
{
    /**
     * The queue manager instance.
     *
     * @var \Elegant\Queue\QueueManager
     */
    protected QueueManager $queueManager;

    /**
     * Create a new queue dispatcher instance.
     *
     * @param \Elegant\Queue\QueueManager|null $queueManager
     */
    public function __construct(QueueManager $queueManager = null)
    {
        $this->queueManager = $queueManager ?: new QueueManager();
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
     * @return mixed
     * @throws \Exception
     */
    public function dispatchSync($command)
    {
        $payload = json_encode([
            'job' => get_class($command),
            'data' => $this->extractJobData($command),
        ], JSON_UNESCAPED_UNICODE);

        $syncJob = new SyncJob($payload);

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

        $excludeProperties = ['connection', 'queue', 'delay', 'timeout', 'tries', 'connectionName', 'shouldFail'];

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
        $queueName = $command->queue ?? 'default';

        // Get delay from the job instance if it was set via delay() method
        $delay = 0;
        if (property_exists($command, 'delay') && $command->delay > 0) {
            $delay = $command->delay;
        }

        // Use the queue manager to get the appropriate queue connection
        $queue = $this->queueManager->connection();

        return $queue->push(get_class($command), $command, $queueName, $delay);
    }

    /**
     * Get the queue manager instance.
     *
     * @return \Elegant\Queue\QueueManager
     */
    public function getQueueManager(): QueueManager
    {
        return $this->queueManager;
    }

    /**
     * Set the queue manager instance.
     *
     * @param \Elegant\Queue\QueueManager $queueManager
     * @return $this
     */
    public function setQueueManager(QueueManager $queueManager)
    {
        $this->queueManager = $queueManager;
        return $this;
    }
}
