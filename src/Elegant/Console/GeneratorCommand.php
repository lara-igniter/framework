<?php

namespace Elegant\Console;

use Elegant\Console\OutputStyle;
use Elegant\Support\Facades\File;
use Elegant\Support\Str;

abstract class GeneratorCommand extends Command
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected string $type = 'File';

    /**
     * Reserved PHP names that cannot be used for generation.
     *
     * @var string[]
     */
    protected array $reservedNames = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable',
        'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default',
        'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
        'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit',
        'extends', 'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global',
        'goto', 'if', 'implements', 'include', 'include_once', 'instanceof',
        'insteadof', 'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or',
        'print', 'private', 'protected', 'public', 'readonly', 'require',
        'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try',
        'unset', 'use', 'var', 'while', 'xor', 'yield',
    ];

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    abstract protected function getStub(): string;

    /**
     * Execute the console command.
     *
     * @return int|void
     */
    public function handle()
    {
        if ($this->isReservedName($this->getNameInput())) {
            $this->error('The name "' . $this->getNameInput() . '" is reserved by PHP.');
            return 1;
        }

        $name = $this->qualifyClass($this->getNameInput());
        $path = $this->getPath($name);

        if ($this->alreadyExists($this->getNameInput())) {
            $this->error($this->type . ' already exists!');
            return 1;
        }

        $this->makeDirectory($path);

        File::put($path, $this->sortImports($this->buildClass($name)));

        $class = str_replace($this->getNamespace($name) . '\\', '', $name);
        $relPath = ltrim(str_replace(base_path(), '', $path), '/\\');

        $this->info($this->type . ' [' . $class . '] created successfully.');
        $this->line('File: ' . OutputStyle::color($relPath, 'light_gray'));
    }

    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param string $name
     * @return string
     */
    protected function qualifyClass(string $name): string
    {
        $name = ltrim($name, '\\/');
        $name = str_replace('/', '\\', $name);

        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        return $this->qualifyClass(
            $this->getDefaultNamespace(trim($rootNamespace, '\\')) . '\\' . $name
        );
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace;
    }

    /**
     * Determine if the class already exists.
     *
     * @param string $rawName
     * @return bool
     */
    protected function alreadyExists(string $rawName): bool
    {
        return File::exists($this->getPath($this->qualifyClass($rawName)));
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

        return app_path(str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php');
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param string $path
     * @return string
     */
    protected function makeDirectory(string $path): string
    {
        if (!File::isDirectory(dirname($path))) {
            File::makeDirectory(dirname($path), 0777, true, true);
        }

        return $path;
    }

    /**
     * Build the class with the given name.
     *
     * @param string $name
     * @return string
     */
    protected function buildClass(string $name): string
    {
        $stub = File::get($this->getStub());

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Replace the namespace for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return $this
     */
    protected function replaceNamespace(string &$stub, string $name): self
    {
        $searches = [
            ['DummyNamespace', 'DummyRootNamespace'],
            ['{{ namespace }}', '{{ rootNamespace }}'],
            ['{{namespace}}', '{{rootNamespace}}'],
        ];

        foreach ($searches as [$nsToken, $rootToken]) {
            $stub = str_replace(
                [$nsToken, $rootToken],
                [$this->getNamespace($name), $this->rootNamespace()],
                $stub
            );
        }

        return $this;
    }

    /**
     * Get the full namespace for a given class, without the class name.
     *
     * @param string $name
     * @return string
     */
    protected function getNamespace(string $name): string
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replaceClass(string $stub, string $name): string
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);

        return str_replace(['DummyClass', '{{ class }}', '{{class}}'], $class, $stub);
    }

    /**
     * Alphabetically sorts the imports for the given stub.
     *
     * @param string $stub
     * @return string
     */
    protected function sortImports(string $stub): string
    {
        if (preg_match('/(?P<imports>(?:use [^;]+;$\n?)+)/m', $stub, $match)) {
            $imports = explode("\n", trim($match['imports']));

            sort($imports);

            return str_replace(trim($match['imports']), implode("\n", $imports), $stub);
        }

        return $stub;
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput(): string
    {
        return trim($this->argument('name'));
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function rootNamespace(): string
    {
        return 'App\\';
    }

    /**
     * Checks whether the given name is reserved.
     *
     * @param string $name
     * @return bool
     */
    protected function isReservedName(string $name): bool
    {
        return in_array(strtolower($name), $this->reservedNames, true);
    }

    /**
     * Programmatically call another generator command.
     *
     * @param class-string $commandClass
     * @param array<int, string> $arguments
     * @param array<string, string|true> $options
     * @return void
     * @throws \ReflectionException
     */
    protected function callCommand(string $commandClass, array $arguments = [], array $options = []): void
    {
        $reflection = new \ReflectionClass($commandClass);

        /** @var GeneratorCommand $cmd */
        $cmd = $reflection->newInstanceWithoutConstructor();

        $sigProp = $this->reflectProperty($reflection, 'signature');
        $signature = $sigProp->getValue($cmd);

        [, $argDefs] = Parser::parse($signature);

        $mappedArgs = [];
        foreach ($argDefs as $i => $def) {
            if (isset($arguments[$i])) {
                $mappedArgs[$def['name']] = $arguments[$i];
            } elseif (isset($def['default'])) {
                $mappedArgs[$def['name']] = $def['default'];
            } elseif (empty($def['required'])) {
                $mappedArgs[$def['name']] = null;
            }
        }

        $this->reflectProperty($reflection, 'arguments')->setValue($cmd, $mappedArgs);
        $this->reflectProperty($reflection, 'options')->setValue($cmd, $options);

        $cmd->handle();
    }

    /**
     * Walk the class hierarchy to find a named property and return an
     * accessible ReflectionProperty.
     *
     * @param \ReflectionClass $reflection
     * @param string $name
     * @return \ReflectionProperty
     */
    private function reflectProperty(\ReflectionClass $reflection, string $name): \ReflectionProperty
    {
        $current = $reflection;

        do {
            if ($current->hasProperty($name)) {
                $prop = $current->getProperty($name);
                $prop->setAccessible(true);

                return $prop;
            }
        } while ($current = $current->getParentClass());

        throw new \RuntimeException("Property '{$name}' not found in {$reflection->getName()} hierarchy.");
    }
}

