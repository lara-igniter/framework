<?php

namespace Elegant\Auth;

use Elegant\Auth\Access\Gate;
use Elegant\Contracts\Hook\PostControllerConstructor;

class AuthServiceProvider implements PostControllerConstructor
{
    public function postControllerConstructor(&$params)
    {
        $this->registerAccessGate();
    }

    /**
     * Register the access gate service.
     *
     * @return void
     */
    protected function registerAccessGate()
    {
        app('gate', new Gate(auth()));
    }
}
