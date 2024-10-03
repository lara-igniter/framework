<?php

namespace Elegant\Http;

use CI_Output;
use Elegant\Contracts\Support\Arrayable;
use Elegant\Contracts\Support\Jsonable;
use Elegant\Support\Str;
use Elegant\Support\Traits\ForwardsCalls;
use Elegant\Support\Traits\Macroable;
use InvalidArgumentException;
use JsonSerializable;
use MY_Session;
use Symfony\Component\HttpFoundation\JsonResponse as BaseJsonResponse;

class JsonResponse extends BaseJsonResponse
{
    use ForwardsCalls, Macroable {
        Macroable::__call as macroCall;
    }

    /**
     * The session store instance.
     *
     * @var \MY_Session
     */
    protected MY_Session $session;

    /**
     * The request instance.
     *
     * @var \CI_Output $response
     */
    protected CI_Output $response;

    /**
     * Constructor.
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @param int $options
     * @param bool $json
     * @return void
     */
    public function __construct($data = null, int $status = 200, array $headers = [], int $options = 0, bool $json = false)
    {
        $this->encodingOptions = $options;

        parent::__construct($data, $status, $headers, $json);
    }

    /**
     * Flash a piece of data to the session.
     *
     * @param string|array $key
     * @param mixed $value
     * @return $this
     */
    public function with($key, $value = null): self
    {
        $key = is_array($key) ? $key : [$key => $value];

        foreach ($key as $k => $v) {
//            $this->session->set_flashdata($k, $v);
            $this->session->set_userdata($k, $v);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromJsonString(?string $data = null, int $status = 200, array $headers = [])
    {
        return new static($data, $status, $headers, 0, true);
    }

    /**
     * Sets the JSONP callback.
     *
     * @param string|null $callback
     * @return $this
     */
    public function withCallback(string $callback = null): JsonResponse
    {
        return $this->setCallback($callback);
    }

    /**
     * Get the json_decoded data from the response.
     *
     * @param bool $assoc
     * @param int $depth
     * @return mixed
     */
    public function getData(bool $assoc = false, int $depth = 512)
    {
        return json_decode($this->data, $assoc, $depth);
    }

    /**
     * {@inheritdoc}
     */
    public function setData($data = [])
    {
        $this->original = $data;

        if ($data instanceof Jsonable) {
            $this->data = $data->toJson($this->encodingOptions);
        } elseif ($data instanceof JsonSerializable) {
            $this->data = json_encode($data->jsonSerialize(), $this->encodingOptions);
        } elseif ($data instanceof Arrayable) {
            $this->data = json_encode($data->toArray(), $this->encodingOptions);
        } else {
            $this->data = json_encode($data, $this->encodingOptions);
        }

        if (!$this->hasValidJson(json_last_error())) {
            throw new InvalidArgumentException(json_last_error_msg());
        }

        return $this->update();
    }

    /**
     * Determine if an error occurred during JSON encoding.
     *
     * @param int $jsonError
     * @return bool
     */
    protected function hasValidJson(int $jsonError): bool
    {
        if ($jsonError === JSON_ERROR_NONE) {
            return true;
        }

        return $this->hasEncodingOption(JSON_PARTIAL_OUTPUT_ON_ERROR) &&
            in_array($jsonError, [
                JSON_ERROR_RECURSION,
                JSON_ERROR_INF_OR_NAN,
                JSON_ERROR_UNSUPPORTED_TYPE,
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function setEncodingOptions($options)
    {
        $this->encodingOptions = (int)$options;

        return $this->setData($this->getData());
    }

    /**
     * Determine if a JSON encoding option is set.
     *
     * @param int $option
     * @return bool
     */
    public function hasEncodingOption(int $option): bool
    {
        return (bool)($this->encodingOptions & $option);
    }

    /**
     * Sends HTTP headers and content.
     *
     * @return \Elegant\Http\JsonResponse
     */
    public function send(): JsonResponse
    {
        $this->response->set_status_header($this->getStatusCode());
        $this->response->set_content_type('application/json');
        $this->response->set_output($this->getContent());

        return $this;
    }

    /**
     * Get the response instance.
     *
     * @return \CI_Output|null
     */
    public function getResponse(): ?CI_Output
    {
        return $this->response;
    }

    /**
     * Set the response instance.
     *
     * @param \CI_Output $response
     * @return void
     */
    public function setResponse(CI_Output $response): void
    {
        $this->response = $response;
    }

    /**
     * Get the session store instance.
     *
     * @return \MY_Session|null
     */
    public function getSession(): ?MY_Session
    {
        return $this->session;
    }

    /**
     * Set the session store instance.
     *
     * @param \MY_Session $session
     * @return void
     */
    public function setSession(MY_Session $session): void
    {
        $this->session = $session;
    }

    /**
     * Dynamically bind flash data in the session.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (Str::startsWith($method, 'with')) {
            return $this->with(Str::snake(substr($method, 4)), $parameters[0]);
        }

        static::throwBadMethodCallException($method);
    }
}
