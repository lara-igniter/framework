<?php

namespace Elegant\Session\Middleware;

use Elegant\Routing\Contracts\MiddlewareInterface as Middleware;
use Elegant\Routing\Route;
use MY_Input;

class StartSession implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param \MY_Input $request
     * @param mixed $args
     * @return void
     * @throws \Exception
     */
    public function run(MY_Input $request, $args)
    {
        $this->collectGarbage(app('session'));
        $this->storeCurrentUrl($request);
    }


    protected function storeCurrentUrl(MY_Input $request)
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
