<?php

namespace Elegant\Support;

use Closure;

class Benchmark
{
    /**
     * Measure a callable or array of callables over the given number of iterations.
     *
     * @param  \Closure|array  $benchmarkables
     * @param  int  $iterations
     * @return array|float
     */
    public static function measure($benchmarkables, int $iterations = 1)
    {
        return collect(Arr::wrap($benchmarkables))->map(function ($callback) use ($iterations) {
            return collect(range(1, $iterations))->map(function () use ($callback) {
                gc_collect_cycles();

                $start = microtime(true);

                $callback();

                return number_format((microtime(true) - $start), 2);
            })->average();
        })->when(
            $benchmarkables instanceof Closure,
            fn ($c) => $c->first(),
            fn ($c) => $c->all(),
        );
    }

    /**
     * Measure a callable once and return the duration and result.
     *
     * @template TReturn of mixed
     *
     * @param  (callable(): TReturn)  $callback
     * @return array{0: TReturn, 1: float}
     */
    public static function value(callable $callback): array
    {
        gc_collect_cycles();

        $start = microtime(true);

        $result = $callback();

        return [$result, number_format((microtime(true) - $start), 2)];
    }

    /**
     * Measure a callable or array of callables over the given number of iterations, then dump and die.
     *
     * @param  \Closure|array  $benchmarkables
     * @param  int  $iterations
     * @return never
     */
    public static function dd($benchmarkables, int $iterations = 1): void
    {
        $result = collect(static::measure(Arr::wrap($benchmarkables), $iterations))
            ->map(fn ($average) => number_format($average, 3).' sec')
            ->when($benchmarkables instanceof Closure, fn ($c) => $c->first(), fn ($c) => $c->all());

        dd($result);
    }
}
