<?php

use Elegant\Collections\Arr;
use Elegant\Collections\Collection;

if (! function_exists('collect')) {
    /**
     * Create a collection from the given value.
     *
     * @param  mixed  $value
     * @return \Elegant\Collections\Collection
     */
    function collect($value = null): Collection
    {
        return new Collection($value);
    }
}

if (! function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}
