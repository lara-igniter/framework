<?php

namespace Elegant\Foundation\Testing\Concerns;

trait InteractsWithAuthentication
{
    /**
     * Set the currently logged in user for the application.
     *
     * @param mixed $user
     * @param string|null $guard
     * @return $this
     */
    public function actingAs($user, $guard = null)
    {
        $this->actingAsUser = $user;

        return $this;
    }

    /**
     * Alias of actingAs.
     *
     * @param mixed $user
     * @param string|null $guard
     * @return $this
     */
    public function be($user, $guard = null)
    {
        return $this->actingAs($user, $guard);
    }

    /**
     * @return $this
     */
    public function actingAsGuest()
    {
        $this->actingAsUser = null;

        return $this;
    }
}
