<?php

namespace Elegant\Foundation\Console;

use DirectoryIterator;
use Elegant\Console\Command;
use Elegant\Support\Facades\File;

class LogClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'log:clear';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'log:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Clear log files';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        if (!File::exists($storagePath = storage_path('logs'))) {
            $this->error('Logs path not found.');
            return;
        }

        $dir = new DirectoryIterator($storagePath);

        foreach ($dir as $file) {
            if ($file->isFile() && $file->getExtension() !== 'gitignore') {
                @unlink($file->getPathname());
            }
        }

        $this->info('Application logs cleared successfully.');
    }
}

