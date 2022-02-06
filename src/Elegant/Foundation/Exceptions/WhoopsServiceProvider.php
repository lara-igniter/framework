<?php

namespace Elegant\Foundation\Exceptions;

use Elegant\Contracts\Hook\PreSystem;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as WhoopsRun;

class WhoopsServiceProvider implements PreSystem
{
    public function preSystemHook()
    {
        $whoops = new WhoopsRun;
        $whoops->pushHandler(new PrettyPageHandler());
        $whoops->register();
    }
}
