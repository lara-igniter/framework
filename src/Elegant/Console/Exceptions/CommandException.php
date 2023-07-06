<?php

namespace Elegant\Console\Exceptions;

use Elegant\Console\Concerns\DebugTraceableTrait;
use RuntimeException;

class CommandException extends RuntimeException
{
    use DebugTraceableTrait;

    /**
     * Thrown when `$color` specified for `$type` is not within the
     * allowed list of colors.
     *
     * @return \Elegant\Console\Exceptions\CommandException
     */
    public static function forInvalidColor(string $type, string $color)
    {
        return new static(lang('CLI.invalidColor', [$type, $color]));
    }
}
