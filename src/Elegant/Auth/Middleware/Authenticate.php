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
     * @return void
     * @throws \Exception
     */
    public function run($args)
    {
        if (!app('ion_auth')->logged_in()) {
            try {
                $this->unauthenticated(app('input'));
            } catch (AuthenticationException $e) {
                redirector()->guest($e->redirectTo() ?? route('login'));

//                return $request->expectsJson()
//                    ? response()->json(['message' => $exception->getMessage()], 401)
//                    : redirect()->guest($exception->redirectTo() ?? route('login'));
            }
        }
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param  \MY_Input  $request
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
     * @param  \MY_Input  $request
     * @return string|null
     */
    protected function redirectTo(MY_Input $request): ?string
    {
        //
    }
}
