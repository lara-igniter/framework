<?php

namespace Elegant\Database\Console\Migrations;
class StatusCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'migrate:status';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'migrate:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Show the status of each migration';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        $status = $this->migrator()->status();

        if (!$status['table_exists']) {
            $this->error('Migration table not found.');
            return;
        }

        if ($status['version'] === null || (int)$status['version'] === 0) {
            $this->error('No migrations have been run yet.');
            return;
        }

        $this->info("Current version: {$status['version']}");
    }
}
