<?php

namespace Elegant\Auth\Middleware;

use Elegant\Auth\AuthenticationException;
use Elegant\Routing\Contracts\MiddlewareInterface as Middleware;
use Elegant\Http\Request;

class Authenticate implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Elegant\Http\Request $request
     * @param mixed $args
     * @return \Elegant\Http\RedirectResponse
     * @throws \Exception
     */
    public function run(Request $request, $args)
    {
        // ion_auth->logged_in() only checks session identity; auth() needs logged_user.
        // Require both so half-sessions redirect to login instead of breaking policies.
        if (!app('ion_auth')->logged_in() || !auth()) {
            try {
                $this->unauthenticated($request);
            } catch (AuthenticationException $e) {
                return redirector()->guest($e->redirectTo() ?? route('login'))->send();

//                return $request->expectsJson()
//                    ? response()->json(['message' => $exception->getMessage()], 401)
//                    : redirect()->guest($exception->redirectTo() ?? route('login'));
            }
        }
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param \Elegant\Http\Request $request
     * @return void
     *
     * @throws \Elegant\Auth\AuthenticationException
     */
    protected function unauthenticated(Request $request)
    {
        throw new AuthenticationException(
            'Unauthenticated.', $this->redirectTo($request)
        );
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param \Elegant\Http\Request $request
     * @return string|null
     */
    protected function redirectTo(Request $request): ?string
    {
        //
    }
}
