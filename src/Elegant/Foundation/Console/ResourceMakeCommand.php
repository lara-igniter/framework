<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\GeneratorCommand;
use Elegant\Support\Str;

class ResourceMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:resource
                                    {name : The name of the class}
                                    {--collection|-c : Create a resource collection}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:resource';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new resource class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Resource';

    /**
     * Execute the console command.
     *
     * @return int|void
     */
    public function handle()
    {
        if ($this->isCollection()) {
            $this->type = 'Resource collection';
        }

        return parent::handle();
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->isCollection()
            ? $this->resolveStubPath('resource-collection.stub')
            : $this->resolveStubPath('resource.stub');
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
     * Determine if the command is generating a resource collection.
     *
     * @return bool
     */
    protected function isCollection(): bool
    {
        return $this->option('collection') ||
            Str::endsWith($this->argument('name'), 'Collection');
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace . '\Resources';
    }
}

