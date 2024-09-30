<?php

namespace Elegant\Routing;

use MY_Input;

class RouteUrlGenerator
{
    /**
     * The URL generator instance.
     *
     * @var \Elegant\Routing\UrlGenerator $url
     */
    protected $url;

    /**
     * The request instance.
     *
     * @var \MY_Input $request
     */
    protected MY_Input $request;

    /**
     * Characters that should not be URL encoded.
     *
     * @var array
     */
    public array $dontEncode = [
        '%2F' => '/',
        '%40' => '@',
        '%3A' => ':',
        '%3B' => ';',
        '%2C' => ',',
        '%3D' => '=',
        '%2B' => '+',
        '%21' => '!',
        '%2A' => '*',
        '%7C' => '|',
        '%3F' => '?',
        '%26' => '&',
        '%23' => '#',
        '%25' => '%',
    ];

    /**
     * Create a new Route URL generator.
     *
     * @param \Elegant\Routing\UrlGenerator $url
     * @param \MY_Input $request
     *
     * @return void
     */
    public function __construct(UrlGenerator $url, MY_Input $request)
    {
        $this->url = $url;
        $this->request = $request;
    }

    /**
     * Generate a URL for the given route.
     *
     * @param \Elegant\Routing\Route $route
     * @param array $parameters
     * @param bool $absolute
     * @return string
     *
     * @throws \Exception
     */
    public function to(Route $route, array $parameters = [], bool $absolute = false): string
    {
        $uri = $route->buildUrl($parameters);

        // First we will construct the entire URI including the root and query string. Once it
        // has been constructed, we'll make sure we don't have any missing parameters or we
        // will need to throw the exception to let the developers know one was not given.
        /**
         * $uri = $this->addQueryString($this->url->format(
         * $root = $this->replaceRootParameters($route, $domain, $parameters),
         * $this->replaceRouteParameters($route->uri(), $parameters),
         * $route
         * ), $parameters);
         */

        // Once we have ensured that there are no missing parameters in the URI, we will encode
        // the URI and prepare it for returning to the developer. If the URI is supposed to
        // be absolute, we will return it as-is. Otherwise, we will remove the URL's root.
        $uri = strtr(rawurlencode($uri), $this->dontEncode);

        if (!$absolute) {
            $uri = preg_replace('#^(//|[^/?])+#', '', $uri);

            if ($base = $this->request->getBaseUrl()) {
                $uri = preg_replace('#^' . $base . '#i', '', $uri);
            }

            return '/' . ltrim($uri, '/');
        }

        return $uri;
    }
}
