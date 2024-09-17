<?php

namespace Elegant\Foundation;

use Elegant\Contracts\Hook\Boot;
use Elegant\Contracts\Hook\PostControllerConstructor;
use Elegant\Contracts\Hook\PreSystem;
use Elegant\Filesystem\Filesystem;
use Elegant\Support\Facades\Date;
use Elegant\Support\Number;

class AppServiceProvider implements Boot, PreSystem, PostControllerConstructor
{
    public function boot()
    {
        date_default_timezone_set(config_item('timezone'));

        Date::setlocale(config_item('locale'));

        Number::useLocale(config_item('locale'));
    }

    public function preSystem()
    {
        Autoloader::register();
    }

    public function postControllerConstructor(&$params)
    {
        app('files', new Filesystem());
    }
}
