<?php

namespace Elegant\Database\Console\Migrations;

class ResetCommand extends MigrateCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'migrate:reset';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'migrate:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Rollback all database migrations';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        $this->migrator()->reset();
    }
}
