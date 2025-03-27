<?php

namespace Elegant\Foundation\Providers;

use Elegant\Contracts\Hook\PreSystem;
use Elegant\Foundation\Autoloader;

class AutoloadServiceProvider implements PreSystem
{
    public function preSystem()
    {
        Autoloader::register();
    }
}
