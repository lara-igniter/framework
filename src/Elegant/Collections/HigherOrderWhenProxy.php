<?php

namespace Elegant\Collections;

/**
 * @mixin \Elegant\Collections\Enumerable
 */
class HigherOrderWhenProxy
{
    /**
     * The collection being operated on.
     *
     * @var \Elegant\Collections\Enumerable
     */
    protected $collection;

    /**
     * The condition for proxying.
     *
     * @var bool
     */
    protected $condition;

    /**
     * Create a new proxy instance.
     *
     * @param  \Elegant\Collections\Enumerable  $collection
     * @param bool $condition
     * @return void
     */
    public function __construct(Enumerable $collection, bool $condition)
    {
        $this->condition = $condition;
        $this->collection = $collection;
    }

    /**
     * Proxy accessing an attribute onto the collection.
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->condition
            ? $this->collection->{$key}
            : $this->collection;
    }

    /**
     * Proxy a method call onto the collection.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->condition
            ? $this->collection->{$method}(...$parameters)
            : $this->collection;
    }
}
