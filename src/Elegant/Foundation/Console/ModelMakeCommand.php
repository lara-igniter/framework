<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\GeneratorCommand;
use Elegant\Database\Console\Factories\FactoryMakeCommand;
use Elegant\Database\Console\Migrations\MigrateMakeCommand;
use Elegant\Database\Console\Seeds\SeederMakeCommand;
use Elegant\Support\Str;

class ModelMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:model
                                    {name : The name of the class}
                                    {--all|-a : Generate a migration, seeder, factory, policy, and resource controller for the model}
                                    {--controller|-c : Create a new controller for the model}
                                    {--factory|-f : Create a new factory for the model}
                                    {--migration|-m : Create a new migration file for the model}
                                    {--policy : Create a new policy for the model}
                                    {--seed|-s : Create a new seeder for the model}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new model class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Model';

    /**
     * Execute the console command.
     *
     * @return int|void
     */
    public function handle()
    {
        $result = parent::handle();

        if ($result === false) {
            return false;
        }

        $name = $this->getNameInput();
        $modelName = Str::studly(class_basename($name));
        $table = Str::snake(Str::pluralStudly($modelName));

        $nameArray = explode('\\', $name);
        $subNamespaceFile = trim(implode('\\', array_slice($nameArray, 0, -1)), '\\');

        $className = empty($subNamespaceFile)
            ? $modelName
            : $subNamespaceFile . '\\' . $modelName;

        if ($this->option('all')) {
            $this->createFactory($className, $modelName);
            $this->createSeeder($modelName);
            $this->createMigration($table);
            $this->createController($className, $modelName);
            $this->createPolicy($className, $modelName);
        } else {
            if ($this->option('factory')) {
                $this->createFactory($className, $modelName);
            }

            if ($this->option('migration')) {
                $this->createMigration($table);
            }

            if ($this->option('seed')) {
                $this->createSeeder($modelName);
            }

            if ($this->option('controller')) {
                $this->createController($className, $modelName);
            }

            if ($this->option('policy')) {
                $this->createPolicy($className, $modelName);
            }
        }

        return $result;
    }

    /**
     * Create a model factory for the model.
     *
     * @param string $className Full class path (may include sub-namespace)
     * @param string $modelName Simple PascalCase class name
     * @return void
     * @throws \ReflectionException
     */
    protected function createFactory(string $className, string $modelName): void
    {
        $this->callCommand(FactoryMakeCommand::class, [$className . 'Factory'], ['model' => $modelName]);
    }

    /**
     * Create a migration file for the model.
     *
     * @param string $table Snake-case plural table name
     * @return void
     * @throws \ReflectionException
     */
    protected function createMigration(string $table): void
    {
        $this->callCommand(MigrateMakeCommand::class, ['create_' . $table . '_table'], ['create' => $table]);
    }

    /**
     * Create a seeder file for the model.
     *
     * @param string $modelName Simple PascalCase class name
     * @return void
     * @throws \ReflectionException
     */
    protected function createSeeder(string $modelName): void
    {
        $this->callCommand(SeederMakeCommand::class, [$modelName . 'Seeder']);
    }

    /**
     * Create a controller for the model.
     *
     * @param string $className Full class path (may include sub-namespace)
     * @param string $modelName Simple PascalCase class name
     * @return void
     * @throws \ReflectionException
     */
    protected function createController(string $className, string $modelName): void
    {
        $this->callCommand(ControllerMakeCommand::class, [$className . 'Controller'], ['model' => $modelName]);
    }

    /**
     * Create a policy for the model.
     *
     * @param string $className Full class path (may include sub-namespace)
     * @param string $modelName Simple PascalCase class name
     * @return void
     * @throws \ReflectionException
     */
    protected function createPolicy(string $className, string $modelName): void
    {
        $this->callCommand(PolicyMakeCommand::class, [$className . 'Policy'], ['model' => $modelName]);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('model.stub');
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
        return $rootNamespace . '\Models';
    }
}

