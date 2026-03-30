<?php

namespace Elegant\Queue\Console;

use Elegant\Console\Command;
use Elegant\Queue\Worker;
use Elegant\Queue\WorkerOptions;

/**
 * @property \CI_Cache $cache
 */
class WorkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'queue:work
                            {connection? : The name of the queue connection to work}
                            {--name=default : The name of the worker}
                            {--queue= : The names of the queues to work}
                            {--once : Only process the next job on the queue}
                            {--stop-when-empty : Stop when the queue is empty}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping}
                            {--max-time=0 : The maximum number of seconds the worker should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--rest=0 : Number of seconds to rest between jobs}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job before logging it failed}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'queue:work';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Start processing jobs on the queue as a daemon';

    /**
     * The queue worker instance.
     *
     * @var \Elegant\Queue\Worker
     */
    protected Worker $worker;

    /**
     * Create a new queue work command.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->worker = new Worker();
    }

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle(): ?int
    {
        $this->load->driver('cache');

        /** @var \CI_Cache $cache */
        $cache = $this->cache;

        $connection = $this->argument('connection') ?: config('queue.default');

        $queue = $this->getQueue($connection);

        return $this->runWorker($connection, $queue, $cache);
    }

    /**
     * Run the worker instance.
     *
     * @param string $connection
     * @param string $queue
     * @param \CI_Cache $cache
     * @return int|null
     */
    protected function runWorker(string $connection, string $queue, \CI_Cache $cache): ?int
    {
        return $this->worker
            ->setName($this->option('name'))
            ->setCache($cache)
            ->{$this->option('once') ? 'runNextJob' : 'daemon'}(
                $connection, $queue, $this->gatherWorkerOptions()
            );
    }

    /**
     * Gather all of the queue worker options as a single object.
     *
     * @return \Elegant\Queue\WorkerOptions
     */
    protected function gatherWorkerOptions(): WorkerOptions
    {
        return new WorkerOptions(
            (int)$this->option('backoff'),
            (int)$this->option('memory'),
            (int)$this->option('timeout'),
            (int)$this->option('sleep'),
            (int)$this->option('tries'),
            (bool)$this->option('force'),
            (bool)$this->option('stop-when-empty'),
            (int)$this->option('max-jobs'),
            (int)$this->option('max-time'),
            (int)$this->option('rest')
        );
    }

    /**
     * Get the queue name for the worker.
     *
     * @param string $connection
     * @return string
     */
    protected function getQueue(string $connection): string
    {
        return $this->option('queue')
            ?: config("queue.connections.{$connection}.queue", 'default');
    }
}

