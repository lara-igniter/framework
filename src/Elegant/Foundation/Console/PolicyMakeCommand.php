<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\GeneratorCommand;
use Elegant\Support\Str;

class PolicyMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:policy
                                    {name : The name of the class}
                                    {--model=|-m= : The model that the policy applies to}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:policy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new policy class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Policy';

    /**
     * Build the class with the given name.
     *
     * @param string $name
     * @return string
     */
    protected function buildClass(string $name): string
    {
        $stub = parent::buildClass($name);

        if ($model = $this->option('model')) {
            $modelVar = Str::lower(class_basename($model));

            $stub = str_replace(['{{ model }}', '{{model}}'], $modelVar, $stub);
        }

        return $stub;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->option('model')
            ? $this->resolveStubPath('policy.stub')
            : $this->resolveStubPath('policy.plain.stub');
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
        return $rootNamespace . '\Policies';
    }
}

