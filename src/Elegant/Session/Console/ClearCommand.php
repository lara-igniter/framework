<?php

namespace Elegant\Session\Console;

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
    protected string $signature = 'session:clear';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'session:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Clear session files';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        if (config_item('sess_driver') === 'database') {
            app('db')->truncate(config_item('sess_save_path'));
        } else {
            if (!File::exists($storagePath = storage_path('framework/sessions'))) {
                $this->error('Session path not found.');
                return;
            }

            $dir = new DirectoryIterator($storagePath);

            foreach ($dir as $file) {
                if ($file->isFile() && $file->getExtension() !== 'gitignore') {
                    @unlink($file->getPathname());
                }
            }
        }

        $this->info('Application session cleared successfully.');
    }
}

