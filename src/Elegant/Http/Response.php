<?php

namespace Elegant\Http;

use CI_Output;
use MY_Session;

class Response
{
    /**
     * The session store instance.
     *
     * @var \MY_Session $session
     */
    protected MY_Session $session;

    /**
     * The URL generator instance.
     *
     * @var \CI_Output
     */
    protected CI_Output $output;

    public function __construct(CI_Output $output, MY_Session $session)
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
     * Get the request instance.
     *
     * @return \CI_Output $output
     */
    public function getResponse(): CI_Output
    {
        return $this->output;
    }
}
