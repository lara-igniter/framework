<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'test';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Run the application tests';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $root = $this->resolveProjectRoot();

        $phpunit = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit';

        if (! is_file($phpunit)) {
            $phpunit = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'phpunit';
        }

        $config = $root . DIRECTORY_SEPARATOR . 'phpunit.xml';

        if (! is_file($phpunit)) {
            $this->error('PHPUnit is not installed. Run composer install.');

            return 1;
        }

        if (! is_file($config)) {
            $this->error('Unable to locate phpunit.xml in ' . $root);

            return 1;
        }

        $args = [];
        $argv = $_SERVER['argv'] ?? [];
        $found = false;

        foreach ($argv as $arg) {
            if (! $found) {
                if ($arg === 'test') {
                    $found = true;
                }

                continue;
            }

            $args[] = $arg;
        }

        // Laravel-style colored printer (not plain --testdox), unless the caller
        // already chose another printer / format option.
        if (! $this->hasPrinterOption($args)) {
            $args[] = '--printer';
            $args[] = \Elegant\Foundation\Testing\Printer::class;
        }

        // Ensure colors even when stdout is not detected as a TTY (piped/CI).
        if (! in_array('--colors=never', $args, true) && ! $this->hasArgPrefix($args, '--colors')) {
            $args[] = '--colors=always';
        }

        $command = array_merge(
            [PHP_BINARY, $phpunit, '--configuration', $config],
            $args
        );

        $previous = getcwd();
        chdir($root);

        try {
            $cmd = implode(' ', array_map('escapeshellarg', $command));
            passthru($cmd, $exitCode);
        } finally {
            if ($previous) {
                chdir($previous);
            }
        }

        return (int) $exitCode;
    }

    /**
     * @return string
     */
    protected function resolveProjectRoot(): string
    {
        $candidates = [
            rtrim(base_path(), '/\\'),
            dirname(rtrim(base_path(), '/\\')),
            getcwd() ?: '',
            dirname(__DIR__, 4),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate . DIRECTORY_SEPARATOR . 'phpunit.xml')) {
                return $candidate;
            }
        }

        return rtrim(base_path(), '/\\');
    }

    /**
     * @param array $args
     * @return bool
     */
    protected function hasPrinterOption(array $args): bool
    {
        $options = [
            '--testdox',
            '--testdox-html',
            '--testdox-text',
            '--testdox-xml',
            '--teamcity',
            '--log-junit',
            '--log-teamcity',
            '--no-output',
            '--list-tests',
            '--list-suites',
            '--list-groups',
            '--printer',
        ];

        foreach ($args as $arg) {
            foreach ($options as $option) {
                if ($arg === $option || strpos((string) $arg, $option . '=') === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array $args
     * @param string $prefix
     * @return bool
     */
    protected function hasArgPrefix(array $args, string $prefix): bool
    {
        foreach ($args as $arg) {
            if ($arg === $prefix || strpos((string) $arg, $prefix . '=') === 0) {
                return true;
            }
        }

        return false;
    }
}
