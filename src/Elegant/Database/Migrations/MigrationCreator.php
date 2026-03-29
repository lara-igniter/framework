<?php

namespace Elegant\Database\Migrations;

use Elegant\Support\Facades\File;
use Elegant\Support\Str;
use InvalidArgumentException;

class MigrationCreator
{
    /**
     * The custom stubs directory (e.g. base_path('stubs')).
     *
     * @var string
     */
    protected string $customStubPath;

    /**
     * Create a new migration creator instance.
     *
     * @param  string  $customStubPath
     */
    public function __construct(string $customStubPath)
    {
        $this->customStubPath = $customStubPath;
    }

    /**
     * Create a new migration at the given path.
     *
     * @param  string  $name
     * @param  string  $path
     * @param  string|null  $table
     * @param  bool  $create
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function create(string $name, string $path, ?string $table = null, bool $create = false): string
    {
        $this->ensureMigrationDoesntAlreadyExist($name, $path);

        $stub = $this->getStub($table, $create);

        $filePath = $this->getPath($name, $path);

        File::ensureDirectoryExists(dirname($filePath));

        File::put($filePath, $this->populateStub($stub, $name, $table, $create));

        return $filePath;
    }

    /**
     * Ensure that a migration with the given name doesn't already exist.
     *
     * @param  string  $name
     * @param  string  $path
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function ensureMigrationDoesntAlreadyExist(string $name, string $path): void
    {
        foreach (File::glob($path . DIRECTORY_SEPARATOR . '*.php') as $file) {
            if (Str::endsWith(File::name($file), $name)) {
                throw new InvalidArgumentException("A migration for [{$name}] already exists.");
            }
        }
    }

    /**
     * Get the migration stub file content.
     *
     * @param  string|null  $table
     * @param  bool  $create
     * @return string
     */
    protected function getStub(?string $table, bool $create): string
    {
        if (is_null($table)) {
            $stub = 'migration.stub';
        } elseif ($create) {
            $stub = 'migration.create.stub';
        } else {
            $stub = 'migration.update.stub';
        }

        $custom = $this->customStubPath . DIRECTORY_SEPARATOR . $stub;

        return File::get(file_exists($custom) ? $custom : $this->stubPath() . DIRECTORY_SEPARATOR . $stub);
    }

    /**
     * Populate the place-holders in the migration stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @param  string|null  $table
     * @param  bool  $create
     * @return string
     */
    protected function populateStub(string $stub, string $name, ?string $table, bool $create): string
    {
        $stub = str_replace(['DummyName', '{{ name }}', '{{name}}'], $name, $stub);

        if (! is_null($table)) {
            $stub = str_replace(['DummyTable', '{{ table }}', '{{table}}'], $table, $stub);

            if (! $create) {
                $column = Str::of($name)->after('add_')->before('_column')->toString();

                $stub = str_replace(['{{ column }}', '{{column}}'], $column, $stub);
            }
        }

        return $stub;
    }

    /**
     * Get the full path to the migration.
     *
     * @param  string  $name
     * @param  string  $path
     * @return string
     */
    protected function getPath(string $name, string $path): string
    {
        return $path . DIRECTORY_SEPARATOR . $this->getDatePrefix() . '_' . $name . '.php';
    }

    /**
     * Get the date prefix for the migration.
     *
     * @return string
     */
    protected function getDatePrefix(): string
    {
        return date('YmdHis');
    }

    /**
     * Get the path to the stubs.
     *
     * @return string
     */
    public function stubPath(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'stubs';
    }
}

