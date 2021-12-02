<?php

namespace Elegant\Foundation\Hooks;

use Elegant\Foundation\Hooks\Contracts\PreSystemHookInterface;

class View implements PreSystemHookInterface
{
    public function preSystemHook()
    {
        if (!file_exists(APPPATH . '/config/view.php')) {
            copy(realpath(dirname(__DIR__) . '../../View/Resources/ConfigView.php'), APPPATH . '/config/view.php');
        }
    }
}
