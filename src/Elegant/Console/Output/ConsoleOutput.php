<?php

namespace Elegant\Console\Output;

use Elegant\Console\OutputStyle;

class ConsoleOutput
{
    /**
     * Write a message to the console.
     *
     * @param string $message
     * @param string|null $foreground
     * @return void
     */
    public function writeln(string $message = '', ?string $foreground = null): void
    {
        OutputStyle::write($message, $foreground);
    }

    /**
     * Write an error message to STDERR.
     *
     * @param string $message
     * @return void
     */
    public function error(string $message): void
    {
        OutputStyle::error($message);
    }
}
