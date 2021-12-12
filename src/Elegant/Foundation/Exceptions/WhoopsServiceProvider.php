<?php

namespace Elegant\Foundation\Exceptions;

use Whoops\Run as WhoopsRun;
use Whoops\Handler\PrettyPageHandler;
use Elegant\Foundation\Hooks\Contracts\PreSystemHookInterface;

class WhoopsServiceProvider implements PreSystemHookInterface
{
    public function preSystemHook()
    {
        $whoops = new WhoopsRun;
        $whoops->pushHandler(new PrettyPageHandler());
        $whoops->register();
    }
}
