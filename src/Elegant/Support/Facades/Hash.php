<?php

namespace Elegant\Support\Facades;

/**
 * @method static array info(string $hashedValue)
 * @method static bool check(string $value, string $hashedValue, array $options = [])
 * @method static bool needsRehash(string $hashedValue, array $options = [])
 * @method static string make(string $value, array $options = [])
 * @method static \Elegant\Contracts\Hashing\Hasher driver(string $driver = null)
 * @method static \Elegant\Hashing\HashManager extend(string $driver, \Closure $callback)
 *
 * @see \Elegant\Hashing\HashManager
 */
class Hash extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'hash';
    }
}
