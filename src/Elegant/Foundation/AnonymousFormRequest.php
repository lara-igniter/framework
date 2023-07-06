<?php

namespace Elegant\Foundation;

use Elegant\Foundation\Http\FormRequest;

class AnonymousFormRequest extends FormRequest
{
    public array $rules;

    public function __construct($request_rules = [])
    {
        parent::__construct();

        $this->rules = $request_rules;
    }

    public function rules(): array
    {
        return $this->rules;
    }
}