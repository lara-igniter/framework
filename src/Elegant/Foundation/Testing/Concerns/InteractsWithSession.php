<?php

namespace Elegant\Foundation\Testing\Concerns;

trait InteractsWithSession
{
    /**
     * Set the session to the given array.
     *
     * @param array $data
     * @return $this
     */
    public function withSession(array $data)
    {
        return $this->session($data);
    }

    /**
     * Set the session to the given array.
     *
     * @param array $data
     * @return $this
     */
    public function session(array $data)
    {
        $this->sessionData = array_merge($this->sessionData, $data);

        return $this;
    }

    /**
     * Flush all of the current session data.
     *
     * @return $this
     */
    public function flushSession()
    {
        $this->sessionData = [];

        return $this;
    }
}
