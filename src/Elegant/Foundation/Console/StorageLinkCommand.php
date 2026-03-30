<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;
use Elegant\Support\Facades\File;

class StorageLinkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'storage:link';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'storage:link';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create the symbolic links configured for the application';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        $links = config('filesystems.links') ?? [public_path('storage') => storage_path('app/public')];
        foreach ($links as $link => $target) {
            if (is_link($link) || @readlink($link) !== false) {
                is_link($link) ? File::delete($link) : @rmdir($link);
            } elseif (file_exists($link)) {
                $this->warn("The [{$link}] link already exists and is not a symbolic link.");
                continue;
            }

            File::link($target, $link);

            $this->info("The [{$link}] link has been connected to [{$target}].");
        }

        $this->info('The links have been created.');
    }
}
