<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\GeneratorCommand;

class ScopeMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:scope {name : The name of the class}';

    /**
     * The name of the console command.
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:scope';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new query scope class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Scope';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('scope.stub');
    }

    /**
     * Resolve the stub path, checking for a published override first.
     *
     * @param string $stub
     * @return string
     */
    protected function resolveStubPath(string $stub): string
    {
        $published = base_path('stubs/' . $stub);
        return file_exists($published) ? $published : __DIR__ . '/stubs/' . $stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace . '\Scopes';
    }
}
