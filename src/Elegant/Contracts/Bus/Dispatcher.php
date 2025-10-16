<?php

namespace Elegant\Contracts\Bus;

interface Dispatcher
{
    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param mixed $command
     * @return mixed
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function dispatch($command);

    /**
     * Dispatch a job using SyncJob for immediate execution.
     *
     * @param mixed $command
     * @throws \Exception
     */
    public function dispatchSync($command);

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @param mixed $command
     * @return mixed
     */
    public function dispatchNow($command);
}
