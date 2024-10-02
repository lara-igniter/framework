<?php

namespace Elegant\Support\Facades;

/**
 * @method static \Elegant\Http\RedirectResponse back(int $status = 302, array $headers = [], $fallback = false)
 * @method static \Elegant\Http\RedirectResponse guest(string $path, int $status = 302, array $headers = [], bool $secure = null)
 * @method static \Elegant\Http\RedirectResponse intended(string $default = '/', int $status = 302, array $headers = [], bool $secure = null)
 * @method static \Elegant\Http\RedirectResponse route(string $route, array $parameters = [], int $status = 302, array $headers = [])
 * @method static \Elegant\Http\RedirectResponse to(string $path, int $status = 302, array $headers = [], bool $secure = null)
 * @method static \Elegant\Routing\UrlGenerator getUrlGenerator()
 * @method static void setSession(\MY_Session $session)
 * @method static void setIntendedUrl(string $url)
 *
 * @see \Elegant\Routing\Redirector
 */
class Redirect extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'redirect';
    }
}
