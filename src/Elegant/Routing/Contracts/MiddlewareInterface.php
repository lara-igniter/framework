<?php

namespace Elegant\Routing\Contracts;

interface MiddlewareInterface
{
    /**
     * Middleware entry point
     *
     * @param mixed $args Middleware arguments
     *
     * @return mixed
     */
    public function run($args);
}
