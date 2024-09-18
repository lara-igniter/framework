<?php

namespace Elegant\Support\Facades;

/**
 * @method static \Elegant\Routing\Route get(string|array $methods, array $route)
 * @method static \Elegant\Routing\Route post(string|array $methods, array $route)
 * @method static \Elegant\Routing\Route put(string|array $methods, array $route)
 * @method static \Elegant\Routing\Route patch(string|array $methods, array $route)
 * @method static \Elegant\Routing\Route delete(string|array $methods, array $route)
 * @method static \Elegant\Routing\Route options(string|array $methods, array $route)
 * @method static void group(string $prefix, callable|array $attributes, callable|null $routes = null)
 * @method static void middleware(mixed $middleware, string $point = 'pre_controller')
 * @method static void compileAll()
 * @method static void resources(array $resources, array $options = [])
 * @method static void resource(string $name, string|array $controller, array $options = [])
 * @method static void apiResources(array $resources, array $options = [])
 * @method static void apiResource(string $name, string|array $controller, array $options = [])
 * @method static void set(string $name, string $value)
 * @method static \Elegant\Routing\Route getByUrl(string $url, string $requestMethod = null)
 * @method static \Elegant\Routing\Route getByName(string $name)
 * @method static array getRoutes()
 * @method static \Elegant\Routing\Route|null getCurrentRoute()
 * @method static bool named(mixed $patterns)
 * @method static bool has(string|array $name)
 * @method static bool hasNamedRoute(string $name)
 * @method static array getGlobalMiddleware()
 * @method static string|callable|null get404()
 * @method static mixed getContext(string $context)
 * @method static \Elegant\Routing\Route setCurrentRoute(\Elegant\Routing\Route $route)
 * @method static void setDefaultParam(string $name, string $value)
 * @method static string getDefaultParams()
 *
 * @see \Elegant\Routing\RouteBuilder
 */
class Route extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'route';
    }
}
