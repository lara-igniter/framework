<?php

namespace Elegant\Contracts\Console;

use Elegant\Console\Input\ArgvInput;
use Elegant\Console\Output\ConsoleOutput;

interface Kernel
{
    /**
     * Bootstrap the application for artisan commands.
     *
     * @return void
     */
    public function bootstrap(): void;

    /**
     * Handle an incoming console command.
     *
     * @param  ArgvInput     $input
     * @param  ConsoleOutput $output
     * @return int
     */
    public function handle(ArgvInput $input, ConsoleOutput $output): int;

    /**
     * Terminate the application.
     *
     * @param  ArgvInput  $input
     * @param  int        $status
     * @return void
     */
    public function terminate(ArgvInput $input, int $status): void;
}
