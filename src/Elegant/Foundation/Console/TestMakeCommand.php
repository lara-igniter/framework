<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\GeneratorCommand;
use Elegant\Support\Str;

class TestMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:test
                                    {name : The name of the class}
                                    {--unit|-u : Create a unit test}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new test class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Test';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->option('unit')
            ? $this->resolveStubPath('test.unit.stub')
            : $this->resolveStubPath('test.stub');
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
     * Get the destination class path.
     *
     * @param string $name
     * @return string
     */
    protected function getPath(string $name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);
        $name = ltrim(str_replace('\\', DIRECTORY_SEPARATOR, $name), DIRECTORY_SEPARATOR);

        return base_path('tests') . DIRECTORY_SEPARATOR . $name . '.php';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        if ($this->option('unit')) {
            return $rootNamespace . '\Unit';
        }

        return $rootNamespace . '\Feature';
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function rootNamespace(): string
    {
        return 'Tests\\';
    }
}
