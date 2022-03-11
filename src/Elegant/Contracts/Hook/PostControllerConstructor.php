<?php

namespace Elegant\Contracts\Hook;

interface PostControllerConstructor
{
    /**
     * "post_controller_constructor" hook
     *
     * @param array $params
     *
     * @return void
     */
    public function postControllerConstructor(&$params);
}
