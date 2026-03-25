<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;
use Elegant\Console\OutputStyle;
use Elegant\Support\Facades\File;
use Elegant\Support\Str;

class ConsoleMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'make:command
                                    {name : The name of the command class (e.g. SendEmails or Notifications/SendEmails)}
                                    {--command= : The terminal command that should be assigned (e.g. emails:send)}';

    /**
     * The default name (used for routing — must match the command name in $signature).
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
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $rawName = $this->argument('name');

        // Normalise separator: always use backslash internally
        $rawName = str_replace('/', '\\', $rawName);

        // Split into (optional) sub-namespace parts + class name
        $parts = explode('\\', $rawName);
        $class = Str::studly(array_pop($parts));
        $subNs = implode('\\', array_map([Str::class, 'studly'], array_filter($parts)));

        // Full PSR-4 namespace
        $namespace = 'App\\Console\\Commands' . ($subNs !== '' ? '\\' . $subNs : '');

        // Absolute file path
        $subDir = $subNs !== '' ? str_replace('\\', DIRECTORY_SEPARATOR, $subNs) : '';
        $filePath = app_path(
            'Console' . DIRECTORY_SEPARATOR . 'Commands' .
            ($subDir !== '' ? DIRECTORY_SEPARATOR . $subDir : '') .
            DIRECTORY_SEPARATOR . $class . '.php'
        );

        if (File::exists($filePath)) {
            OutputStyle::error("Command [{$class}] already exists!", 'light_gray', 'red');
            return;
        }

        // Derive the default CLI slug from the class name and sub-namespace
        $commandName = $this->option('command') ?: $this->guessCommandName($class, $subNs);

        // Resolve stub path — prefer a published stub in the project root, fall back to package stub
        $stub = $this->resolveStub();

        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ command }}', $commandName, $stub);

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $stub);

        $relPath = ltrim(str_replace(base_path(), '', $filePath), '/\\');

        OutputStyle::write("Console command [{$class}] created successfully.", 'green');
        OutputStyle::write('File: ' . OutputStyle::color($relPath, 'light_gray'), 'green');
    }

    /**
     * Guess a human-readable CLI command name from the class name and sub-namespace.
     *
     * @param string $class
     * @param string $subNs
     * @return string
     */
    protected function guessCommandName(string $class, string $subNs): string
    {
        // Strip trailing "Command" suffix before kebab-casing
        $slug = Str::kebab(Str::endsWith($class, 'Command')
            ? Str::beforeLast($class, 'Command')
            : $class
        );

        if ($subNs === '') {
            return $slug;
        }

        // Use only the first (outermost) namespace part as the group
        $group = Str::kebab(explode('\\', $subNs)[0]);

        return $group . ':' . $slug;
    }

    /**
     * Resolve the stub content.
     *
     * @return string
     */
    protected function resolveStub(): string
    {
        $published = base_path('stubs/console.stub');

        $path = file_exists($published) ? $published : __DIR__ . '/stubs/console.stub';

        return File::get($path);
    }
}

