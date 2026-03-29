<?php

namespace Elegant\Database\Console\Factories;

use Elegant\Console\GeneratorCommand;
use Elegant\Support\Str;

class FactoryMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:factory
                                    {name : The name of the class}
                                    {--model=|-m= : The name of the model}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:factory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new model factory';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Factory';

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
            ? '\\App\\Models\\' . Str::studly(class_basename($this->option('model'))) . '::class'
            : "''";

        return str_replace(['DummyModel', '{{ model }}', '{{model}}'], $model, $stub);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('factory.stub');
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
        return 'Database\\Factories';
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function rootNamespace(): string
    {
        return 'Database\\';
    }

    /**
     * Get the destination class path.
     *
     * @param string $name
     * @return string
     */
    protected function getPath(string $name): string
    {
        $name = Str::replaceFirst('Database\\Factories\\', '', $name);

        return database_path('factories/' . str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php');
    }
}

