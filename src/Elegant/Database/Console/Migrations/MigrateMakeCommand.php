<?php

namespace Elegant\Database\Console\Migrations;

use Elegant\Console\OutputStyle;
use Elegant\Support\Facades\File;
use Elegant\Support\Str;

class MigrateMakeCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:migration
                                    {name : The name of the migration}
                                    {--create= : The table to be created}
                                    {--table= : The table to migrate}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new migration file';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Migration';

    /**
     * Execute the console command.
     *
     * @return int|void
     */
    public function handle()
    {
        $name = Str::snake(trim($this->argument('name')));
        $create = $this->option('create') ?: false;
        $table = $this->option('table');

        // If no table was given as an option but a create option is given then we
        // will use the "create" option as the table name. This allows the devs
        // to pass a table name into this option as a short-cut for creating.
        if (!$table && is_string($create)) {
            $table = $create;
            $create = true;
        }

        // Next, we will attempt to guess the table name if this migration has
        // "create" in the name. This will allow us to provide a convenient way
        // of creating migrations that create new tables for the application.
        if (!$table) {
            [$table, $create] = TableGuesser::guess($name);
        }

        $datePrefix = date('YmdHis');
        $filename = $datePrefix . '_' . $name . '.php';
        $filePath = database_path('migrations' . DIRECTORY_SEPARATOR . $filename);

        // Check that a migration with the same logical name does not already exist.
        $existing = File::glob(database_path('migrations' . DIRECTORY_SEPARATOR . '*.php'));
        foreach ($existing as $existingFile) {
            if (Str::endsWith(File::name($existingFile), $name)) {
                $this->error("A migration for [{$name}] already exists.");
                return 1;
            }
        }

        File::ensureDirectoryExists(database_path('migrations'));

        // Handle the special-case sessions-table migration via its own dedicated stub.
        if ($name === 'create_sessions_table') {
            File::put($filePath, File::get($this->resolveStubPath('database.stub')));
            $this->info($this->type . ' [' . $filename . '] created successfully.');
            $this->line('File: ' . OutputStyle::color('database/migrations/' . $filename, 'light_gray'));
            return;
        }

        // Now we are ready to write the migration out to disk. Once we've written
        // the migration out, we will dump-autoload for the entire framework to
        // make sure that the migrations are registered by the class loaders.
        $this->writeMigration($name, $filename, $filePath, $table, $create);
    }

    /**
     * Write the migration file to disk.
     *
     * @param string $name
     * @param string $filename
     * @param string $filePath
     * @param string|null $table
     * @param bool $create
     * @return void
     */
    protected function writeMigration(string $name, string $filename, string $filePath, ?string $table, bool $create): void
    {
        if (is_null($table)) {
            $stubFile = $this->resolveStubPath('migration.stub');
        } elseif ($create) {
            $stubFile = $this->resolveStubPath('migration.create.stub');
        } else {
            $stubFile = $this->resolveStubPath('migration.update.stub');
        }

        $stub = File::get($stubFile);
        $stub = str_replace(['DummyName', '{{ name }}', '{{name}}'], $name, $stub);
        $stub = str_replace(['DummyTable', '{{ table }}', '{{table}}'], $table ?? '', $stub);

        if (!is_null($table) && !$create) {
            $columnName = Str::of($name)->after('add_')->before('_column')->toString();
            $stub = str_replace(['{{ column }}', '{{column}}'], $columnName, $stub);
        }

        File::put($filePath, $stub);

        $this->info($this->type . ' [' . $filename . '] created successfully.');
        $this->line('File: ' . OutputStyle::color('database/migrations/' . $filename, 'light_gray'));
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('migration.create.stub');
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

