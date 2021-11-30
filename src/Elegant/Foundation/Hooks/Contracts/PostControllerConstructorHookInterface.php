<?php

namespace Elegant\Foundation\Hooks\Contracts;

interface PostControllerConstructorHookInterface
{
    /**
     * "post_controller_constructor" hook
     *
     * @param array $params
     *
     * @return void
     */
    public function postControllerConstructorHook(&$params);
}
