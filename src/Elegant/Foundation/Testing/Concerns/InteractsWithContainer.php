<?php

namespace Elegant\Foundation\Testing\Concerns;

trait InteractsWithContainer
{
    /**
     * Register an instance of an object in the container.
     *
     * @param string $abstract
     * @param object $instance
     * @return object
     */
    protected function swap(string $abstract, object $instance): object
    {
        return $this->instance($abstract, $instance);
    }

    /**
     * Register an instance of an object in the container.
     *
     * @param string $abstract
     * @param object $instance
     * @return object
     */
    protected function instance(string $abstract, object $instance): object
    {
        $this->app->instance($abstract, $instance);

        return $instance;
    }
}
