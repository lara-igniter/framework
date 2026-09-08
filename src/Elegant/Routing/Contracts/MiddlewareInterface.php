<?php

namespace Elegant\Routing\Contracts;

use Elegant\Http\Request;

interface MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @param \Elegant\Http\Request $request
     * @param mixed $args
     * @return mixed
     */
    public function run(Request $request, $args);
}
