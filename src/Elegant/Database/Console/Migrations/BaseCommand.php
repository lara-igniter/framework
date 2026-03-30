<?php

namespace Elegant\Database\Console\Migrations;

use Elegant\Console\Command;
use Elegant\Database\Migrations\Migrator;

class BaseCommand extends Command
{
    /**
     * The migrator instance (lazily resolved).
     *
     * @var \Elegant\Database\Migrations\Migrator|null
     */
    protected ?Migrator $migrator = null;

    /**
     * Get the migrator instance, creating it lazily if needed.
     *
     * @return \Elegant\Database\Migrations\Migrator
     */
    protected function migrator(): Migrator
    {
        return $this->migrator ??= new Migrator();
    }

    /**
     * Get all of the migration paths.
     *
     * @return array
     */
    protected function getMigrationPaths(): array
    {
        // Here, we will check to see if a path option has been defined. If it has we will
        // use the path relative to the root of the installation folder so our database
        // migrations may be run for any customized path from within the application.
        if ($pathOption = $this->option('path')) {
            $paths = is_array($pathOption) ? $pathOption : [$pathOption];

            return array_map(function (string $path) {
                return $this->usingRealPath()
                    ? $path
                    : base_path($path);
            }, $paths);
        }

        return [$this->getMigrationPath()];
    }

    /**
     * Determine if the given path(s) are pre-resolved "real" paths.
     *
     * @return bool
     */
    protected function usingRealPath(): bool
    {
        return (bool)$this->option('realpath');
    }

    /**
     * Get the path to the migration directory.
     *
     * @return string
     */
    protected function getMigrationPath(): string
    {
        return database_path('migrations');
    }
}
