<?php

namespace Elegant\Routing;

use Elegant\Routing\Exceptions\RouteNotFoundException;
use Elegant\Support\Str;

class RouteBuilder
{
    const DEFAULT_CONTROLLER = 'Controller';

    /**
     * All of the verbs supported by the router.
     *
     * @var string[]
     */
    const HTTP_VERBS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE',  'OPTIONS'];

    /**
     * @var Route[]
     */
    private static $routes = [];

    /**
     * @var string[]
     */
    private static $context = [
        'middleware' =>
            [
                'route' => [],
                'global' =>
                    [
                        'pre_controller' => [],
                        'controller' => [],
                        'post_controller' => [],
                    ],
            ],
        'namespace' => [],
        'prefix' => [],
        'params' => [],
    ];

    /**
     * @var string[]
     */
    public static $compiled = [
        'routes' => [],
        'paths' => [],
        'names' => [],
        'reserved' => [],
    ];

    /**
     * @var Route
     */
    private static $current;

    /**
     * @var string|callable
     */
    private static $_404;

    public static function __callStatic($callback, array $args)
    {
        if (is_cli() && $callback != 'cli' || !is_cli() && $callback == 'cli' || (!is_cli() && is_array($callback) && in_array('CLI', $callback))) {
            show_error('You only can define CLI routes in CLI context. Please define this route using the Route::cli() method in your routes/console.php file instead');
        }

        $methods = $callback === 'match' ? $args[0] : $callback;

        if (!in_array(strtoupper($callback), self::HTTP_VERBS, true) && !in_array($callback, ['any', 'match', true])) {
            show_error("Call to undefined RouteBuilder::{$callback()} method", 500, 'Route builder error');
        }

        $route = new Route($methods, $args);

        self::$routes[] = $route;

        return $route;
    }

    /**
     * Creates a new route group
     *
     * @param string $prefix
     * @param \Closure|array $attributes
     * @param \Closure|null $routes
     *
     * @return void
     */
    public static function group($prefix, $attributes, $routes = null)
    {
        if ($routes === null && is_callable($attributes)) {
            $routes = $attributes;
            $attributes = [];
        }

        self::$context['prefix'][] = $prefix;

        if (isset($attributes['namespace'])) {
            self::$context['namespace'][] = $attributes['namespace'];
        }

        if (isset($attributes['middleware'])) {
            if (is_string($attributes['middleware'])) {
                if (isset(\App\Kernel::$routeMiddleware[$attributes['middleware']])) {
                    $attributes['middleware'] = [$attributes['middleware']];
                } else {
                    show_error('Route group middleware {' . $attributes['middleware'] . '} must be register at application\Kernel.php');
                }
            } else {
                if (!is_array($attributes['middleware']) && !is_object($attributes['middleware'])) {
                    show_error('Route group middleware must be an array or a string or a new instance');
                }
            }

            if (!is_object($attributes['middleware'])) {
                $existMiddlewares = [];

                foreach ($attributes['middleware'] as $middleware) {
                    if (is_string($middleware)) {
                        if (isset(\App\Kernel::$routeMiddleware[$middleware])) {
                            $existMiddlewares[] = new \App\Kernel::$routeMiddleware[$middleware];
                        }
                    } elseif (is_object($middleware)) {
                        $existMiddlewares[] = $middleware;
                    } else {
                        show_error('Route group middleware must be an array or a string or a new instance');
                    }
                }

                self::$context['middleware']['route'][] = $existMiddlewares;
            } else {
                self::$context['middleware']['route'][] = $attributes['middleware'];
            }
        }

        call_user_func($routes);

        array_pop(self::$context['prefix']);

        if (isset($attributes['namespace'])) {
            array_pop(self::$context['namespace']);
        }

        if (isset($attributes['middleware'])) {
            array_pop(self::$context['middleware']['route']);
        }
    }

    /**
     * Creates a new middleware
     *
     * @param mixed $middleware Middleware callable
     * @param string $point Middleware execution point
     *
     * @return void
     */
    public static function middleware($middleware, $point = 'pre_controller')
    {
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        foreach ($middleware as $_middleware) {
            if (!in_array($_middleware, self::$context['middleware']['global'][$point])) {
                self::$context['middleware']['global'][$point][] = $_middleware;
            }
        }
    }

    /**
     * Compiles all routes
     *
     * @return void
     * @throws \Exception
     */
    public static function compileAll()
    {
        $routes = [];

        foreach (self::$routes as $route) {
            $routeName = $route->getName();

            if ($routeName !== null) {
                if (!isset(self::$compiled['names'][$routeName])) {
                    self::$compiled['names'][$routeName] = clone $route;
                } else {
                    show_error('Duplicated "<strong>' . $routeName . '</strong>" named route');
                }
            }

            foreach ($route->compile() as $compiled) {
                foreach ($compiled as $path => $action) {
                    foreach ($action as $method => $target) {
                        $routes[$path][$method] = $target;

                        $routePlaceholders = RouteParameter::getPlaceholderReplacements();
                        $regexPath = implode('\\/', explode('/', $path));
                        $regexPath = preg_replace(array_keys($routePlaceholders), array_values($routePlaceholders), $regexPath);
                        self::$compiled['paths']['#^' . $regexPath . '$#'][] = clone $route;
                    }
                }
            }
        }

        $routes['default_controller'] = isset(self::$compiled['reserved']['default_controller']) ?
            self::$compiled['reserved']['default_controller'] : null;

        $routes['translate_uri_dashes'] = isset(self::$compiled['reserved']['translate_uri_dashes']) ?
            self::$compiled['reserved']['translate_uri_dashes'] : FALSE;

        $routes['404_override'] = isset(self::$compiled['reserved']['404_override']) ?
            self::$compiled['reserved']['404_override'] : '';

        self::$compiled['routes'] = $routes;
    }

    /**
     * Register an array of resource controllers.
     *
     * @param array $resources
     * @param array $options
     * @return void
     */
    public static function resources(array $resources, array $options = [])
    {
        foreach ($resources as $name => $controller) {
            self::resource($name, $controller, $options);
        }
    }

    /**
     * Route a resource to a controller.
     *
     * @param string $name
     * @param string|array $controller
     * @param array $options
     *
     * @return void
     */
    public static function resource(string $name, $controller, array $options = [])
    {
        $only = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'restore', 'limit'];

        $routes = [
            'index' => [
                '/', ['GET']
            ],
            'create' => [
                '/create', ['GET']
            ],
            'store' => [
                '/', ['POST']
            ],
            'show' => [
                '/{num:' . Str::singular($name) . '_id}', ['GET']
            ],
            'edit' => [
                '/{num:' . Str::singular($name) . '_id}/edit', ['GET']
            ],
            'update' => [
                '/{num:' . Str::singular($name) . '_id}', ['POST', 'PUT', 'PATCH']
            ],
            'destroy' => [
                '/{num:' . Str::singular($name) . '_id}', ['DELETE']
            ],
            'restore' => [
                '/{num:' . Str::singular($name) . '_id}/restore', ['GET']
            ],
            'limit' => [
                '/limit', ['POST']
            ],
        ];

        if (!is_array($options)) {
            $options = [];
        }

        if (isset($options['except'])) {
            $only = array_diff($only, (array)$options['except']);
        }

        foreach ($routes as $action => $props) {
            if (!empty($options) && !empty($options['only']) && !in_array($action, $options['only'])) {
                continue;
            }

            if (!in_array($action, $only)) {
                continue;
            }

            if (!isset($options['pagination']) || !$options['pagination']) {
                unset($routes['limit']);
            }

            [$path, $methods] = $props;

            if (is_array($controller)) {
                $nameArray = explode('\\', $controller[0]);
                $controllerName = array_pop($nameArray);

                $controller = $controllerName;
            }

            self::match($methods, $name . $path, $controller . '@' . $action)->name(
                !empty($options['as']) ? $options['as'] . $name . '.' . $action : $name . '.' . $action
            );
        }
    }


    /**
     * Register an array of API resource controllers.
     *
     * @param array $resources
     * @param array $options
     * @return void
     */
    public static function apiResources(array $resources, array $options = [])
    {
        foreach ($resources as $name => $controller) {
            self::apiResource($name, $controller, $options);
        }
    }

    /**
     * Route an API resource to a controller.
     *
     * @param string $name
     * @param string|array $controller
     * @param array $options
     *
     * @return void
     */
    public static function apiResource(string $name, $controller, array $options = [])
    {
        $only = ['index', 'show', 'store', 'update', 'destroy'];

        $routes = [
            'index' => [
                '/', ['GET']
            ],
            'show' => [
                '/{num:' . Str::singular($name) . '_id}', ['GET']
            ],
            'store' => [
                '/', ['POST']
            ],
            'update' => [
                '/{num:' . Str::singular($name) . '_id}', ['POST', 'PUT', 'PATCH']
            ],
            'destroy' => [
                '/{num:' . Str::singular($name) . '_id}', ['DELETE']
            ]
        ];

        if (!is_array($options)) {
            $options = [];
        }

        if (isset($options['except'])) {
            $only = array_diff($only, (array)$options['except']);
        }

        if (isset($options['only'])) {
            $only = $options['only'];
        }

        foreach ($routes as $action => $props) {
            if (!empty($options) && !empty($options['only']) && !in_array($action, $options['only'])) {
                continue;
            }

            if (!in_array($action, $only)) {
                continue;
            }

            [$path, $methods] = $props;

            if (is_array($controller)) {
                $nameArray = explode('\\', $controller[0]);
                $controllerName = array_pop($nameArray);

                $controller = $controllerName;
            }

            self::match($methods, $name . $path, $controller . '@' . $action)->name(
                !empty($options['as']) ? $options['as'] . $name . '.' . $action : $name . '.' . $action
            );
        }
    }

    /**
     * Sets a CodeIgniter special route
     *
     * @param string $name Route name
     * @param string $value Route value
     *
     * @return void
     *
     * @throws \Exception
     */
    public static function set($name, $value)
    {
        if (!in_array($name, ['404_override', 'default_controller', 'translate_uri_dashes'])) {
            throw new \Exception('Unknown reserved route "' . $name . '"');
        }

        if ($name == '404_override' && is_callable($value)) {
            self::$_404 = $value;
            $value = '';
        }

        self::$compiled['reserved'][$name] = $value;
    }

    /**
     * Gets the matching route of the provided URL
     *
     * @param string $url
     * @param string $requestMethod
     *
     * @return Route
     *
     * @throws RouteNotFoundException
     *
     */
    public static function getByUrl($url, $requestMethod = null)
    {
        if ($requestMethod === null || empty($requestMethod)) {
            $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : (!is_cli() ? 'GET' : 'CLI');
        } else {
            $requestMethod = strtoupper($requestMethod);
        }

        // First, look for a direct match:
        $urlRegex = '#^' . str_replace('/', '\\/', $url) . '$#';

        if (isset(self::$compiled['paths'][$urlRegex])) {
            foreach (self::$compiled['paths'][$urlRegex] as $route) {
                if (in_array($requestMethod, $route->getMethods())) {
                    return $route;
                }
            }
        }

        // Then, loop into the array of compiled path
        foreach (self::$compiled['paths'] as $path => $routes) {
            if (preg_match($path, $url)) {
                foreach ($routes as $route) {
                    if (in_array($requestMethod, $route->getMethods())) {
                        return $route;
                    }
                }
            }
        }

        throw new RouteNotFoundException("Route url [{$url}] does not exist.");
    }

    /**
     * Gets a route by its name
     *
     * @param string $name Route name to search
     *
     * @return Route
     *
     * @throws RouteNotFoundException
     *
     */
    public static function getByName($name)
    {
        if (isset(self::$compiled['names'][$name])) {
            return self::$compiled['names'][$name];
        }

        throw new RouteNotFoundException("Route [{$name}] not defined.");
    }

    /**
     * Gets all compiled routes
     *
     * @return string[]
     */
    public static function getRoutes()
    {
        return self::$compiled['routes'];
    }

    /**
     * Gets the current route
     *
     * @return Route|null
     */
    public static function getCurrentRoute()
    {
        return self::$current;
    }

    /**
     * Determine whether the route's name matches the given patterns.
     *
     * @param mixed ...$patterns
     * @return bool
     */
    public static function named(...$patterns)
    {
        if (is_null($routeName = self::getCurrentRoute()->getName())) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a route with the given name exists.
     *
     * @param string|array $name
     * @return bool
     */
    public static function has($name): bool
    {
        $names = is_array($name) ? $name : func_get_args();

        foreach ($names as $value) {
            if (!self::hasNamedRoute($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the route collection contains a given named route.
     *
     * @param string $name
     * @return bool
     */
    public static function hasNamedRoute(string $name): bool
    {
        try {
            return (bool)self::getByName($name);
        } catch (RouteNotFoundException $e) {
            return false;
        }
    }

    /**
     * Gets the global middleware
     *
     * @return array
     */
    public static function getGlobalMiddleware()
    {
        return self::$context['middleware']['global'];
    }

    /**
     * Gets the custom 404 controller/callback
     *
     * @return string|callable|NULL
     */
    public static function get404()
    {
        if (self::$_404 !== null) {
            return self:: $_404;
        }

        return isset(self::$compiled['reserved']['404_override']) ?
            self::$compiled['reserved']['404_override'] : null;
    }

    /**
     * Gets the static context of the route builder
     *
     * (This is used internally by Luthier CI)
     *
     * @param string $context Context index
     *
     * @return mixed
     */
    public static function getContext($context)
    {
        return self::$context[$context];
    }

    /**
     * Sets the current route
     *
     * @param Route $route
     *
     * @return void
     */
    public static function setCurrentRoute(Route $route)
    {
        self::$current = $route;
    }

    /**
     * Sets a (global) default value for a sticky parameter
     *
     * @param string $name Parameter name
     * @param string $value Parameter value
     *
     * @return void
     */
    public static function setDefaultParam($name, $value)
    {
        self::$context['params'][$name] = $value;
    }

    /**
     * Gets all (global) default sticky parameters values
     * @return string
     */
    public static function getDefaultParams()
    {
        return self::$context['params'];
    }
}
