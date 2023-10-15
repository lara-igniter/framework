<?php

namespace Elegant\Http\Client;

use Closure;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Elegant\Support\Str;
use Elegant\Support\Traits\Macroable;

/**
 * @method \Elegant\Http\Client\PendingRequest accept(string $contentType)
 * @method \Elegant\Http\Client\PendingRequest acceptJson()
 * @method \Elegant\Http\Client\PendingRequest asForm()
 * @method \Elegant\Http\Client\PendingRequest asJson()
 * @method \Elegant\Http\Client\PendingRequest asMultipart()
 * @method \Elegant\Http\Client\PendingRequest async()
 * @method \Elegant\Http\Client\PendingRequest attach(string|array $name, string $contents = '', string|null $filename = null, array $headers = [])
 * @method \Elegant\Http\Client\PendingRequest baseUrl(string $url)
 * @method \Elegant\Http\Client\PendingRequest beforeSending(callable $callback)
 * @method \Elegant\Http\Client\PendingRequest bodyFormat(string $format)
 * @method \Elegant\Http\Client\PendingRequest contentType(string $contentType)
 * @method \Elegant\Http\Client\PendingRequest dd()
 * @method \Elegant\Http\Client\PendingRequest dump()
 * @method \Elegant\Http\Client\PendingRequest retry(int $times, int $sleep = 0)
 * @method \Elegant\Http\Client\PendingRequest sink(string|resource $to)
 * @method \Elegant\Http\Client\PendingRequest stub(callable $callback)
 * @method \Elegant\Http\Client\PendingRequest timeout(int $seconds)
 * @method \Elegant\Http\Client\PendingRequest withBasicAuth(string $username, string $password)
 * @method \Elegant\Http\Client\PendingRequest withBody(resource|string $content, string $contentType)
 * @method \Elegant\Http\Client\PendingRequest withCookies(array $cookies, string $domain)
 * @method \Elegant\Http\Client\PendingRequest withDigestAuth(string $username, string $password)
 * @method \Elegant\Http\Client\PendingRequest withHeaders(array $headers)
 * @method \Elegant\Http\Client\PendingRequest withMiddleware(callable $middleware)
 * @method \Elegant\Http\Client\PendingRequest withOptions(array $options)
 * @method \Elegant\Http\Client\PendingRequest withToken(string $token, string $type = 'Bearer')
 * @method \Elegant\Http\Client\PendingRequest withUserAgent(string $userAgent)
 * @method \Elegant\Http\Client\PendingRequest withoutRedirecting()
 * @method \Elegant\Http\Client\PendingRequest withoutVerifying()
 * @method array pool(callable $callback)
 * @method \Elegant\Http\Client\Response delete(string $url, array $data = [])
 * @method \Elegant\Http\Client\Response get(string $url, array|string|null $query = null)
 * @method \Elegant\Http\Client\Response head(string $url, array|string|null $query = null)
 * @method \Elegant\Http\Client\Response patch(string $url, array $data = [])
 * @method \Elegant\Http\Client\Response post(string $url, array $data = [])
 * @method \Elegant\Http\Client\Response put(string $url, array $data = [])
 * @method \Elegant\Http\Client\Response send(string $method, string $url, array $options = [])
 *
 * @see \Elegant\Http\Client\PendingRequest
 */
class Factory
{
    use Macroable {
        __call as macroCall;
    }

    /**
     * The stub callables that will handle requests.
     *
     * @var \Elegant\Support\Collection
     */
    protected $stubCallbacks;

    /**
     * Indicates if the factory is recording requests and responses.
     *
     * @var bool
     */
    protected $recording = false;

    /**
     * The recorded response array.
     *
     * @var array
     */
    protected $recorded = [];

    /**
     * All created response sequences.
     *
     * @var array
     */
    protected $responseSequences = [];

    /**
     * Create a new factory instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->stubCallbacks = collect();
    }

    /**
     * Create a new response instance for use during stubbing.
     *
     * @param  array|string  $body
     * @param  int  $status
     * @param  array  $headers
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function response($body = null, $status = 200, $headers = [])
    {
        if (is_array($body)) {
            $body = json_encode($body);

            $headers['Content-Type'] = 'application/json';
        }

        $response = new Psr7Response($status, $headers, $body);

        return class_exists(GuzzleHttp\Promise\Create::class)
            ? \GuzzleHttp\Promise\Create::promiseFor($response)
            : \GuzzleHttp\Promise\promise_for($response);
    }

    /**
     * Get an invokable object that returns a sequence of responses in order for use during stubbing.
     *
     * @param  array  $responses
     * @return \Elegant\Http\Client\ResponseSequence
     */
    public function sequence(array $responses = [])
    {
        return $this->responseSequences[] = new ResponseSequence($responses);
    }

    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     *
     * @param  callable|array  $callback
     * @return $this
     */
    public function fake($callback = null)
    {
        $this->record();

        if (is_null($callback)) {
            $callback = function () {
                return static::response();
            };
        }

        if (is_array($callback)) {
            foreach ($callback as $url => $callable) {
                $this->stubUrl($url, $callable);
            }

            return $this;
        }

        $this->stubCallbacks = $this->stubCallbacks->merge(collect([
            $callback instanceof Closure
                ? $callback
                : function () use ($callback) {
                return $callback;
            },
        ]));

        return $this;
    }

    /**
     * Register a response sequence for the given URL pattern.
     *
     * @param  string  $url
     * @return \Elegant\Http\Client\ResponseSequence
     */
    public function fakeSequence($url = '*')
    {
        return tap($this->sequence(), function ($sequence) use ($url) {
            $this->fake([$url => $sequence]);
        });
    }

    /**
     * Stub the given URL using the given callback.
     *
     * @param  string  $url
     * @param  \Elegant\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface|callable  $callback
     * @return $this
     */
    public function stubUrl($url, $callback)
    {
        return $this->fake(function ($request, $options) use ($url, $callback) {
            if (! Str::is(Str::start($url, '*'), $request->url())) {
                return;
            }

            return $callback instanceof Closure || $callback instanceof ResponseSequence
                ? $callback($request, $options)
                : $callback;
        });
    }

    /**
     * Begin recording request / response pairs.
     *
     * @return $this
     */
    protected function record()
    {
        $this->recording = true;

        return $this;
    }

    /**
     * Record a request response pair.
     *
     * @param  \Elegant\Http\Client\Request  $request
     * @param  \Elegant\Http\Client\Response  $response
     * @return void
     */
    public function recordRequestResponsePair($request, $response)
    {
        if ($this->recording) {
            $this->recorded[] = [$request, $response];
        }
    }


    /**
     * Get a collection of the request / response pairs matching the given truth test.
     *
     * @param  callable  $callback
     * @return \Elegant\Support\Collection
     */
    public function recorded($callback = null)
    {
        if (empty($this->recorded)) {
            return collect();
        }

        $callback = $callback ?: function () {
            return true;
        };

        return collect($this->recorded)->filter(function ($pair) use ($callback) {
            return $callback($pair[0], $pair[1]);
        });
    }

    /**
     * Create a new pending request instance for this factory.
     *
     * @return \Elegant\Http\Client\PendingRequest
     */
    protected function newPendingRequest()
    {
        return new PendingRequest($this);
    }

    /**
     * Execute a method against a new pending request instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return tap($this->newPendingRequest(), function ($request) {
            $request->stub($this->stubCallbacks);
        })->{$method}(...$parameters);
    }
}