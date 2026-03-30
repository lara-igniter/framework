<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;

class OptimizeClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'optimize:clear';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'optimize:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Remove the cached bootstrap files';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        $this->call('view:clear');
        $this->call('cache:clear');

        $this->info('Caches cleared successfully.');
    }
}
