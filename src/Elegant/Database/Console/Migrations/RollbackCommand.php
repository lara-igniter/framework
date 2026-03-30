<?php

namespace Elegant\Database\Console\Migrations;
class RollbackCommand extends MigrateCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'migrate:rollback
                                    {--step= : The number of migrations to be reverted}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'migrate:rollback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Rollback the last database migration';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        $step = $this->option('step');

        if (is_null($step)) {
            $this->migrator()->rollback();
            return;
        }

        $this->migrator()->rollbackBySteps((int)$step);
    }
}
