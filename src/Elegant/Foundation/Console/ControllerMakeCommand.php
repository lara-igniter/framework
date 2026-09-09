<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\GeneratorCommand;
use Elegant\Support\Str;

class ControllerMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:controller
                                    {name : The name of the class}
                                    {--resource|-r : Generate a resource controller class}
                                    {--api : Exclude the create and edit methods}
                                    {--invokable : Generate a single method invokable controller}
                                    {--model=|-m= : Generate a resource controller for the given model}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:controller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new controller class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Controller';

    /**
     * Execute the console command.
     *
     * @return int|void
     * @throws \ReflectionException
     */
    public function handle()
    {
        if ($model = $this->option('model')) {
            $modelName = Str::studly(class_basename($model));
            $requestName = 'EditAdd' . $modelName . 'Request';

            $rawName = $this->getNameInput();
            $nameArray = explode('\\', $rawName);
            $subNamespaceFile = trim(implode('\\', array_slice($nameArray, 0, -1)), '\\');
            $requestFqn = empty($subNamespaceFile)
                ? $requestName
                : $subNamespaceFile . '\\' . $requestName;

            $this->callCommand(RequestMakeCommand::class, [$requestFqn]);
        }

        return parent::handle();
    }

    /**
     * Build the class with the given name, performing extra stub replacements
     * when the --model option is used.
     *
     * @param string $name
     * @return string
     */
    protected function buildClass(string $name): string
    {
        $stub = parent::buildClass($name);

        if ($model = $this->option('model')) {
            $modelName = Str::studly(class_basename($model));
            $requestName = 'EditAdd' . $modelName . 'Request';

            $rawName = $this->getNameInput();
            $nameArray = explode('\\', $rawName);
            $subNamespaceFile = trim(implode('\\', array_slice($nameArray, 0, -1)), '\\');

            $requestNamespace = empty($subNamespaceFile)
                ? $requestName
                : $subNamespaceFile . '\\' . $requestName;

            $modelVar = Str::lower($modelName);
            $modelPlural = Str::snake(Str::pluralStudly($modelName));
            $namespacedModel = 'App\\Models\\' . $modelName;

            $stub = str_replace(['{{ requestNamespace }}', '{{requestNamespace}}'], $requestNamespace, $stub);
            $stub = str_replace(['{{ requestName }}', '{{requestName}}'], $requestName, $stub);
            $stub = str_replace(['{{ modelPlural }}', '{{modelPlural}}'], $modelPlural, $stub);
            $stub = str_replace(['{{ model }}', '{{model}}'], $modelVar, $stub);
            $stub = str_replace(['{{ modelClass }}', '{{modelClass}}'], $modelName, $stub);
            $stub = str_replace(['{{ namespacedModel }}', '{{namespacedModel}}'], $namespacedModel, $stub);
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
        if ($this->option('model')) {
            return $this->resolveStubPath('controller.model.stub');
        }

        if ($this->option('api')) {
            return $this->resolveStubPath('controller.api.stub');
        }

        if ($this->option('invokable')) {
            return $this->resolveStubPath('controller.invokable.stub');
        }

        if ($this->option('resource')) {
            return $this->resolveStubPath('controller.stub');
        }

        return $this->resolveStubPath('controller.plain.stub');
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
        return $rootNamespace . '\Http\Controllers';
    }
}

