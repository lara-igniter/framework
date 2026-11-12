<?php

namespace Elegant\Hashing;

use Elegant\Contracts\Hook\PostControllerConstructor;

class HashServiceProvider implements PostControllerConstructor
{
    /**
     * Register the password hasher.
     *
     * @param  array  $params
     * @return void
     */
    public function postControllerConstructor(&$params)
    {
        app('hash', new HashManager());
    }
}
