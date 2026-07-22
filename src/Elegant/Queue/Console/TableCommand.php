<?php

namespace Elegant\Queue\Console;

use Elegant\Console\OutputStyle;
use Elegant\Database\Console\Migrations\BaseCommand;
use Elegant\Support\Facades\File;

class TableCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'queue:table';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'queue:table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a migration for the queue jobs database table';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Migration';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $path = $this->getMigrationPath();
        $filename = date('YmdHis') . '_create_jobs_table.php';
        $filePath = $path . DIRECTORY_SEPARATOR . $filename;

        File::ensureDirectoryExists($path);

        File::put($filePath, File::get($this->resolveStubPath('jobs.stub')));

        $this->info($this->type . ' [' . $filename . '] created successfully.');
        $this->line('File: ' . OutputStyle::color('database/migrations/' . $filename, 'light_gray'));
    }

    /**
     * Resolve the stub path, checking for a published override first.
     *
     * @param string $stub
     * @return string
     */
    protected function resolveStubPath(string $stub): string
    {
        $published = base_path('stubs' . DIRECTORY_SEPARATOR . $stub);

        return file_exists($published) ? $published : __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . $stub;
    }
}
