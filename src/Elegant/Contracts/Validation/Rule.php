<?php

namespace Elegant\Contracts\Validation;

interface Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @return bool
     */
    public function passes(): bool;

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string;
}
