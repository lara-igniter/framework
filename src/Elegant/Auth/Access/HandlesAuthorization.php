<?php

namespace Elegant\Auth\Access;

trait HandlesAuthorization
{
    protected function allow()
    {
        return TRUE;
    }

    protected function deny()
    {
        abort('403');
    }
}