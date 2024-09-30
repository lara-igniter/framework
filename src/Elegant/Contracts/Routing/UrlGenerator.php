<?php

namespace Elegant\Contracts\Routing;

interface UrlGenerator
{
    /**
     * Get the URL for the previous request.
     *
     * @param mixed $fallback
     * @return string
     */
    public function previous($fallback = false): string;

    /**
     * Generate an absolute URL to the given path.
     *
     * @param string $path
     * @param mixed $extra
     * @param bool|null $secure
     * @return string
     */
    public function to(string $path, $extra = [], bool $secure = null): string;

    /**
     * Get the URL to a named route.
     *
     * @param string $name
     * @param mixed $parameters
     * @param bool $absolute
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function route(string $name, $parameters = [], bool $absolute = true): string;
}
