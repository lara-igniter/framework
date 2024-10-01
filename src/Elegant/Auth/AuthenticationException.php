<?php

namespace Elegant\Auth;

use Exception;

class AuthenticationException extends Exception
{
    /**
     * The path the user should be redirected to.
     *
     * @var string
     */
    protected string $redirectTo;

    /**
     * Create a new authentication exception.
     *
     * @param  string  $message
     * @param  string|null  $redirectTo
     * @return void
     */
    public function __construct(string $message = 'Unauthenticated.', string $redirectTo = null)
    {
        parent::__construct($message);

        $this->redirectTo = $redirectTo;
    }

    /**
     * Get the path the user should be redirected to.
     *
     * @return string
     */
    public function redirectTo(): string
    {
        return $this->redirectTo;
    }
}
