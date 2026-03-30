<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;
use Elegant\Support\Facades\File;

class UpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'up';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Bring the application out of maintenance mode';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        if (!is_file(storage_path('framework/down'))) {
            $this->warn('Application is already up.');
            return;
        }

        @unlink(storage_path('framework/down'));

        @unlink(storage_path('framework/maintenance.php'));

        $this->warn('Application is now live.');
    }
}

