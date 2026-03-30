<?php

namespace Elegant\Queue\Console;

use Elegant\Console\Command;
use Elegant\Support\InteractsWithTime;

/**
 * @property \CI_Cache $cache
 */
class RestartCommand extends Command
{
    use InteractsWithTime;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'queue:restart';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'queue:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Restart queue worker daemons after their current job';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->load->driver('cache');

        /** @var \CI_Cache $cache */
        $cache = $this->cache;
        $cache->file->save('elegant_queue_restart', $this->currentTime(), 86400);

        $this->info('Broadcasting queue restart signal.');
        $this->newLine();
    }
}
