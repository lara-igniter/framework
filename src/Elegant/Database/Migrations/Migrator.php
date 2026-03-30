<?php

namespace Elegant\Database\Migrations;

use Elegant\Console\OutputStyle;
use Elegant\Support\Collection;
use Elegant\Support\Str;

class Migrator
{
    private const UP = 'up';
    private const DOWN = 'down';
    private const AUTO = 'auto';
    private const SILENT = 'silent';

    protected bool $libraryLoaded = false;

    protected ?string $migrationTable = null;

    /**
     * @var callable|null
     */
    protected $outputHandler = null;

    /**
     * Run pending migrations up to the given version (or latest).
     */
    public function run(?string $version = null): void
    {
        $this->execute($version, self::UP);
    }

    /**
     * Rollback migrations to the given version.
     */
    public function rollback(?string $version = null): void
    {
        $this->execute($version, self::DOWN);
    }

    /**
     * Rollback all migrations back to version 0.
     */
    public function reset(): void
    {
        $this->execute('0', self::DOWN);
    }

    /**
     * Drop to version 0 silently, then run all migrations from scratch.
     */
    public function fresh(): void
    {
        $this->execute('0', self::SILENT);
        $this->execute(null, self::AUTO);
    }

    /**
     * Reset and re-run all migrations with full rollback + migrate output.
     */
    public function refresh(): void
    {
        $this->execute('0', self::AUTO);
        $this->execute(null, self::AUTO);
    }

    /**
     * Rollback by a specific number of migration steps.
     */
    public function rollbackBySteps(int $steps): void
    {
        $this->boot();

        $currentVersion = $this->getCurrentVersion();
        $allMigrations = Collection::make($this->getMigrations());

        if (!$allMigrations->has($currentVersion)) {
            if ((int)$currentVersion === 0) {
                $this->line('Nothing to migrate.', 'green');
                return;
            }

            $this->execute('0', self::DOWN);
            return;
        }

        $migrations = $allMigrations->takeUntil(
            fn($item, $key) => (int)$key > (int)$currentVersion
        );

        $count = $migrations->count();
        $adjusted = $steps + 1;

        $targetVersion = $adjusted > $count
            ? '0'
            : (string)$migrations->keys()->get($count - $adjusted);

        $this->execute($targetVersion, self::DOWN);
    }

    /**
     * Return migration status information.
     *
     * @return array{table_exists: bool, version: string|null, migrations: array}
     */
    public function status(): array
    {
        $this->boot();

        $table = $this->getMigrationTableName();

        if (!app('db')->table_exists($table)) {
            return ['table_exists' => false, 'version' => null, 'migrations' => []];
        }

        return [
            'table_exists' => true,
            'version' => $this->getCurrentVersion(),
            'migrations' => $this->getMigrations(),
        ];
    }

    /**
     * Determine if any migrations have been run.
     */
    public function hasRunAnyMigrations(): bool
    {
        return (int)$this->getCurrentVersion() > 0;
    }

    /**
     * Get the current migration version from the database.
     */
    public function getCurrentVersion(): string
    {
        $this->boot();

        return (string)app('db')
            ->get($this->getMigrationTableName())
            ->result()[0]->version;
    }

    /**
     * Get all available migration files indexed by version.
     */
    public function getMigrations(): array
    {
        $this->boot();

        return app('migration')->find_migrations();
    }

    /**
     * Get the migration table name.
     */
    public function getMigrationTableName(): string
    {
        if ($this->migrationTable !== null) {
            return $this->migrationTable;
        }

        $this->boot();

        $ref = new \ReflectionProperty('CI_Migration', '_migration_table');
        $ref->setAccessible(true);

        return $this->migrationTable = $ref->getValue(app('migration'));
    }

    /**
     * Set the output implementation that should be used by the console.
     *
     * @param callable(string $text, string $color): void $handler
     * @return $this
     */
    public function setOutput(callable $handler): self
    {
        $this->outputHandler = $handler;

        return $this;
    }

    /**
     * Execute a migration action towards a target version.
     */
    protected function execute(?string $version, string $direction): void
    {
        $this->boot();

        $startTime = microtime(true);
        $migrations = $this->getMigrations();
        $old = $this->getCurrentVersion();

        if ($direction === self::DOWN
            && $version !== null
            && (int)$version > (int)$old
        ) {
            $this->line('Nothing to migrate.', 'green');
            return;
        }

        $result = $version === null
            ? app('migration')->latest()
            : app('migration')->version($version);

        if ($result === false) {
            show_error(app('migration')->error_string());
        }

        $current = $this->getCurrentVersion();
        $runTime = round(microtime(true) - $startTime, 2);

        if ($old === $current) {
            if ($direction !== self::SILENT) {
                $this->line('Nothing to migrate.', 'green');
            }
            return;
        }

        if ($direction !== self::SILENT) {
            $this->reportMigrations($old, $current, $migrations, $runTime, $direction);
        }
    }

    /**
     * Print which migrations were applied or rolled back.
     */
    protected function reportMigrations(
        string $old,
        string $current,
        array  $migrations,
        float  $runTime,
        string $direction
    ): void
    {
        $ascendant = (int)$old < (int)$current;

        switch ($direction) {
            case self::UP:
                $affected = $this->migrationsInRange($migrations, $old, $current);
                break;
            case self::DOWN:
                $affected = $this->migrationsInRange($migrations, $current, $old);
                break;
            default:
                $affected = $migrations;
        }

        foreach ($affected as $version => $path) {
            $filename = Str::before(basename($path), '.php');

            if ($direction === self::UP) {
                $this->writeMigrating($filename, $runTime);
            } elseif ($direction === self::DOWN) {
                $this->writeRollingBack($filename, $runTime);
            } else {
                $this->writeAutoLine($filename, $runTime, $ascendant, (int)$version, (int)$current);
            }
        }
    }

    /**
     * Write the appropriate line for an AUTO-direction operation.
     */
    private function writeAutoLine(
        string $filename,
        float  $runTime,
        bool   $ascendant,
        int    $version,
        int    $current
    ): void
    {
        if ($ascendant && $version <= $current) {
            $this->writeMigrating($filename, $runTime);
        } elseif (!$ascendant && $version > $current) {
            $this->writeRollingBack($filename, $runTime);
        }
    }

    /**
     * Return migrations in the half-open range ($from, $to] using numeric comparison.
     */
    protected function migrationsInRange(array $migrations, string $from, string $to): array
    {
        return Collection::make($migrations)
            ->filter(fn($path, $v) => (int)$v > (int)$from && (int)$v <= (int)$to)
            ->all();
    }

    /**
     * Ensure the CI migration library is loaded.
     */
    protected function boot(): void
    {
        if (!$this->libraryLoaded) {
            app('load')->library('migration');
            $this->libraryLoaded = true;
        }
    }

    protected function writeMigrating(string $filename, float $runTime): void
    {
        $this->line(str_pad('Migrating:', 11) . $this->colorize($filename), 'yellow');
        $this->line(str_pad('Migrated:', 11) . $this->colorize("{$filename} ({$runTime}s)"), 'green');
    }

    protected function writeRollingBack(string $filename, float $runTime): void
    {
        $this->line(str_pad('Rolling back:', 14) . $this->colorize($filename), 'yellow');
        $this->line(str_pad('Rolled back:', 14) . $this->colorize("{$filename} ({$runTime}s)"), 'green');
    }

    protected function line(string $text, string $color = 'white'): void
    {
        if ($this->outputHandler !== null) {
            ($this->outputHandler)($text, $color);
            return;
        }

        OutputStyle::write($text, $color);
    }

    protected function colorize(string $text): string
    {
        return OutputStyle::wrap(OutputStyle::color($text, 'light_gray'), 120, 3);
    }
}
