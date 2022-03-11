<?php

namespace Elegant\Contracts\Hook;

interface CacheOverride
{
    /**
     * "cache_override" hook
     *
     * @return void
     */
    public function cacheOverride();
}
