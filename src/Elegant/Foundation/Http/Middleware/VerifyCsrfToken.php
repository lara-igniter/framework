<?php

namespace Elegant\Foundation\Http\Middleware;

use Elegant\Routing\Contracts\MiddlewareInterface as Middleware;
use Elegant\Support\InteractsWithTime;
use MY_Input;

class VerifyCsrfToken implements Middleware
{
    use InteractsWithTime;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected array $except = [
        //
    ];

    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected bool $addHttpCookie = true;

    /**
     * Handle an incoming request.
     *
     * @param \MY_Input $request
     * @param mixed $args
     * @return void
     */
    public function run(MY_Input $request, $args)
    {
        if (
            $this->isReading($request) ||
            $this->runningInConsole() ||
            $this->isGloballyDisabled() ||
            $this->inExceptArray($request)
        ) {
            $this->addCookieToResponse();
            return;
        }

        if (!$this->tokensMatch($request)) {
            $this->tokenMismatch();
        }

        $this->addCookieToResponse();
    }

    /**
     * Determine if the HTTP request uses a 'read' verb.
     *
     * @param \MY_Input $request
     * @return bool
     */
    protected function isReading(MY_Input $request): bool
    {
        return in_array($request->method(true), ['HEAD', 'GET', 'OPTIONS']);
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    protected function runningInConsole(): bool
    {
        return is_cli();
    }

    /**
     * Determine if CSRF protection is globally disabled.
     *
     * @return bool
     */
    protected function isGloballyDisabled(): bool
    {
        return in_array('*', $this->except, true);
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param \MY_Input $request
     * @return bool
     */
    protected function inExceptArray(MY_Input $request): bool
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->fullUrlIs($except) || $request->is($except)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * The session token is always the single source of truth. The XSRF-TOKEN
     * cookie is only ever used to *transport* the token (e.g. for AJAX
     * clients that echo it back via the X-XSRF-TOKEN header); it must never
     * be trusted on its own, otherwise a stale cookie left over from a
     * destroyed/expired session (e.g. after logout) could satisfy the check
     * without ever matching the current session.
     *
     * @param \MY_Input $request
     * @return bool
     */
    protected function tokensMatch(MY_Input $request): bool
    {
        $token = $this->getTokenFromRequest($request);
        $sessionToken = app('session')->token();

        return is_string($sessionToken) && is_string($token) && hash_equals($sessionToken, $token);
    }


    /**
     * Get the CSRF token from the request.
     *
     * @param \MY_Input $request
     * @return string|null
     */
    protected function getTokenFromRequest(MY_Input $request): ?string
    {
        $token = $request->input('_token') ?: $request->get_request_header('X-CSRF-TOKEN');

        if (! $token && $header = $request->get_request_header('X-XSRF-TOKEN')) {
            $token = $header;
        }

        return $token;
    }

    /**
     * Determine if the cookie should be added to the response.
     *
     * @return bool
     */
    public function shouldAddXsrfTokenCookie(): bool
    {
        if (!$this->addHttpCookie) {
            return false;
        }

        $sessionToken = app('session')->token();

        return empty($_COOKIE['XSRF-TOKEN']) ||
            !hash_equals($_COOKIE['XSRF-TOKEN'], $sessionToken);
    }

    /**
     * Add the CSRF token to the response cookies.
     *
     * @return void
     */
    protected function addCookieToResponse(): void
    {
        if (!$this->shouldAddXsrfTokenCookie()) {
            return;
        }

        $this->newCookie();
    }

    protected function newCookie()
    {
        $token = app('session')->token();
        $secure = config_item('cookie_secure') ? '; Secure' : '';

        header(
            'Set-Cookie: XSRF-TOKEN=' . $token
            . '; Expires=' . gmdate('D, d-M-Y H:i:s T', $this->availableAt(config_item('sess_expiration')))
            . '; Max-Age=' . config_item('sess_expiration')
            . '; Path=' . config_item('cookie_path')
            . '; Domain=' . config_item('cookie_domain')
            . $secure
            . '; SameSite=Lax',
            false
        );

        $_COOKIE['XSRF-TOKEN'] = $token;
    }

    /**
     * Handle token mismatch.
     *
     * @return void
     */
    protected function tokenMismatch(): void
    {
        log_message('error', 'CSRF token mismatch.');

        abort(419);
    }
}
