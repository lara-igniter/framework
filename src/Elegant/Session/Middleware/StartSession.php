<?php

namespace Elegant\Session\Middleware;

use Elegant\Routing\Contracts\MiddlewareInterface as Middleware;
use Elegant\Routing\Route;
use Elegant\Http\Request;

class StartSession implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Elegant\Http\Request $request
     * @param mixed $args
     * @return void
     * @throws \Exception
     */
    public function run(Request $request, $args)
    {
        $this->collectGarbage(app('session'));
        $this->storeCurrentUrl($request);
    }


    protected function storeCurrentUrl(Request $request)
    {
        if ($request->method(true) === 'GET' &&
            $request->route() instanceof Route &&
            !$request->ajax() &&
            !$request->prefetch()) {
            app('session')->set_userdata('_previous', [
                'url' => current_url()
            ]);
        }
    }

    protected function collectGarbage($session)
    {
        if(config_item('sess_driver') === 'files') {
            $session->gc();
        }
    }
}
