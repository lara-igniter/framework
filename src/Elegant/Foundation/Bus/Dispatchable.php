<?php

namespace Elegant\Foundation\Bus;

use Elegant\Support\Fluent;

trait Dispatchable
{
    /**
     * Dispatch the job with the given arguments.
     *
     * @param mixed ...$arguments
     * @return \Elegant\Foundation\Bus\PendingDispatch
     */
    public static function dispatch(...$arguments): PendingDispatch
    {
        return static::newPendingDispatch(new static(...$arguments));
    }

    /**
     * Dispatch the job with the given arguments if the given truth test passes.
     *
     * @param bool $boolean
     * @param mixed ...$arguments
     * @return \Elegant\Foundation\Bus\PendingDispatch|\Elegant\Support\Fluent
     */
    public static function dispatchIf(bool $boolean, ...$arguments)
    {
        return $boolean
            ? static::newPendingDispatch(new static(...$arguments))
            : new Fluent;
    }

    /**
     * Dispatch the job with the given arguments unless the given truth test passes.
     *
     * @param bool $boolean
     * @param mixed ...$arguments
     * @return \Elegant\Foundation\Bus\PendingDispatch|\Elegant\Support\Fluent
     */
    public static function dispatchUnless(bool $boolean, ...$arguments)
    {
        return !$boolean
            ? static::newPendingDispatch(new static(...$arguments))
            : new Fluent;
    }

    /**
     * Create a new pending job dispatch instance.
     *
     * @param mixed $job
     * @return \Elegant\Foundation\Bus\PendingDispatch
     */
    protected static function newPendingDispatch($job): PendingDispatch
    {
        return new PendingDispatch($job);
    }
}
