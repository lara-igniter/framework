<?php

namespace Elegant\Foundation\Auth\Access;

trait AuthorizesRequests
{
    public function authorize($ability, $modelName)
    {
        return app('gate')->authorize($ability, $modelName);
    }
}