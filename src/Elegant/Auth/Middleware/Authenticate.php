<?php

namespace Elegant\Auth\Middleware;

use Elegant\Auth\AuthenticationException;
use Elegant\Routing\Contracts\MiddlewareInterface as Middleware;
use MY_Input;

class Authenticate implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param \MY_Input $request
     * @param mixed $args
     * @return \Elegant\Http\RedirectResponse
     * @throws \Exception
     */
    public function run(MY_Input $request, $args)
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
     * @param \MY_Input $request
     * @return void
     *
     * @throws \Elegant\Auth\AuthenticationException
     */
    protected function unauthenticated(MY_Input $request)
    {
        throw new AuthenticationException(
            'Unauthenticated.', $this->redirectTo($request)
        );
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param \MY_Input $request
     * @return string|null
     */
    protected function redirectTo(MY_Input $request): ?string
    {
        //
    }
}
