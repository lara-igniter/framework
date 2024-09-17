<?php

namespace Elegant\Support;

use Elegant\Contracts\Hook\PostControllerConstructor;
use Elegant\Support\Facades\Facade;

class FacadeServiceProvider implements PostControllerConstructor
{
    public function postControllerConstructor(&$params)
    {
        Facade::setFacadeApplication(app());
    }
}
