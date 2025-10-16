<?php

namespace Elegant\Console\Concerns;

trait InteractsWithIO
{
    /**
     * Get the value of an argument.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function argument(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->arguments;
        }

        return $this->arguments[$key] ?? $default;
    }

    /**
     * Get the value of an option.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function option(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->options;
        }

        return $this->options[$key] ?? $default;
    }
}
