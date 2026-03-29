<?php

namespace Elegant\Foundation\Providers;

use Elegant\Contracts\Hook\PreSystem;
use Elegant\Database\MigrationServiceProvider;
use Elegant\Support\ServiceProvider;

class ConsoleSupportServiceProvider extends ServiceProvider implements PreSystem
{
    /**
     * The provider class names.
     *
     * @var array
     */
    protected array $providers = [
        ArtisanServiceProvider::class,
        MigrationServiceProvider::class,
    ];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function preSystem(): void
    {
        foreach ($this->providers as $provider) {
            $instance = new $provider();

            if ($instance instanceof PreSystem) {
                $instance->preSystem();
            }
        }
    }
}
