<?php

namespace Elegant\Queue;

class QueueManager
{
    /**
     * The array of resolved queue connections.
     *
     * @var array
     */
    protected array $connections = [];

    /**
     * The default connection name.
     *
     * @var string
     */
    protected string $default = 'database';

    /**
     * Create a new queue manager instance.
     */
    public function __construct()
    {
        $this->connections['database'] = $this->createDatabaseQueue();
        $this->connections['sync'] = $this->createSyncQueue();
        $this->connections['default'] = $this->connections['database'];
    }

    /**
     * Create a database queue instance.
     *
     * @return \Elegant\Queue\DatabaseQueue
     */
    protected function createDatabaseQueue(): DatabaseQueue
    {
        return (new DatabaseQueue())->setConnectionName('database');
    }

    /**
     * Create a sync queue instance.
     *
     * @return \Elegant\Queue\SyncQueue
     */
    protected function createSyncQueue(): SyncQueue
    {
        return (new SyncQueue())->setConnectionName('sync');
    }

    /**
     * Resolve a queue connection instance.
     *
     * @param string|null $name
     * @return \Elegant\Queue\Queue
     */
    public function connection(string $name = null): Queue
    {
        $name = $name ?: $this->getDefaultDriver();

        // If the connection has not been resolved yet we will resolve it now
        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Create a queue connection based on the name.
     *
     * @param string $name
     * @return \Elegant\Queue\Queue
     */
    protected function createConnection(string $name): Queue
    {
        switch ($name) {
            case 'database':
                return $this->createDatabaseQueue();
            default:
                return $this->createSyncQueue();
        }
    }

    /**
     * Get the name of the default queue connection.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->default;
    }

    /**
     * Set the name of the default queue connection.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver(string $name): void
    {
        $this->default = $name;
    }

    /**
     * Get the full name for the given connection.
     *
     * @param string|null $connection
     * @return string
     */
    public function getName(string $connection = null): string
    {
        return $connection ?: $this->getDefaultDriver();
    }

    /**
     * Dynamically pass calls to the default connection.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->connection()->$method(...$parameters);
    }
}
