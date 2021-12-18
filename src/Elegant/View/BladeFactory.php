<?php

namespace Elegant\View;

class BladeFactory
{
    public static function directive($name, callable $handler)
    {
        return app()->view->compiler->directive($name, $handler);
    }
}
