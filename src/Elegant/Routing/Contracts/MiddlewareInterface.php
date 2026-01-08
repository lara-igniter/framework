<?php

namespace Elegant\Routing\Contracts;

use MY_Input;

interface MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @param \MY_Input $request
     * @param mixed $args
     * @return mixed
     */
    public function run(MY_Input $request, $args);
}
