<?php

namespace Elegant\Foundation\Testing\Concerns;

use Elegant\Foundation\Http\Kernel;
use Elegant\Foundation\Http\Request;
use Elegant\Foundation\Testing\TestResponse;

trait MakesHttpRequests
{
    /**
     * @var mixed
     */
    protected $actingAsUser;

    /**
     * @var array
     */
    protected $sessionData = [];

    /**
     * @var array
     */
    protected $withoutMiddleware = [];

    /**
     * @var bool
     */
    protected $withoutMiddlewareAll = false;

    /**
     * Call the given URI and return the Response.
     *
     * @param string $method
     * @param string $uri
     * @param array $parameters
     * @param array $cookies
     * @param array $files
     * @param array $server
     * @param string|null $content
     * @return \Elegant\Foundation\Testing\TestResponse
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $kernel = $this->kernel();

        $kernel->setPendingUser($this->actingAsUser);
        $kernel->setPendingSession($this->sessionData);
        $kernel->setWithoutMiddleware($this->withoutMiddlewareAll, $this->withoutMiddleware);

        $request = Request::create(
            $method,
            $uri,
            $parameters,
            $cookies,
            $files,
            array_merge($this->serverVariables, $server),
            $content
        );

        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $payload = new \stdClass();
        $payload->status = $response->getStatusCode();
        $payload->content = $response->getContent();
        $payload->headers = $response->headers();
        $payload->view = '';
        $payload->data = [];

        return new TestResponse($payload);
    }

    /**
     * @return \Elegant\Foundation\Http\Kernel
     */
    protected function kernel()
    {
        if (! isset($this->httpKernel) || ! $this->httpKernel instanceof Kernel) {
            $this->httpKernel = new Kernel($this->app, $this->applicationBasePath());
        }

        return $this->httpKernel;
    }
}
