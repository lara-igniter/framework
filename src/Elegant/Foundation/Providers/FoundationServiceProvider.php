<?php

namespace Elegant\Foundation\Providers;

use Elegant\Contracts\Hook\PostControllerConstructor;
use Elegant\Http\Client\Factory;

class FoundationServiceProvider implements PostControllerConstructor
{
    public function postControllerConstructor(&$params)
    {
        app('http', new Factory);
    }
}
