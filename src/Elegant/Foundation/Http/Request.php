<?php

namespace Elegant\Foundation\Http;

/**
 * Lightweight HTTP request used by the testing Http Kernel.
 */
class Request
{
    /**
     * @var string
     */
    public $method;

    /**
     * @var string
     */
    public $uri;

    /**
     * @var array
     */
    public $query = [];

    /**
     * @var array
     */
    public $request = [];

    /**
     * @var array
     */
    public $cookies = [];

    /**
     * @var array
     */
    public $files = [];

    /**
     * @var array
     */
    public $server = [];

    /**
     * @var string|null
     */
    public $content;

    /**
     * @param string $method
     * @param string $uri
     * @param array $parameters
     * @param array $cookies
     * @param array $files
     * @param array $server
     * @param string|null $content
     * @return static
     */
    public static function create($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $request = new static();
        $request->method = strtoupper($method);

        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if ($request->method === 'GET') {
            $query = array_merge($query, $parameters);
            $parameters = [];
        }

        $request->uri = $path;
        $request->query = $query;
        $request->request = $parameters;
        $request->cookies = $cookies;
        $request->files = $files;
        $request->server = $server;
        $request->content = $content;

        return $request;
    }

    /**
     * Create a request from PHP global variables.
     *
     * @return static
     */
    public static function capture()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parameters = strtoupper($method) === 'GET' ? $_GET : $_POST;

        return static::create($method, $uri, $parameters, $_COOKIE, $_FILES, $_SERVER);
    }

    /**
     * @return string
     */
    public function getRequestUri()
    {
        $queryString = http_build_query($this->query);

        return $this->uri . ($queryString !== '' ? '?' . $queryString : '');
    }
}
