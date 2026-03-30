<?php

namespace Elegant\Database\Console\Migrations;

use Elegant\Database\Seeder;

class RefreshCommand extends MigrateCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'migrate:refresh
                                    {--seed : Seed the database after every migration operation}
                                    {--seeder= : The class name of the root seeder}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'migrate:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Reset and re-run all migrations';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->migrator()->refresh();

        if ($this->option('seed')) {
            $class = $this->option('seeder') ?? 'Database\\Seeders\\DatabaseSeeder';

            (new Seeder)->call($class);
        }
    }
}
