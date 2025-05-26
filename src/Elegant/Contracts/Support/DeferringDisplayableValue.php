<?php

namespace Elegant\Contracts\Support;

interface DeferringDisplayableValue
{
    /**
     * Resolve the displayable value that the class is deferring.
     *
     * @return \Elegant\Contracts\Support\Htmlable|string
     */
    public function resolveDisplayableValue();
}
