<?php

namespace Elegant\Database\Console\Migrations;

use Elegant\Database\Seeder;

class FreshCommand extends MigrateCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'migrate:fresh
                                    {--seed : Seed the database after every migration operation}
                                    {--seeder= : The class name of the root seeder}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'migrate:fresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Drop all tables and re-run all migrations';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        $this->migrator()->fresh();

        if ($this->option('seed')) {
            $class = $this->option('seeder') ?? 'Database\\Seeders\\DatabaseSeeder';

            (new Seeder)->call($class);
        }
    }
}
