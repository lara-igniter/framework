<?php

namespace Elegant\Foundation\Providers;

use Elegant\Console\Kernel;
use Elegant\Contracts\Hook\PreSystem;
use Elegant\Database\Console\Factories\FactoryMakeCommand;
use Elegant\Foundation\Console\ConsoleMakeCommand;
use Elegant\Foundation\Console\ControllerMakeCommand;
use Elegant\Foundation\Console\JobMakeCommand;
use Elegant\Foundation\Console\MailMakeCommand;
use Elegant\Foundation\Console\MiddlewareMakeCommand;
use Elegant\Foundation\Console\ModelMakeCommand;
use Elegant\Foundation\Console\PolicyMakeCommand;
use Elegant\Foundation\Console\ProviderMakeCommand;
use Elegant\Foundation\Console\RepositoryMakeCommand;
use Elegant\Foundation\Console\RequestMakeCommand;
use Elegant\Foundation\Console\ResourceMakeCommand;
use Elegant\Foundation\Console\RuleMakeCommand;
use Elegant\Foundation\Console\ScopeMakeCommand;
use Elegant\Foundation\Console\SeederMakeCommand;
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
        'ControllerMake' => ControllerMakeCommand::class,
        'FactoryMake' => FactoryMakeCommand::class,
        'JobMake' => JobMakeCommand::class,
        'MailMake' => MailMakeCommand::class,
        'MiddlewareMake' => MiddlewareMakeCommand::class,
        'ModelMake' => ModelMakeCommand::class,
        'PolicyMake' => PolicyMakeCommand::class,
        'ProviderMake' => ProviderMakeCommand::class,
        'RepositoryMake' => RepositoryMakeCommand::class,
        'RequestMake' => RequestMakeCommand::class,
        'ResourceMake' => ResourceMakeCommand::class,
        'RuleMake' => RuleMakeCommand::class,
        'ScopeMake' => ScopeMakeCommand::class,
        'SeederMake' => SeederMakeCommand::class,
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
    protected function registerControllerMakeCommand()
    {
        Kernel::registerCommand(ControllerMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerFactoryMakeCommand()
    {
        Kernel::registerCommand(FactoryMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerJobMakeCommand()
    {
        Kernel::registerCommand(JobMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMailMakeCommand()
    {
        Kernel::registerCommand(MailMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMiddlewareMakeCommand()
    {
        Kernel::registerCommand(MiddlewareMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerModelMakeCommand()
    {
        Kernel::registerCommand(ModelMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerPolicyMakeCommand()
    {
        Kernel::registerCommand(PolicyMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerProviderMakeCommand()
    {
        Kernel::registerCommand(ProviderMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerRepositoryMakeCommand()
    {
        Kernel::registerCommand(RepositoryMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerRequestMakeCommand()
    {
        Kernel::registerCommand(RequestMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerResourceMakeCommand()
    {
        Kernel::registerCommand(ResourceMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerRuleMakeCommand()
    {
        Kernel::registerCommand(RuleMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerScopeMakeCommand()
    {
        Kernel::registerCommand(ScopeMakeCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerSeederMakeCommand()
    {
        Kernel::registerCommand(SeederMakeCommand::class);
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
