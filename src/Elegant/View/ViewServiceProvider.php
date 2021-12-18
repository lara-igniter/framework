<?php

namespace Elegant\View;

use Elegant\Foundation\Hooks\Contracts\PostControllerConstructorHookInterface;
use Elegant\Foundation\Hooks\Contracts\PreSystemHookInterface;
use Elegant\Support\Facades\View;

class ViewServiceProvider implements PreSystemHookInterface, PostControllerConstructorHookInterface
{
    public function preSystemHook()
    {
        if (!file_exists(APPPATH . '/config/view.php')) {
            copy(realpath(dirname(__DIR__) . './Resources/ConfigView.php'), APPPATH . '/config/view.php');
        }
    }

    public function postControllerConstructorHook(&$params)
    {
        app()->view = new View();
    }
}
