<?php

namespace Elegant\Database;

use Elegant\Console\Kernel;
use Elegant\Contracts\Hook\PreSystem;
use Elegant\Database\Console\Migrations\MigrateMakeCommand;
use Elegant\Support\ServiceProvider;

class MigrationServiceProvider extends ServiceProvider implements PreSystem
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected array $commands = [
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
    protected function registerMigrateMakeCommand()
    {
        Kernel::registerCommand(MigrateMakeCommand::class);
    }
}
