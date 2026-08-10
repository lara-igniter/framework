<?php

namespace Elegant\Foundation\Http;

/**
 * Captured HTTP response for feature tests.
 */
class Response
{
    /**
     * @var int
     */
    public $status;

    /**
     * @var string
     */
    public $content;

    /**
     * @var array
     */
    public $headers;

    /**
     * @param string $content
     * @param int $status
     * @param array $headers
     */
    public function __construct($content = '', $status = 200, array $headers = [])
    {
        $this->content = (string) $content;
        $this->status = (int) $status;
        $this->headers = $headers;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return array
     */
    public function headers()
    {
        return $this->headers;
    }
}
