<?php

namespace Elegant\Contracts\Filesystem;

interface Factory
{
    /**
     * Get a filesystem implementation.
     *
     * @param  string|null  $name
     * @return \Elegant\Contracts\Filesystem\Filesystem
     */
    public function disk($name = null);
}