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

    public function __construct(UrlGenerator $generator, MY_Session $session)
    {
        $this->generator = $generator;
        $this->session = $session;
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
     * Create a new redirect response, while putting the current URL in the session.
     *
     * @param string $path
     * @param int $status
     * @param array $headers
     * @param bool|null $secure
     * @return \Elegant\Http\RedirectResponse
     */
    public function guest(string $path, int $status = 302, array $headers = [], bool $secure = null)
    {
        $request = $this->generator->getRequest();

        $intended = $request->method(true) === 'GET' && $request->route() && !$request->expectsJson()
            ? $this->generator->full()
            : $this->generator->previous();

        if ($intended) {
            $this->setIntendedUrl($intended);
        }

        return $this->to($path, $status, $headers, $secure);
    }

    /**
     * Create a new redirect response to the previously intended location.
     *
     * @param string $default
     * @param int $status
     * @param array $headers
     * @param bool|null $secure
     * @return \Elegant\Http\RedirectResponse
     */
    public function intended(string $default = '/', int $status = 302, array $headers = [], bool $secure = null)
    {
        $path = $this->session->pull_userdata('url.intended', $default);

        return $this->to($path, $status, $headers, $secure);
    }

    /**
     * Set the intended url.
     *
     * @param string $url
     * @return void
     */
    public function setIntendedUrl(string $url)
    {
        $this->session->set_userdata('url', [
            'intended' => $url
        ]);
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
     * Create a new redirect response to an external URL (no validation).
     *
     * @param string $path
     * @param int $status
     * @param array $headers
     * @return \Elegant\Http\RedirectResponse
     */
    public function away(string $path, int $status = 302, array $headers = [])
    {
        return $this->createRedirect($path, $status, $headers);
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
