<?php

namespace Elegant\Foundation\Hooks\Contracts;

interface PostControllerHookInterface
{
    /**
     * "post_controller" hook
     *
     * @return void
     */
    public function postControllerHook();
}
