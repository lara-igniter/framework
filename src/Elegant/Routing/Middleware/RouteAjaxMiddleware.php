<?php

namespace Elegant\Routing\Middleware;

class RouteAjaxMiddleware
{
    /**
     *
     * @see \Elegant\Routing\Contracts\MiddlewareInterface::run()
     */
    public function run($args = [])
    {
        if (!ci()->input->is_ajax_request()) {
            trigger_404();
        }
    }
}
