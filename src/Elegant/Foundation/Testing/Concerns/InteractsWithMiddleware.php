<?php

namespace Elegant\Foundation\Testing\Concerns;

trait InteractsWithMiddleware
{
    /**
     * Disable middleware for the test.
     *
     * @param string|array|null $middleware
     * @return $this
     */
    public function withoutMiddleware($middleware = null)
    {
        if ($middleware === null) {
            $this->withoutMiddlewareAll = true;
            $this->withoutMiddleware = [];

            return $this;
        }

        $this->withoutMiddlewareAll = false;
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->withoutMiddleware = array_values(array_unique(array_merge($this->withoutMiddleware, $middleware)));

        return $this;
    }

    /**
     * @return $this
     */
    public function withMiddleware()
    {
        $this->withoutMiddlewareAll = false;
        $this->withoutMiddleware = [];

        return $this;
    }
}
