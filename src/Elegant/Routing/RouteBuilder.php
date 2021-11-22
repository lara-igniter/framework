<?php

namespace Elegant\Routing;

use Elegant\Routing\Exceptions\RouteNotFoundException;
use Elegant\Support\Str;
use Exception;

class RouteBuilder
{
    const DEFAULT_CONTROLLER = 'Controller';

    /**
     * All of the verbs supported by the router.
     *
     * @var string[]
     */
    public static $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

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
            show_error('You only can define CLI routes in CLI context. Please define this route using the Route::cli() method in your routes/cli.php file instead');
        }

        if ($callback == 'match') {
            $methods = $args[0];
        } else {
            $methods = $callback;
        }

        if (!in_array(strtoupper($callback), self::$verbs, true) && !in_array($callback, ['any', 'match', true])) {
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
     * @param callable|array $attributes
     * @param callable|null $routes
     *
     * @return void
     */
    public static function group(string $prefix, $attributes, callable $routes = null)
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
                $attributes['middleware'] = [$attributes['middleware']];
            } else {
                if (!is_array($attributes['middleware'])) {
                    show_error('Route group middleware must be an array o a string');
                }
            }
            self::$context['middleware']['route'][] = $attributes['middleware'];
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
    public static function middleware($middleware, string $point = 'pre_controller')
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

        $routes['default_controller'] = self::$compiled['reserved']['default_controller'] ?? null;

        $routes['translate_uri_dashes'] = self::$compiled['reserved']['translate_uri_dashes'] ?? FALSE;

        $routes['404_override'] = self::$compiled['reserved']['404_override'] ?? '';

        self::$compiled['routes'] = $routes;
    }

    /**
     * Creates a new resource route
     *
     * @param string $name Resource name
     * @param string $controller Resource controller
     * @param array $only Resource action filtering
     *
     * @return void
     */
    public static function resource(string $name, string $controller, array $only = [])
    {
        $routes = [
            'index' => ['/', ['GET']],
            'create' => ['/create', ['GET']],
            'store' => ['/', ['POST']],
            'show' => ['/{id}', ['GET']],
            'edit' => ['/{id}/edit', ['GET']],
            'update' => ['/{id}', ['PUT', 'PATCH']],
            'destroy' => ['/{id}', ['DELETE']]
        ];

        if (!is_array($only)) {
            $only = [];
        }

        foreach ($routes as $action => $props) {
            if (!empty($only) && !in_array($action, $only)) {
                continue;
            }

            list($path, $methods) = $props;

            self::match($methods, $name . $path, $controller . '@' . $action)->name($name . '.' . $action);
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
     * @throws Exception
     */
    public static function set(string $name, string $value)
    {
        if (!in_array($name, ['404_override', 'default_controller', 'translate_uri_dashes'])) {
            throw new Exception('Unknown reserved route "' . $name . '"');
        }

        if ($name == '404_override' && is_callable($value)) {
            self::$_404 = $value;
            $value = '';
        }

        self::$compiled['reserved'][$name] = $value;
    }

    /**
     * Sets the SimpleAuth default routing
     *
     * @param boolean $secureLogout Disable logout with GET requests
     *
     * @return void
     */
//    public static function auth($secureLogout = true)
//    {
//        self::match(['get', 'post'], 'login', 'SimpleAuthController@login')->name('login');
//
//        self::match($secureLogout === true ? ['post'] : ['get', 'post'], 'logout', 'SimpleAuthController@logout')->name('logout');
//
//        self::get('email_verification/{token}', 'SimpleAuthController@emailVerification')->name('email_verification');
//
//        self::match(['get', 'post'], 'signup', 'SimpleAuthController@signup')->name('signup');
//
//        self::match(['get', 'post'], 'confirm_password', 'SimpleAuthController@confirmPassword')->name('confirm_password');
//
//        self::group('password-reset', function () {
//            self::match(['get', 'post'], '/', 'SimpleAuthController@passwordReset')->name('password_reset');
//            self::match(['get', 'post'], '{token}', 'SimpleAuthController@passwordResetForm')->name('password_reset_form');
//        });
//    }

    /**
     * Gets the matching route of the provided URL
     *
     * @param string $url
     * @param string|null $requestMethod
     *
     * @return Route
     * @throws RouteNotFoundException
     *
     */
    public static function getByUrl(string $url, string $requestMethod = null)
    {
        if (empty($requestMethod)) {
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

        throw new RouteNotFoundException;
    }

    /**
     * Gets a route by its name
     *
     * @param string $name Route name to search
     *
     * @return Route
     * @throws RouteNotFoundException
     *
     */
    public static function getByName(string $name)
    {
        if (isset(self::$compiled['names'][$name])) {
            return self::$compiled['names'][$name];
        }

        throw new RouteNotFoundException;
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
    public static function getCurrentRoute(): ?Route
    {
        return self::$current;
    }

    /**
     * Determine whether the route's name matches the given patterns.
     *
     * @param mixed ...$patterns
     * @return bool
     */
    public static function named(...$patterns): bool
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
     * Gets the global middleware
     *
     * @return string
     */
    public static function getGlobalMiddleware(): string
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

        return self::$compiled['reserved']['404_override'] ?? null;
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
    public static function getContext(string $context)
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
    public static function setDefaultParam(string $name, string $value)
    {
        self::$context['params'][$name] = $value;
    }

    /**
     * Gets all (global) default sticky parameters values
     * @return string
     */
    public static function getDefaultParams(): string
    {
        return self::$context['params'];
    }
}
