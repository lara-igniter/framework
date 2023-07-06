<?php

namespace Elegant\Console\Concerns;

use Throwable;

trait DebugTraceableTrait
{
    /**
     * Tweaks the exception's constructor to assign the file/line to where
     * it is actually raised rather than were it is instantiated.
     */
    final public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $trace = $this->getTrace()[0];

        if (isset($trace['class']) && $trace['class'] === static::class) {
            [
                'line' => $this->line,
                'file' => $this->file,
            ] = $trace;
        }
    }
}
