<?php

namespace Elegant\Foundation;

use Elegant\Filesystem\Filesystem;
use Elegant\Foundation\Hooks\Contracts\PostControllerConstructorHookInterface;

class AppServiceProvider implements PostControllerConstructorHookInterface
{
    public function postControllerConstructorHook(&$params)
    {
        app()->files = new Filesystem();
    }
}
