<?php

namespace Elegant\Foundation\Providers;

use Elegant\Console\Kernel;
use Elegant\Contracts\Hook\PreSystem;
use Elegant\Foundation\Console\ConsoleMakeCommand;
use Elegant\Foundation\Console\VendorPublishCommand;
use Elegant\Support\ServiceProvider;

class ArtisanServiceProvider extends ServiceProvider implements PreSystem
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected array $commands = [

    ];

    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected array $devCommands = [
        'ConsoleMake' => ConsoleMakeCommand::class,
        'VendorPublish' => VendorPublishCommand::class,
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

        $this->registerCommands(array_merge(
            $this->commands, $this->devCommands
        ));
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
    protected function registerConsoleMakeCommand()
    {
        Kernel::registerCommand(ConsoleMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerVendorPublishCommand(): void
    {
        Kernel::registerCommand(VendorPublishCommand::class);
    }
}
