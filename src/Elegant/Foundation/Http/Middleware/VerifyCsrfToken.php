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
        app('session')->token();

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
     * @param \MY_Input $request
     * @return bool
     */
    protected function tokensMatch(MY_Input $request): bool
    {
        $token = $this->getTokenFromRequest($request);
        $cookieToken = $_COOKIE['XSRF-TOKEN'] ?? null;

        if (
            !is_string(app('session')->token()) ||
            !is_string($token) ||
            !is_string($cookieToken)
        ) {
            return false;
        }

        return hash_equals(app('session')->token(), $token)
            && hash_equals(app('session')->token(), $cookieToken);
    }

    /**
     * Get the CSRF token from the request.
     *
     * @param \MY_Input $request
     * @return string|null
     */
    protected function getTokenFromRequest(MY_Input $request): ?string
    {
        $token = $request->post('_token');

        if (empty($token)) {
            $payload = json_decode(file_get_contents('php://input'), true);
            if (is_array($payload) && isset($payload['_token'])) {
                $token = $payload['_token'];
            }
        }

        if (empty($token)) {
            $token = $request->get_request_header('X-CSRF-TOKEN', true)
                ?: $request->get_request_header('X-XSRF-TOKEN', true);
        }

        unset($_POST['_token']);

        return $token;
    }

    /**
     * Determine if the cookie should be added to the response.
     *
     * @return bool
     */
    public function shouldAddXsrfTokenCookie(): bool
    {
        return $this->addHttpCookie &&
            (
                !isset($_COOKIE['XSRF-TOKEN']) ||
                !hash_equals($_COOKIE['XSRF-TOKEN'], app('session')->token())
            );
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
        header(
            'Set-Cookie: XSRF-TOKEN=' . app('session')->token()
            . '; Expires=' . gmdate('D, d-M-Y H:i:s T', $this->availableAt(config_item('sess_expiration')))
            . '; Max-Age=' . config_item('sess_expiration')
            . '; Path=' . config_item('cookie_path')
            . '; Domain=' . config_item('cookie_domain')
            . '; Secure'
            . '; SameSite=Strict'
        );

        $_COOKIE['XSRF-TOKEN'] = app('session')->token();
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
