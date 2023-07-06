<?php

namespace Elegant\Foundation\Auth\Access;

trait AuthorizesRequests
{
    public function authorize($ability, $modelName)
    {
        return ci()->gate->authorize($ability, $modelName);
    }
}