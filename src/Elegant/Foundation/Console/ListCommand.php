<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;
use Elegant\Console\Kernel;
use Elegant\Console\OutputStyle;
use Elegant\Foundation\Application;

class ListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'list';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'List commands';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        $this->line('Laraigniter Framework ' . OutputStyle::color(Application::VERSION, 'green'));

        $this->newLine();

        $this->warn('Usage:');

        $this->line(' command [options] [arguments]', 'light_gray');

        $this->newLine();

        $maxLen = 28;

        $this->warn('Options:');

        foreach ([['  -h, --help', 'Display help for the given command'], ['  -v, --version', 'Display this application version']] as [$t, $d]) {
            $this->writeCommandLine($t, $d, $maxLen);
        }

        $this->newLine();

        $grouped = $this->groupCommands();

        if (!empty($grouped[''])) {
            $this->warn('Available commands:');

            foreach ($grouped[''] as $cmd) {
                $this->writeCommandLine('  ' . $cmd['name'], $cmd['description'], $maxLen);
            }

            unset($grouped['']);
        }

        foreach ($grouped as $group => $cmds) {
            $this->warn(' ' . $group);

            foreach ($cmds as $cmd) {
                $this->writeCommandLine('  ' . $cmd['name'], $cmd['description'], $maxLen);
            }
        }

        $this->newLine();
    }

    protected function groupCommands(): array
    {
        $grouped = [];

        foreach (Kernel::discovered() as $routePath => $class) {
            $description = '';

            try {
                $props = (new \ReflectionClass($class))->getDefaultProperties();
                $description = $props['description'] ?? '';
            } catch (\ReflectionException $e) {
                //
            }

            // Strip route-path argument segments: 'migrate/{version?}' → 'migrate'
            $name = strpos($routePath, '/') !== false
                ? substr($routePath, 0, strpos($routePath, '/'))
                : $routePath;

            $parts = explode(':', $name);
            $group = count($parts) > 1 ? $parts[0] : '';

            $grouped[$group][] = ['name' => $name, 'description' => $description];
        }

        ksort($grouped);

        foreach ($grouped as $group => &$cmds) {
            usort($cmds, fn($a, $b) => strcmp($a['name'], $b['name']));
        }

        return $grouped;
    }

    private function writeCommandLine(string $title, string $description, int $maxLen): void
    {
        $this->info(
            substr($title . str_repeat(' ', $maxLen + 3), 0, $maxLen + 3)
            . OutputStyle::wrap(OutputStyle::color($description, 'light_gray'), 120, $maxLen + 3)
        );
    }
}
