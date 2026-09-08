<?php

namespace Elegant\Http;

use Elegant\Support\Str;
use Elegant\Support\Traits\ForwardsCalls;
use Elegant\Support\Traits\Macroable;
use Elegant\Http\Request;
use Elegant\Session\Store;
use Symfony\Component\HttpFoundation\RedirectResponse as BaseRedirectResponse;

class RedirectResponse extends BaseRedirectResponse
{
    use ForwardsCalls, Macroable {
        Macroable::__call as macroCall;
    }

    /**
     * The request instance.
     *
     * @var \Elegant\Http\Request $request
     */
    protected Request $request;

    /**
     * The session store instance.
     *
     * @var \Elegant\Session\Store
     */
    protected Store $session;


    /**
     * Flash a piece of data to the session.
     *
     * @param string|array $key
     * @param mixed $value
     * @return $this
     */
    public function with($key, $value = null): RedirectResponse
    {
        $key = is_array($key) ? $key : [$key => $value];

        foreach ($key as $k => $v) {
            $this->session->set_flashdata($k, $v);
        }

        return $this;
    }

    /**
     * Flash validates input to the session.
     *
     * @return $this
     */
    public function withInput(): RedirectResponse
    {
        $errors = view()->shared('errors');

        view()->share('errors', $errors);

        $this->with('errors', $errors->toArray());

        return $this;
    }

    /**
     * Flash a container of errors to the session.
     *
     * @param array $provider
     * @return $this
     */
    public function withErrors(array $provider): RedirectResponse
    {
        $value = $this->parseErrors($provider);

        $this->with('errors', $value);

        return $this;
    }

    /**
     * Parse the given errors into an appropriate value.
     *
     * @param array $provider
     * @return array
     */
    protected function parseErrors(array $provider): array
    {
        return $provider;
    }

    /**
     * Get the request instance.
     *
     * @return \Elegant\Http\Request|null
     */
    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * Set the request instance.
     *
     * @param \Elegant\Http\Request $request
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get the session store instance.
     *
     * @return \Elegant\Session\Store|null
     */
    public function getSession(): ?Store
    {
        return $this->session;
    }

    /**
     * Set the session store instance.
     *
     * @param \Elegant\Session\Store $session
     * @return void
     */
    public function setSession(Store $session)
    {
        $this->session = $session;
    }

    /**
     * Dynamically bind flash data in the session.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (Str::startsWith($method, 'with')) {
            return $this->with(Str::snake(substr($method, 4)), $parameters[0]);
        }

        static::throwBadMethodCallException($method);
    }
}
