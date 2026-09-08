<?php

namespace Elegant\Http;

use CI_Output;
use Elegant\Session\Store;
use Elegant\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Response
{
    /**
     * The session store instance.
     *
     * @var \Elegant\Session\Store $session
     */
    protected Store $session;

    /**
     * The URL generator instance.
     *
     * @var \CI_Output
     */
    protected CI_Output $output;

    public function __construct(CI_Output $output, Store $session)
    {
        $this->output = $output;
        $this->session = $session;
    }

    /**
     * Create a new JSON response instance.
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @param int $options
     * @return \Elegant\Http\JsonResponse
     */
    public function json($data = [], int $status = 200, array $headers = [], int $options = 0): JsonResponse
    {
        return $this->createResponse($data, $status, $headers, $options);
    }

    /**
     * Create a new file download response.
     *
     * @param \SplFileInfo|string $file
     * @param string|null $name
     * @param array $headers
     * @param string|null $disposition
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($file, string $name = null, array $headers = [], ?string $disposition = 'attachment'): BinaryFileResponse
    {
        $response = new BinaryFileResponse($file, 200, $headers, true, $disposition);

        if (!is_null($name)) {
            return $response->setContentDisposition($disposition, $name, $this->fallbackName($name));
        }

        return $response;
    }

    /**
     * @param array $data
     * @param int $status
     * @param array $headers
     * @param int $options
     * @return \Elegant\Http\JsonResponse
     */
    protected function createResponse(array $data, int $status, array $headers, int $options): JsonResponse
    {
        return tap(new JsonResponse($data, $status, $headers, $options), function ($response) {
            $response->setSession(app('session'));

            $response->setResponse($this->getResponse());
        });
    }

    /**
     * Convert the string to ASCII characters that are equivalent to the given name.
     *
     * @param string $name
     * @return string
     */
    protected function fallbackName(string $name): string
    {
        return str_replace('%', '', Str::ascii($name));
    }

    /**
     * Get the request instance.
     *
     * @return \CI_Output $output
     */
    public function getResponse(): CI_Output
    {
        return $this->output;
    }
}
