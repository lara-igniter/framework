<?php

namespace Elegant\Http;

use CI_Output;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Copies a returned HTTP response into CI_Output.
 *
 * public/index.php sends the kernel response once. Controllers return the
 * response object; this writes status, headers and body where CI's _display()
 * can emit them into that kernel response.
 */
class ResponseCapture
{
    /**
     * @var array<int, bool>
     */
    private static $done = [];

    /**
     * @var \Symfony\Component\HttpFoundation\Response|null
     */
    private static $response;

    /**
     * @param mixed $response
     * @param \CI_Output|null $output
     * @return void
     */
    public static function apply($response, ?CI_Output $output = null): void
    {
        if (!$response instanceof SymfonyResponse) {
            return;
        }

        self::$response = $response;

        if (self::already($response)) {
            return;
        }

        if ($output === null && function_exists('get_instance')) {
            $ci = get_instance();
            $output = ($ci && isset($ci->output)) ? $ci->output : null;
        }

        if (!$output instanceof CI_Output) {
            return;
        }

        if ($response instanceof JsonResponse) {
            $response->setResponse($output);
            $response->send();

            return;
        }

        self::mark($response);
        self::write($response, $output);
    }

    /**
     * @param object $response
     * @return bool
     */
    public static function already($response): bool
    {
        return isset(self::$done[spl_object_id($response)]);
    }

    /**
     * @param object $response
     * @return void
     */
    public static function mark($response): void
    {
        self::$done[spl_object_id($response)] = true;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    public static function response()
    {
        return self::$response;
    }

    /**
     * @return void
     */
    public static function reset(): void
    {
        self::$done = [];
        self::$response = null;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param \CI_Output $output
     * @return void
     */
    private static function write(SymfonyResponse $response, CI_Output $output): void
    {
        $response->prepare(Request::createFromGlobals());
        $output->set_status_header($response->getStatusCode());
        self::copyHeaders($response, $output);

        if ($response instanceof BinaryFileResponse) {
            $output->parse_exec_vars = false;
            $path = $response->getFile()->getPathname();
            $output->set_output(is_readable($path) ? (string) file_get_contents($path) : '');
            self::deleteFileAfterSend($response, $path);

            return;
        }

        $content = $response->getContent();
        $output->set_output(is_string($content) ? $content : '');
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param \CI_Output $output
     * @return void
     */
    private static function copyHeaders(SymfonyResponse $response, CI_Output $output): void
    {
        foreach ($response->headers->all() as $name => $values) {
            $headerName = ucwords((string) $name, '-');
            $replace = strcasecmp($headerName, 'Set-Cookie') !== 0;

            foreach ($values as $value) {
                if ($value === null) {
                    continue;
                }

                $output->set_header($headerName . ': ' . $value, $replace);
            }
        }
    }

    /**
     * @param \Symfony\Component\HttpFoundation\BinaryFileResponse $response
     * @param string $path
     * @return void
     */
    private static function deleteFileAfterSend(BinaryFileResponse $response, string $path): void
    {
        $property = new ReflectionProperty(BinaryFileResponse::class, 'deleteFileAfterSend');
        $property->setAccessible(true);

        if ($property->getValue($response) && is_file($path)) {
            unlink($path);
        }
    }
}
