<?php

namespace Elegant\Routing\Middleware;

use MY_Input;

class RouteAjaxMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \MY_Input $request
     * @param mixed $args
     *
     * @see \Elegant\Routing\Contracts\MiddlewareInterface::run()
     */
    public function run(MY_Input $request, $args = [])
    {
        if (!$request->is_ajax_request()) {
            trigger_404();
        }
    }
}
