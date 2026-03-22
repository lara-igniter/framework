<?php

namespace Elegant\Pagination;

use Elegant\Contracts\Hook\PostControllerConstructor;
use Elegant\Support\ServiceProvider;

class PaginationServiceProvider extends ServiceProvider implements PostControllerConstructor
{
    public function postControllerConstructor(&$params)
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'pagination');

        PaginationState::resolveUsing();
    }
}
