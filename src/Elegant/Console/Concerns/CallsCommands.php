<?php

namespace Elegant\Console\Concerns;

use Elegant\Console\Kernel;

trait CallsCommands
{
    /**
     * Call another console command.
     *
     * @param string $command
     * @param array  $arguments
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
     * @param array  $arguments
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
     * @param array  $arguments
     * @param bool   $silent
     * @return void
     */
    protected function runCommand(string $command, array $arguments, bool $silent): void
    {
        $class = Kernel::discovered()[$command] ?? null;

        if (!$class) {
            $this->error("Command [{$command}] not found.");
            return;
        }

        $argv = array_merge(['artisan', $command], $this->buildArgv($arguments));

        $savedArgv       = $GLOBALS['argv'] ?? [];
        $savedServerArgv = $_SERVER['argv'] ?? [];

        $GLOBALS['argv'] = $_SERVER['argv'] = $argv;

        try {
            if ($silent) {
                ob_start();
            }

            $instance = new $class();
            $instance->handle();
        } finally {
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
     * @return array
     */
    protected function buildArgv(array $arguments): array
    {
        $argv = [];

        foreach ($arguments as $key => $value) {
            if (is_int($key)) {
                $argv[] = (string) $value;
            } elseif ($value === true) {
                $argv[] = '--' . $key;
            } elseif ($value !== false && $value !== null) {
                $argv[] = '--' . $key . '=' . $value;
            }
        }

        return $argv;
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

