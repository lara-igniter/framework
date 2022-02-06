<?php

namespace Elegant\Contracts\Hook;

interface PreController
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
