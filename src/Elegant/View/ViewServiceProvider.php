<?php

namespace Elegant\View;

use Elegant\Foundation\Hooks\Contracts\PreSystemHookInterface;

class ViewServiceProvider implements PreSystemHookInterface
{
    public function preSystemHook()
    {
        if (!file_exists(APPPATH . '/config/view.php')) {
            copy(realpath(dirname(__DIR__) . './Resources/ConfigView.php'), APPPATH . '/config/view.php');
        }
    }
}
