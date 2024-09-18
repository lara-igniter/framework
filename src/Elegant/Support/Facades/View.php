<?php

namespace Elegant\Support\Facades;

/**
 * @method static \Elegant\Contracts\View\Factory addNamespace(string $namespace, string|array $hints)
 * @method static \Elegant\Contracts\View\View first(array $views, \Elegant\Contracts\Support\Arrayable|array $data = [], array $mergeData = [])
 * @method static \Elegant\Contracts\View\Factory replaceNamespace(string $namespace, string|array $hints)
 * @method static \Elegant\Contracts\View\View file(string $path, array $data = [], array $mergeData = [])
 * @method static \Elegant\Contracts\View\View make(string $view, array $data = [], array $mergeData = [])
 * @method static bool exists(string $view)
 * @method static mixed share(array|string $key, $value = null)
 *
 * @see \Elegant\View\Factory
 */
class View extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'view';
    }
}
