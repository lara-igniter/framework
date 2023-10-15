<?php

namespace Elegant\Support\Facades;

/**
 * @method static \GuzzleHttp\Promise\PromiseInterface response($body = null, $status = 200, $headers = [])
 * @method static \Elegant\Http\Client\Factory fake($callback = null)
 * @method static \Elegant\Http\Client\PendingRequest accept(string $contentType)
 * @method static \Elegant\Http\Client\PendingRequest acceptJson()
 * @method static \Elegant\Http\Client\PendingRequest asForm()
 * @method static \Elegant\Http\Client\PendingRequest asJson()
 * @method static \Elegant\Http\Client\PendingRequest asMultipart()
 * @method static \Elegant\Http\Client\PendingRequest async()
 * @method static \Elegant\Http\Client\PendingRequest attach(string|array $name, string $contents = '', string|null $filename = null, array $headers = [])
 * @method static \Elegant\Http\Client\PendingRequest baseUrl(string $url)
 * @method static \Elegant\Http\Client\PendingRequest beforeSending(callable $callback)
 * @method static \Elegant\Http\Client\PendingRequest bodyFormat(string $format)
 * @method static \Elegant\Http\Client\PendingRequest contentType(string $contentType)
 * @method static \Elegant\Http\Client\PendingRequest dd()
 * @method static \Elegant\Http\Client\PendingRequest dump()
 * @method static \Elegant\Http\Client\PendingRequest retry(int $times, int $sleep = 0)
 * @method static \Elegant\Http\Client\PendingRequest sink(string|resource $to)
 * @method static \Elegant\Http\Client\PendingRequest stub(callable $callback)
 * @method static \Elegant\Http\Client\PendingRequest timeout(int $seconds)
 * @method static \Elegant\Http\Client\PendingRequest withBasicAuth(string $username, string $password)
 * @method static \Elegant\Http\Client\PendingRequest withBody(resource|string $content, string $contentType)
 * @method static \Elegant\Http\Client\PendingRequest withCookies(array $cookies, string $domain)
 * @method static \Elegant\Http\Client\PendingRequest withDigestAuth(string $username, string $password)
 * @method static \Elegant\Http\Client\PendingRequest withHeaders(array $headers)
 * @method static \Elegant\Http\Client\PendingRequest withMiddleware(callable $middleware)
 * @method static \Elegant\Http\Client\PendingRequest withOptions(array $options)
 * @method static \Elegant\Http\Client\PendingRequest withToken(string $token, string $type = 'Bearer')
 * @method static \Elegant\Http\Client\PendingRequest withUserAgent(string $userAgent)
 * @method static \Elegant\Http\Client\PendingRequest withoutRedirecting()
 * @method static \Elegant\Http\Client\PendingRequest withoutVerifying()
 * @method static array pool(callable $callback)
 * @method static \Elegant\Http\Client\Response delete(string $url, array $data = [])
 * @method static \Elegant\Http\Client\Response get(string $url, array|string|null $query = null)
 * @method static \Elegant\Http\Client\Response head(string $url, array|string|null $query = null)
 * @method static \Elegant\Http\Client\Response patch(string $url, array $data = [])
 * @method static \Elegant\Http\Client\Response post(string $url, array $data = [])
 * @method static \Elegant\Http\Client\Response put(string $url, array $data = [])
 * @method static \Elegant\Http\Client\Response send(string $method, string $url, array $options = [])
 * @method static \Elegant\Http\Client\ResponseSequence fakeSequence(string $urlPattern = '*')
 * @method static void assertSent(callable $callback)
 * @method static void assertNotSent(callable $callback)
 * @method static void assertNothingSent()
 * @method static void assertSentCount(int $count)
 * @method static void assertSequencesAreEmpty()
 *
 * @see \Elegant\Http\Client\Factory
 */
class Http extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'http';
    }
}