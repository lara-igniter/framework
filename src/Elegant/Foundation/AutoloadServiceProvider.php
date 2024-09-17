<?php

namespace Elegant\Foundation;

use Elegant\Contracts\Hook\PreSystem;

class AutoloadServiceProvider implements PreSystem
{
    public function preSystem()
    {
        Autoloader::register();
    }
}
