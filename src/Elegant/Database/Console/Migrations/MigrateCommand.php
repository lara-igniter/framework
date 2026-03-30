<?php

namespace Elegant\Database\Console\Migrations;

class MigrateCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'migrate
                                    {version? : Target migration version}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Run the database migrations';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->migrator()->run($this->argument('version'));
    }
}
