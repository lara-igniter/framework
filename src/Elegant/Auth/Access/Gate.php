<?php

namespace Elegant\Auth\Access;

use Exception;
use Elegant\Contracts\Auth\Access\Gate as GateContract;
use Elegant\Support\Arr;
use Elegant\Support\Str;

class Gate implements GateContract
{
    use HandlesAuthorization;

    /**
     * @var object|null $userResolver
     */
    protected ?object $userResolver;

    /**
     * @param object|null $userResolver
     */
    public function __construct(?object $userResolver)
    {
        $this->userResolver = $userResolver;
    }

    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @param string $ability
     * @param array|mixed $arguments
     * @return bool
     *
     * @throws \Exception
     */
    public function allows($ability, $arguments = [])
    {
        return $this->check($ability, $arguments);
    }

    /**
     * Determine if the given ability should be denied for the current user.
     *
     * @param string $ability
     * @param array|mixed $arguments
     * @return bool
     *
     * @throws \Exception
     */
    public function denies($ability, $arguments = [])
    {
        return !$this->allows($ability, $arguments);
    }

    /**
     * Determine if all of the given abilities should be granted for the current user.
     *
     * @param iterable|string $abilities
     * @param array|mixed $arguments
     * @return bool
     *
     * @throws \Exception
     */
    public function check($abilities, $arguments = [])
    {
        return collect($abilities)->every(function ($ability) use ($arguments) {
            return (bool)$this->raw($ability, $arguments);
        });
    }

    /**
     * Determine if any one of the given abilities should be granted for the current user.
     *
     * @param iterable|string $abilities
     * @param array|mixed $arguments
     * @return bool
     *
     * @throws \Exception
     */
    public function any($abilities, $arguments = [])
    {
        return collect($abilities)->contains(function ($ability) use ($arguments) {
            return $this->check($ability, $arguments);
        });
    }

    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @param string $ability
     * @param array|mixed $arguments
     * @return bool|void
     *
     * @throws \Exception
     */
    public function authorize($ability, $arguments = [])
    {
        $result = $this->raw($ability, $arguments);

        return $result ? $this->allow() : $this->deny();
    }

    /**
     * Get the raw result from the authorization callback.
     *
     * @param string $ability
     * @param array|mixed $arguments
     * @return mixed
     *
     * @throws \Exception
     */
    public function raw($ability, $arguments = [])
    {
        $arguments = Arr::wrap($arguments);

        // if (class_exists(is_array($arguments) ? $arguments[0] : $arguments)) {
        $className = 'App\\Policies\\' . Str::studly(Str::afterLast($arguments[0], '\\')) . 'Policy';

        if (class_exists($className)) {
            $policy = new $className();
        } else {
            throw new Exception('Policy with name ' . $className . ' does not exist!');
        }

        $arguments[0] = $this->resolveUser();

        return method_exists($policy, $ability)
            ? call_user_func_array([$policy, $ability], $arguments)
            : false;
        // } else {
        //     throw new Exception('Model with name ' . $arguments . ' does not exist!');
        // }
    }

    /**
     * Resolve the user from auth session.
     *
     * @return object|null
     */
    protected function resolveUser()
    {
        return $this->userResolver;
    }
}