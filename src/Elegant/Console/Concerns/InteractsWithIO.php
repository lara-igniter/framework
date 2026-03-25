<?php

namespace Elegant\Console\Concerns;

use Elegant\Console\OutputStyle;

trait InteractsWithIO
{
    /**
     * Get the value of a command argument.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function argument(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->arguments;
        }

        return $this->arguments[$key] ?? $default;
    }

    /**
     * Get all of the arguments passed to the command.
     *
     * @return array
     */
    public function arguments(): array
    {
        return $this->argument();
    }

    /**
     * Get the value of a command option.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function option(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->options;
        }

        return $this->options[$key] ?? $default;
    }

    /**
     * Get all of the options passed to the command.
     *
     * @return array
     */
    public function options(): array
    {
        return $this->option();
    }

    /**
     * Write a string as information output (green).
     *
     * @param string $string
     * @return void
     */
    public function info(string $string): void
    {
        OutputStyle::write($string, 'green');
    }

    /**
     * Write a string as standard output with an optional foreground / background colour.
     *
     * @param string $string
     * @param string|null $fg
     * @param string|null $bg
     * @return void
     */
    public function line(string $string, ?string $fg = null, ?string $bg = null): void
    {
        OutputStyle::write($string, $fg, $bg);
    }

    /**
     * Write a string as comment output (dark gray).
     *
     * @param string $string
     * @return void
     */
    public function comment(string $string): void
    {
        OutputStyle::write($string, 'dark_gray');
    }

    /**
     * Write a string as warning output (yellow).
     *
     * @param string $string
     * @return void
     */
    public function warn(string $string): void
    {
        OutputStyle::write($string, 'yellow');
    }

    /**
     * Write a string as error output (white on red, via STDERR).
     *
     * @param string $string
     * @return void
     */
    public function error(string $string): void
    {
        OutputStyle::error($string, 'light_gray', 'red');
    }

    /**
     * Write a string in an alert box.
     *
     * @param string $string
     * @return void
     */
    public function alert(string $string): void
    {
        $length = strlen(strip_tags($string)) + 12;
        $border = str_repeat('*', $length);

        $this->comment($border);
        $this->comment('*     ' . $string . '     *');
        $this->comment($border);

        $this->newLine();
    }

    /**
     * Write one or more blank lines.
     *
     * @param int $count
     * @return void
     */
    public function newLine(int $count = 1): void
    {
        OutputStyle::newLine($count);
    }

    /**
     * Prompt the user for input.
     *
     * @param string $question
     * @param string|null $default
     * @return string
     */
    public function ask(string $question, ?string $default = null): string
    {
        return OutputStyle::prompt($question, $default ?? '');
    }

    /**
     * Confirm a yes/no question with the user.
     *
     * @param string $question
     * @param bool $default
     * @return bool
     */
    public function confirm(string $question, bool $default = false): bool
    {
        $answer = OutputStyle::prompt($question . ' (yes/no)', $default ? 'yes' : 'no');

        return in_array(strtolower(trim($answer)), ['y', 'yes', '1', 'true'], true);
    }

    /**
     * Format input to a textual table.
     *
     * @param array $headers
     * @param array $rows
     * @return void
     */
    public function table(array $headers, array $rows): void
    {
        OutputStyle::table($rows, $headers);
    }
}
