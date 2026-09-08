<?php

namespace Elegant\Routing\Middleware;

use Elegant\Http\Request;

class RouteAjaxMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Elegant\Http\Request $request
     * @param mixed $args
     *
     * @see \Elegant\Routing\Contracts\MiddlewareInterface::run()
     */
    public function run(Request $request, $args = [])
    {
        if (!$request->is_ajax_request()) {
            trigger_404();
        }
    }
}
