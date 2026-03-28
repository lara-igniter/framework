<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\GeneratorCommand;
use Elegant\Support\Str;

class RepositoryMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:repository
                                    {name : The name of the class}
                                    {--model=|-m= : The model that the repository uses}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:repository';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new repository class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Repository';

    /**
     * Build the class with the given name.
     *
     * @param string $name
     * @return string
     */
    protected function buildClass(string $name): string
    {
        $stub = parent::buildClass($name);

        $model = $this->option('model')
            ? Str::lower(class_basename($this->option('model')))
            : '';

        return str_replace(['DummyModel', '{{ model }}', '{{model}}'], $model, $stub);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('repository.stub');
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
        return $rootNamespace . '\Repositories';
    }
}

