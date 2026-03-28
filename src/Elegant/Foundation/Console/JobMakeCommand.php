<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\GeneratorCommand;

class JobMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:job
                                    {name : The name of the class}
                                    {--queued|-Q : Indicates that job should be queued}';

    /**
     * The name of the console command.
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new job class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Job';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->option('queued')
            ? $this->resolveStubPath('job.queued.stub')
            : $this->resolveStubPath('job.stub');
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
        return $rootNamespace . '\Jobs';
    }
}

