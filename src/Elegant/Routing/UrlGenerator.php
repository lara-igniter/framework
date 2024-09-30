<?php

namespace Elegant\Routing;

use Elegant\Contracts\Routing\UrlGenerator as UrlGeneratorContract;
use Elegant\Routing\Exceptions\RouteNotFoundException;
use Elegant\Support\Arr;
use Elegant\Support\Str;
use Elegant\Support\Traits\Macroable;
use MY_Input;
use MY_Session;

class UrlGenerator implements UrlGeneratorContract
{
    use Macroable;

    /**
     * The route collection.
     *
     * @var \Elegant\Routing\RouteBuilder
     */
    protected RouteBuilder $route;

    /**
     * The request instance.
     *
     * @var \MY_Input $request
     */
    protected MY_Input $request;

    /**
     * The forced URL root.
     *
     * @var string
     */
    protected string $forcedRoot;

    /**
     * The forced scheme for URLs.
     *
     * @var string
     */
    protected string $forceScheme;

    /**
     * A cached copy of the URL root for the current request.
     *
     * @var string|null
     */
    protected ?string $cachedRoot;

    /**
     * A cached copy of the URL scheme for the current request.
     *
     * @var string|null
     */
    protected ?string $cachedScheme;

    /**
     * The session resolver callable.
     *
     * @var callable
     */
    protected $sessionResolver;

    /**
     * The route URL generator instance.
     *
     * @var \Elegant\Routing\RouteUrlGenerator|null
     */
    protected $routeGenerator;

    /**
     * Create a new URL Generator instance.
     *
     * @param \Elegant\Routing\RouteBuilder $route
     * @param \MY_Input $request
     * @return void
     */
    public function __construct(RouteBuilder $route, MY_Input $request)
    {
        $this->route = $route;

        $this->setRequest($request);

    }

    /**
     * Get the URL for the previous request.
     *
     * @param mixed $fallback
     * @return string
     */
    public function previous($fallback = false): string
    {
        $referrer = $this->request->server('HTTP_REFERER');

        $url = $referrer ? $this->to($referrer) : $this->getPreviousUrlFromSession();

        if ($url) {
            return $url;
        } elseif ($fallback) {
            return $this->to($fallback);
        }

        return $this->to('/');
    }

    /**
     * Get the previous URL from the session if possible.
     *
     * @return string|null
     */
    protected function getPreviousUrlFromSession(): ?string
    {
        $session = $this->getSession();

        return $session ? $session->userdata('_previous')['url'] : null;
    }

    /**
     * Generate an absolute URL to the given path.
     *
     * @param string $path
     * @param mixed $extra
     * @param bool|null $secure
     * @return string
     */
    public function to(string $path, $extra = [], bool $secure = null): string
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $parameters = $this->formatParameters($extra);

        $tail = preg_replace_callback('/\\{num:(\w+)\}/', function ($matches) use ($parameters) {
            $key = $matches[1];
            return $parameters[$key] ?? $matches[0];
        }, $path);

        $root = $this->formatRoot($this->formatScheme($secure));

        [$path, $query] = $this->extractQueryString($path);

        return $this->format(
                $root, '/' . trim('/' . $tail, '/')
            ) . $query;
    }

    /**
     * Get the URL to a named route.
     *
     * @param string $name
     * @param mixed $parameters
     * @param bool $absolute
     * @return string
     *
     * @throws \Elegant\Routing\Exceptions\RouteNotFoundException
     * @throws \Exception
     */
    public function route(string $name, $parameters = [], bool $absolute = true): string
    {
        if (!is_null($route = $this->route->getByName($name))) {
            return $this->toRoute($route, $parameters, $absolute);
        }

        throw new RouteNotFoundException("Route [{$name}] not defined.");
    }

    /**
     * Get the URL for a given route instance.
     *
     * @param \Elegant\Routing\Route|string $route
     * @param mixed $parameters
     * @param bool $absolute
     * @return string
     *
     * @throws \Exception
     */
    public function toRoute($route, $parameters, bool $absolute): string
    {
        return $this->routeUrl()->to($route, $parameters, $absolute);
    }

    /**
     * Get the default scheme for a raw URL.
     *
     * @param bool|null $secure
     * @return string
     */
    public function formatScheme(bool $secure = null): ?string
    {
        if (!is_null($secure)) {
            return $secure ? 'https://' : 'http://';
        }

        if (is_null($this->cachedScheme)) {
            $this->cachedScheme = $this->forceScheme ?: $this->request->server('REQUEST_SCHEME') . '://';
        }

        return $this->cachedScheme;
    }

    /**
     * Extract the query string from the given path.
     *
     * @param string $path
     * @return array
     */
    protected function extractQueryString(string $path): array
    {
        if (($queryPosition = strpos($path, '?')) !== false) {
            return [
                substr($path, 0, $queryPosition),
                substr($path, $queryPosition),
            ];
        }

        return [$path, ''];
    }

    /**
     * Get the base URL for the request.
     *
     * @param string $scheme
     * @param string|null $root
     * @return string
     */
    public function formatRoot(string $scheme, string $root = null): string
    {
        if (is_null($root)) {
            if (is_null($this->cachedRoot)) {
                $this->cachedRoot = $this->forcedRoot ?: $this->request->server('HTTP_ORIGIN');
            }

            $root = $this->cachedRoot;
        }

        $start = Str::startsWith($root, 'http://') ? 'http://' : 'https://';

        return preg_replace('~' . $start . '~', $scheme, $root, 1);
    }

    /**
     * Format the given URL segments into a single URL.
     *
     * @param string $root
     * @param string $path
     * @return string
     */
    public function format(string $root, string $path): string
    {
        $path = '/' . trim($path, '/');

        return trim($root . $path, '/');
    }

    /**
     * Determine if the given path is a valid URL.
     *
     * @param string $path
     * @return bool
     */
    public function isValidUrl(string $path): bool
    {
        if (!preg_match('~^(#|//|https?://|(mailto|tel|sms):)~', $path)) {
            return filter_var($path, FILTER_VALIDATE_URL) !== false;
        }

        return true;
    }

    /**
     * Format the array of URL parameters.
     *
     * @param mixed|array $parameters
     * @return array
     */
    public function formatParameters($parameters): array
    {
        $parameters = Arr::wrap($parameters);

        foreach ($parameters as $key => $parameter) {
            $parameters[$key] = $parameter;
        }

        return $parameters;
    }

    /**
     * Get the Route URL generator instance.
     *
     * @return \Elegant\Routing\RouteUrlGenerator
     */
    protected function routeUrl()
    {
        if (!$this->routeGenerator) {
            $this->routeGenerator = new RouteUrlGenerator($this, $this->request);
        }

        return $this->routeGenerator;
    }

    /**
     * Get the request instance.
     *
     * @return \MY_Input $request
     */
    public function getRequest(): MY_Input
    {
        return $this->request;
    }

    /**
     * Set the current request instance.
     *
     * @param \MY_Input $request
     * @return void
     */
    public function setRequest(MY_Input $request)
    {
        $this->request = $request;
    }

    /**
     * Get the session implementation from the resolver.
     *
     * @return \MY_Session|null
     */
    protected function getSession(): ?MY_Session
    {
        if ($this->sessionResolver) {
            return call_user_func($this->sessionResolver);
        }
    }

    /**
     * Set the session resolver for the generator.
     *
     * @param callable $sessionResolver
     * @return $this
     */
    public function setSessionResolver(callable $sessionResolver): UrlGenerator
    {
        $this->sessionResolver = $sessionResolver;

        return $this;
    }
}
