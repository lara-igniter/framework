<?php

namespace Elegant\Foundation;

use Elegant\Contracts\Hook\PostControllerConstructor;
use Elegant\Filesystem\Filesystem;

class AppServiceProvider implements PostControllerConstructor
{
    public function postControllerConstructorHook(&$params)
    {
        app()->files = new Filesystem();
    }
}
