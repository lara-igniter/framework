<?php

namespace Elegant\Foundation\Providers;

use Elegant\Cache\Console\ClearCommand as CacheClearCommand;
use Elegant\Console\Kernel;
use Elegant\Contracts\Hook\PreSystem;
use Elegant\Database\Console\Factories\FactoryMakeCommand;
use Elegant\Database\Console\Seeds\SeedCommand;
use Elegant\Database\Console\Seeds\SeederMakeCommand;
use Elegant\Foundation\Console\LogClearCommand;
use Elegant\Foundation\Console\ViewClearCommand;
use Elegant\Foundation\Console\ConsoleMakeCommand;
use Elegant\Foundation\Console\ControllerMakeCommand;
use Elegant\Foundation\Console\DownCommand;
use Elegant\Foundation\Console\EnvironmentCommand;
use Elegant\Foundation\Console\JobMakeCommand;
use Elegant\Foundation\Console\KeyGenerateCommand;
use Elegant\Foundation\Console\ListCommand;
use Elegant\Foundation\Console\MailMakeCommand;
use Elegant\Foundation\Console\MiddlewareMakeCommand;
use Elegant\Foundation\Console\ModelMakeCommand;
use Elegant\Foundation\Console\OptimizeClearCommand;
use Elegant\Foundation\Console\PolicyMakeCommand;
use Elegant\Foundation\Console\ProviderMakeCommand;
use Elegant\Foundation\Console\RepositoryMakeCommand;
use Elegant\Foundation\Console\RequestMakeCommand;
use Elegant\Foundation\Console\ResourceMakeCommand;
use Elegant\Foundation\Console\RouteListCommand;
use Elegant\Foundation\Console\RuleMakeCommand;
use Elegant\Foundation\Console\ScopeMakeCommand;
use Elegant\Foundation\Console\StorageLinkCommand;
use Elegant\Foundation\Console\UpCommand;
use Elegant\Foundation\Console\VendorPublishCommand;
use Elegant\Queue\Console\RestartCommand as QueueRestartCommand;
use Elegant\Queue\Console\WorkCommand as QueueWorkCommand;
use Elegant\Session\Console\ClearCommand as SessionClearCommand;
use Elegant\Session\Console\SessionTableCommand;
use Elegant\Support\ServiceProvider;

class ArtisanServiceProvider extends ServiceProvider implements PreSystem
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected array $commands = [
        'CacheClear' => CacheClearCommand::class,
        'Down' => DownCommand::class,
        'Environment' => EnvironmentCommand::class,
        'KeyGenerate' => KeyGenerateCommand::class,
        // 'List' => ListCommand::class,
        'LogClear' => LogClearCommand::class,
        'OptimizeClear' => OptimizeClearCommand::class,
        'QueueRestart' => QueueRestartCommand::class,
        'QueueWork' => QueueWorkCommand::class,
        'RouteList' => RouteListCommand::class,
        'Seed' => SeedCommand::class,
        'SessionClear' => SessionClearCommand::class,
        'StorageLink' => StorageLinkCommand::class,
        'Up' => UpCommand::class,
        'ViewClear' => ViewClearCommand::class,
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
        'SessionTable' => SessionTableCommand::class,
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
    protected function registerCacheClearCommand()
    {
        Kernel::registerCommand(CacheClearCommand::class);
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
    protected function registerDownCommand()
    {
        Kernel::registerCommand(DownCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerEnvironmentCommand()
    {
        Kernel::registerCommand(EnvironmentCommand::class);
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
    protected function registerKeyGenerateCommand()
    {
        Kernel::registerCommand(KeyGenerateCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerListCommand()
    {
        Kernel::registerCommand(ListCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerLogClearCommand()
    {
        Kernel::registerCommand(LogClearCommand::class);
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
    protected function registerOptimizeClearCommand()
    {
        Kernel::registerCommand(OptimizeClearCommand::class);
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
    protected function registerRouteListCommand()
    {
        Kernel::registerCommand(RouteListCommand::class);
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
    protected function registerSeedCommand()
    {
        Kernel::registerCommand(SeedCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerSessionTableCommand()
    {
        Kernel::registerCommand(SessionTableCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerStorageLinkCommand()
    {
        Kernel::registerCommand(StorageLinkCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerSessionClearCommand()
    {
        Kernel::registerCommand(SessionClearCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerUpCommand()
    {
        Kernel::registerCommand(UpCommand::class);
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

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueRestartCommand()
    {
        Kernel::registerCommand(QueueRestartCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueWorkCommand()
    {
        Kernel::registerCommand(QueueWorkCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerViewClearCommand()
    {
        Kernel::registerCommand(ViewClearCommand::class);
    }
}
