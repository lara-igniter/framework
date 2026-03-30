<?php

namespace Elegant\Database;

use Elegant\Console\Kernel;
use Elegant\Contracts\Hook\PreSystem;
use Elegant\Database\Console\Migrations\FreshCommand;
use Elegant\Database\Console\Migrations\MigrateCommand;
use Elegant\Database\Console\Migrations\MigrateMakeCommand;
use Elegant\Database\Console\Migrations\RefreshCommand;
use Elegant\Database\Console\Migrations\ResetCommand;
use Elegant\Database\Console\Migrations\RollbackCommand;
use Elegant\Database\Console\Migrations\StatusCommand;
use Elegant\Support\ServiceProvider;

class MigrationServiceProvider extends ServiceProvider implements PreSystem
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected array $commands = [
        'Migrate' => MigrateCommand::class,
        'MigrateFresh' => FreshCommand::class,
        'MigrateRefresh' => RefreshCommand::class,
        'MigrateReset' => ResetCommand::class,
        'MigrateRollback' => RollbackCommand::class,
        'MigrateStatus' => StatusCommand::class,
        'MigrateMake' => MigrateMakeCommand::class,
    ];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function preSystem(): void
    {
        if (!is_cli()) {
            return;
        }

        $this->registerCommands($this->commands);
    }

    /**
     * Register the given commands.
     *
     * @param array $commands
     * @return void
     */
    protected function registerCommands(array $commands): void
    {
        foreach (array_keys($commands) as $command) {
            $this->{"register{$command}Command"}();
        }
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateCommand()
    {
        Kernel::registerCommand(MigrateCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateFreshCommand()
    {
        Kernel::registerCommand(FreshCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateRefreshCommand()
    {
        Kernel::registerCommand(RefreshCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateResetCommand()
    {
        Kernel::registerCommand(ResetCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateRollbackCommand()
    {
        Kernel::registerCommand(RollbackCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateStatusCommand()
    {
        Kernel::registerCommand(StatusCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateMakeCommand()
    {
        Kernel::registerCommand(MigrateMakeCommand::class);
    }
}
