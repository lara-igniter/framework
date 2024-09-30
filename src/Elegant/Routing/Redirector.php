<?php

namespace Elegant\Routing;

use Elegant\Http\RedirectResponse;
use Elegant\Support\Traits\Macroable;
use MY_Session;

class Redirector
{
    use Macroable;

    /**
     * The URL generator instance.
     *
     * @var \Elegant\Routing\UrlGenerator
     */
    protected UrlGenerator $generator;

    /**
     * The session store instance.
     *
     * @var \MY_Session $session
     */
    protected MY_Session $session;

    public function __construct(UrlGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Create a new redirect response to the previous location.
     *
     * @param int $status
     * @param array $headers
     * @param mixed $fallback
     * @return \Elegant\Http\RedirectResponse
     */
    public function back(int $status = 302, array $headers = [], $fallback = false)
    {
        return $this->createRedirect($this->generator->previous($fallback), $status, $headers);
    }

    /**
     * Create a new redirect response to the given path.
     *
     * @param string $path
     * @param int $status
     * @param array $headers
     * @param bool|null $secure
     * @return \Elegant\Http\RedirectResponse
     */
    public function to(string $path, int $status = 302, array $headers = [], bool $secure = null)
    {
        return $this->createRedirect($this->generator->to($path, [], $secure), $status, $headers);
    }

    /**
     * @param string $route
     * @param mixed $parameters
     * @param int $status
     * @param array $headers
     *
     * @return \Elegant\Http\RedirectResponse
     *
     * @throws \Elegant\Routing\Exceptions\RouteNotFoundException
     */
    public function route(string $route, $parameters = [], int $status = 302, array $headers = [])
    {
        return $this->to($this->generator->route($route, $parameters), $status, $headers);
    }

    /**
     * Undocumented function
     *
     * @param string $path
     * @param int $status
     * @param array $headers
     * @return \Elegant\Http\RedirectResponse
     */
    protected function createRedirect(string $path, int $status, array $headers): RedirectResponse
    {
        return tap(new RedirectResponse($path, $status, $headers), function ($redirect) {
            $redirect->setSession(app('session'));

            $redirect->setRequest($this->generator->getRequest());
        });
    }

    /**
     * Get the URL generator instance.
     *
     * @return \Elegant\Routing\UrlGenerator
     */
    public function getUrlGenerator(): UrlGenerator
    {
        return $this->generator;
    }

    /**
     * Set the session store instance.
     *
     * @param \MY_Session $session
     * @return void
     */
    public function setSession(MY_Session $session)
    {
        $this->session = $session;
    }
}
