<?php

namespace Elegant\Cache\Console;

use DirectoryIterator;
use Elegant\Console\Command;
use Elegant\Support\Facades\File;

class ClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'cache:clear';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'cache:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Flush the application cache';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        if (!File::exists($storagePath = storage_path('framework/cache/data'))) {
            $this->error('Cache path not found.');
            return;
        }

        $dir = new DirectoryIterator($storagePath);

        foreach ($dir as $file) {
            if ($file->isFile() && $file->getExtension() !== 'gitignore') {
                @unlink($file->getPathname());
            }
        }

        $this->info('Application cache cleared successfully.');
    }
}

