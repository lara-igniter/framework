<?php

namespace Elegant\Database\Console\Migrations;

use Elegant\Console\OutputStyle;
use Elegant\Database\Migrations\MigrationCreator;
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
     * The migration creator instance.
     *
     * @var \Elegant\Database\Migrations\MigrationCreator
     */
    protected MigrationCreator $creator;

    public function __construct()
    {
        parent::__construct();

        $this->creator = new MigrationCreator(base_path('stubs'));
    }

    /**
     * Execute the console command.
     *
     * @return int|void
     */
    public function handle()
    {
        $name = Str::snake(trim($this->argument('name')));
        $table = $this->option('table');
        $create = $this->option('create') ?: false;

        // If no table was given as an option but a create option is given then we
        // will use the "create" option as the table name. This allows the devs
        // to pass a table name into this option as a short-cut for creating.
        if (! $table && is_string($create)) {
            $table = $create;
            $create = true;
        }

        // Next, we will attempt to guess the table name if this migration has
        // "create" in the name. This will allow us to provide a convenient way
        // of creating migrations that create new tables for the application.
        if (! $table) {
            [$table, $create] = TableGuesser::guess($name);
        }

        $this->writeMigration($name, $table, $create);
    }

    /**
     * Write the migration file to disk.
     *
     * @param  string  $name
     * @param  string|null  $table
     * @param  bool  $create
     * @return void
     */
    protected function writeMigration(string $name, ?string $table, bool $create): void
    {
        $file = $this->creator->create($name, $this->getMigrationPath(), $table, $create);

        $filename = basename($file);

        $this->info($this->type . ' [' . $filename . '] created successfully.');
        $this->line('File: ' . OutputStyle::color('database/migrations/' . $filename, 'light_gray'));
    }
}

