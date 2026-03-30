<?php

namespace Elegant\Console\Concerns;

use Elegant\Console\Kernel;
use Elegant\Console\Command;

trait CallsCommands
{
    /**
     * Call another console command.
     *
     * @param string $command
     * @param array $arguments
     * @return void
     */
    public function call(string $command, array $arguments = []): void
    {
        $this->runCommand($command, $arguments, false);
    }

    /**
     * Call another console command without output.
     *
     * @param string $command
     * @param array $arguments
     * @return void
     */
    public function callSilent(string $command, array $arguments = []): void
    {
        $this->runCommand($command, $arguments, true);
    }

    /**
     * Run the given console command.
     *
     * @param string $command
     * @param array $arguments
     * @param bool $silent
     * @return void
     */
    protected function runCommand(string $command, array $arguments, bool $silent): void
    {
        $class = null;
        $routePath = null;

        foreach (Kernel::discovered() as $path => $commandClass) {
            $name = strstr($path, '/', true) ?: $path;
            if ($name === $command) {
                $class = $commandClass;
                $routePath = $path;
                break;
            }
        }

        if (!$class) {
            $this->error("Command [{$command}] not found.");
            return;
        }

        $argv = array_merge(['artisan', $command], $this->buildArgv($arguments, $routePath));

        $savedArgv = $GLOBALS['argv'] ?? [];
        $savedServerArgv = $_SERVER['argv'] ?? [];

        $GLOBALS['argv'] = $_SERVER['argv'] = $argv;

        try {
            if ($silent) {
                ob_start();
            }

            Command::$skipCiConstruct = true;
            $instance = new $class();
            Command::$skipCiConstruct = false;

            $instance->bindToCiSuperObject();

            $instance->handle();
        } finally {
            Command::$skipCiConstruct = false;

            if ($silent) {
                ob_end_clean();
            }

            $GLOBALS['argv'] = $savedArgv;
            $_SERVER['argv'] = $savedServerArgv;

            $this->restoreCiInstance();
        }
    }

    /**
     * Build an argv array from an associative arguments map.
     *
     * @param array $arguments
     * @param string|null $routePath Route path as stored in Kernel::discovered()
     * @return array
     */
    protected function buildArgv(array $arguments, ?string $routePath = null): array
    {
        // Extract ordered positional-argument names from the route path.
        $argNames = [];
        if ($routePath !== null) {
            preg_match_all('/\{(\w+)\??}/', $routePath, $matches);
            $argNames = $matches[1];
        }

        $positional = [];
        $opts = [];

        foreach ($arguments as $key => $value) {
            if (is_int($key)) {
                // Already positional (int index)
                $positional[$key] = (string)$value;
            } elseif (($pos = array_search($key, $argNames, true)) !== false) {
                // Named key matches a known positional argument → emit positionally
                $positional[$pos] = (string)$value;
            } elseif ($value === true) {
                $opts[] = '--' . $key;
            } elseif ($value !== false && $value !== null) {
                $opts[] = '--' . $key . '=' . $value;
            }
        }

        ksort($positional);

        return array_merge(array_values($positional), $opts);
    }

    /**
     * Restore the CI super-object to the current command instance
     * after an inner command replaced it via its constructor.
     *
     * @return void
     */
    protected function restoreCiInstance(): void
    {
        try {
            $ref = new \ReflectionProperty(\CI_Controller::class, 'instance');
            $ref->setAccessible(true);
            $ref->setValue(null, $this);
        } catch (\ReflectionException $e) {
            //
        }
    }
}

