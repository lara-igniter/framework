<?php

namespace Elegant\Foundation\Validation;

use Elegant\Foundation\AnonymousFormRequest;

trait ValidatesRequests
{
    public function validate($request_rules = []): AnonymousFormRequest
    {
        return new AnonymousFormRequest($request_rules);
    }
}