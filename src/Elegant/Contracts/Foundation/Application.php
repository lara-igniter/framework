<?php

namespace Elegant\Contracts\Foundation;

interface Application
{
    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version(): string;
}
