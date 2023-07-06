<?php

namespace Elegant\Contracts\Auth\Access;

interface Gate
{
    /**
     *  Determine if the given ability should be granted for the current user.
     *
     * @param string $ability
     * @param array|mixed $arguments
     * @return bool
     *
     * @throws \Exception
     */
    public function allows($ability, $arguments = []);

    /**
     * Determine if the given ability should be denied for the current user.
     *
     * @param string $ability
     * @param array|mixed $arguments
     * @return bool
     *
     * @throws \Exception
     */
    public function denies($ability, $arguments = []);

    /**
     * Determine if all of the given abilities should be granted for the current user.
     *
     * @param iterable|string $abilities
     * @param array|mixed $arguments
     * @return bool
     *
     * @throws \Exception
     */
    public function check($abilities, $arguments = []);

    /**
     * Determine if any one of the given abilities should be granted for the current user.
     *
     * @param iterable|string $abilities
     * @param array|mixed $arguments
     * @return bool
     */
    public function any($abilities, $arguments = []);

    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @param string $ability
     * @param array|mixed $arguments
     * @return bool|void
     *
     * @throws \Exception
     */
    public function authorize($ability, $arguments = []);

    /**
     * Get the raw result from the authorization callback.
     *
     * @param string $ability
     * @param array|mixed $arguments
     * @return mixed
     *
     * @throws \Exception
     */
    public function raw($ability, $arguments = []);
}