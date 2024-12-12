<?php

namespace Elegant\Http;

use Elegant\Support\Str;
use Elegant\Support\Traits\ForwardsCalls;
use Elegant\Support\Traits\Macroable;
use MY_Input;
use MY_Session;
use Symfony\Component\HttpFoundation\RedirectResponse as BaseRedirectResponse;

class RedirectResponse extends BaseRedirectResponse
{
    use ForwardsCalls, Macroable {
        Macroable::__call as macroCall;
    }

    /**
     * The request instance.
     *
     * @var \MY_Input $request
     */
    protected MY_Input $request;

    /**
     * The session store instance.
     *
     * @var \MY_Session
     */
    protected MY_Session $session;


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
     * @return \MY_Input|null
     */
    public function getRequest(): ?MY_Input
    {
        return $this->request;
    }

    /**
     * Set the request instance.
     *
     * @param \MY_Input $request
     * @return void
     */
    public function setRequest(MY_Input $request)
    {
        $this->request = $request;
    }

    /**
     * Get the session store instance.
     *
     * @return \MY_Session|null
     */
    public function getSession(): ?MY_Session
    {
        return $this->session;
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
