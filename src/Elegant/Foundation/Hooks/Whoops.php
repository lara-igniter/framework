<?php

namespace Elegant\Foundation\Hooks;

use Elegant\Foundation\Hooks\Contracts\PreSystemHookInterface;

class Whoops implements PreSystemHookInterface
{
    public function preSystemHook()
    {
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
        $whoops->register();
    }
}
