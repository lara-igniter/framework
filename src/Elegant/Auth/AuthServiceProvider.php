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
        $user = auth();

        app('gate', new Gate(is_object($user) ? $user : null));
    }
}
