<?php

namespace Elegant\Foundation\Hooks\Contracts;

interface PreControllerHookInterface
{
    /**
     * "pre_controller" hook
     *
     * @param array $params
     * @param string $URI
     * @param string $class
     * @param string $method
     * @return void
     */
    public function preControllerHook(&$params, &$URI, &$class, &$method);
}
