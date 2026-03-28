<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\GeneratorCommand;
use Elegant\Support\Str;

class ConsoleMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:command
                                    {name : The name of the command class}
                                    {--command= : The terminal command that should be assigned}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'make:command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new console command class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'Console command';

    /**
     * Replace the class name for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replaceClass(string $stub, string $name): string
    {
        $stub = parent::replaceClass($stub, $name);

        $defaultNs = $this->getDefaultNamespace(trim($this->rootNamespace(), '\\'));
        $subNs = ltrim(Str::replaceFirst($defaultNs, '', $this->getNamespace($name)), '\\');
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);
        $commandName = $this->option('command') ?: $this->guessCommandName($class, $subNs);

        return str_replace(['dummy:command', '{{ command }}', '{{command}}'], $commandName, $stub);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        $published = base_path('stubs/console.stub');

        return file_exists($published) ? $published : __DIR__ . '/stubs/console.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace . '\Console\Commands';
    }

    /**
     * Guess a human-readable CLI command name from the class and sub-namespace.
     *
     * @param string $class
     * @param string $subNs
     * @return string
     */
    protected function guessCommandName(string $class, string $subNs): string
    {
        $slug = Str::kebab(
            Str::endsWith($class, 'Command')
                ? Str::beforeLast($class, 'Command')
                : $class
        );

        if ($subNs === '') {
            return $slug;
        }

        return Str::kebab(explode('\\', $subNs)[0]) . ':' . $slug;
    }
}

