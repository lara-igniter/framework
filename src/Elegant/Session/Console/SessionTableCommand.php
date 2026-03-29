<?php

namespace Elegant\Session\Console;

use Elegant\Console\OutputStyle;
use Elegant\Database\Console\Migrations\BaseCommand;
use Elegant\Support\Facades\File;

class SessionTableCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'session:table';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'session:table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a migration for the session database table';

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
        $filename = date('YmdHis') . '_create_sessions_table.php';
        $filePath = $path . DIRECTORY_SEPARATOR . $filename;

        File::ensureDirectoryExists($path);

        File::put($filePath, File::get($this->resolveStubPath('database.stub')));

        $this->info($this->type . ' [' . $filename . '] created successfully.');
        $this->line('File: ' . OutputStyle::color('database/migrations/' . $filename, 'light_gray'));
    }

    /**
     * Resolve the stub path, checking for a published override first.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath(string $stub): string
    {
        $published = base_path('stubs' . DIRECTORY_SEPARATOR . $stub);

        return file_exists($published) ? $published : __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . $stub;
    }
}

