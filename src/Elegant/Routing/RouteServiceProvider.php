<?php

namespace Elegant\Routing;

use Elegant\Foundation\Hooks\Contracts\DisplayOverrideHookInterface;
use Elegant\Foundation\Hooks\Contracts\PostControllerConstructorHookInterface;
use Elegant\Foundation\Hooks\Contracts\PostControllerHookInterface;
use Elegant\Foundation\Hooks\Contracts\PreControllerHookInterface;
use Elegant\Foundation\Hooks\Contracts\PreSystemHookInterface;
use Elegant\Routing\Exceptions\RouteNotFoundException;
use Elegant\Routing\Middleware\Middleware;
use Elegant\Routing\Middleware\RouteAjaxMiddleware;
use Elegant\Routing\RouteBuilder as Route;
use Elegant\Support\Utils;

class RouteServiceProvider implements PreSystemHookInterface,
    PreControllerHookInterface,
    PostControllerConstructorHookInterface,
    PostControllerHookInterface,
    DisplayOverrideHookInterface
{
    public function preSystemHook()
    {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $isCli = is_cli();
        $isWeb = !is_cli();

        require_once realpath(dirname(__DIR__) . './Support/Facades/Route.php');

        if (!file_exists(APPPATH . '/routes')) {
            mkdir(APPPATH . '/routes');
        }

        if (!file_exists(APPPATH . '/middleware')) {
            mkdir(APPPATH . '/middleware');
        }

        if ($isWeb) {
            require_once(APPPATH . '/routes/web.php');
        }

        if ($isAjax || $isWeb) {
            Route::group('/', ['middleware' => [new RouteAjaxMiddleware()]],
                function () {
                    require_once(APPPATH . '/routes/api.php');
                }
            );
        }

        if ($isCli) {
            require_once(APPPATH . '/routes/console.php');
            Route::set('default_controller', Route::DEFAULT_CONTROLLER);
        }

        require_once(realpath(dirname(__DIR__) . './Foundation/helpers.php'));

        // Compiling all routes
        Route::compileAll();

        // HTTP verb tweak
        //
        // (This allows us to use any HTTP Verb if the form contains a hidden field
        // named "_method")
        if (isset($_SERVER['REQUEST_METHOD'])) {
            if (strtolower($_SERVER['REQUEST_METHOD']) == 'post' && isset($_POST['_method'])) {
                $_SERVER['REQUEST_METHOD'] = $_POST['_method'];
            }

            $requestMethod = $_SERVER['REQUEST_METHOD'];
        } else {
            $requestMethod = 'CLI';
        }

        // Getting the current url
        $url = Utils::currentUrl();

        try {
            $currentRoute = Route::getByUrl($url);
        } catch (RouteNotFoundException $e) {
            Route::$compiled['routes'][$url] = Route::DEFAULT_CONTROLLER . '/index';
            $currentRoute = Route::{!is_cli() ? 'any' : 'cli'}($url, function () {
                if (!is_cli() && is_callable(Route::get404())) {
                    $_404 = Route::get404();
                    call_user_func($_404);
                } else {
                    show_404();
                }
            });
            $currentRoute->is404 = true;
            $currentRoute->isCli = is_cli();
        };

        $currentRoute->requestMethod = $requestMethod;

        Route::setCurrentRoute($currentRoute);
    }

    public function preControllerHook(&$params, &$URI, &$class, &$method)
    {
        $route = Route::getCurrentRoute();

        // Is a 404 route? stop this hook
        if ($route->is404) {
            return;
        }

        $path = $route->getFullPath();

        $pCount = 0;

        // Removing controller's sub-directory limitation over "/" path
        if ($path == '/') {
            if (!empty($route->getNamespace()) || is_string($route->getAction())) {
                $dir = $route->getNamespace();
                list($_class, $_method) = explode('@', $route->getAction());

                $_controller = APPPATH . 'controllers/' . (!empty($dir) ? $dir . '/' : '') . $_class . '.php';

                if (file_exists($_controller)) {
                    require_once $_controller;
                    list($class, $method) = explode('@', $route->getAction());
                } else {
                    $route->setAction(function () {
                        if (!is_cli() && is_callable(Route::get404())) {
                            $_404 = Route::get404();
                            call_user_func($_404);
                        } else {
                            show_404();
                        }
                    });
                }
            }
        }

        if (!$route->isCli) {
            $params_result = [];

            $sCount = 0;

            foreach (explode('/', $path) as $currentSegmentIndex => $segment) {
                $key = [];

                if (preg_match('/\{(.*?)\}+/', $segment)) {
                    foreach ($route->params as $param) {
                        if (empty($key[$param->getSegmentIndex()])) {
                            $key[$param->getSegmentIndex()] = str_replace($param->getSegment(), '(' . $param->getRegex() . ')', $param->getFullSegment());
                        } else {
                            $key[$param->getSegmentIndex()] = str_replace($param->getSegment(), '(' . $param->getRegex() . ')', $key[$param->getSegmentIndex()]);
                        }
                    }

                    foreach ($route->params as $param) {
                        if ($param->segmentIndex === $currentSegmentIndex) {
                            $segment = preg_replace('/\((.*)\):/', '', $segment);

                            if (preg_match('#^' . $key[$currentSegmentIndex] . '$#', $URI->segment($currentSegmentIndex + 1), $matches)) {
                                if (isset($matches[$pCount + 1 - $sCount])) {
                                    $route->params[$pCount]->value = $matches[$pCount + 1 - $sCount];
                                }
                            }

                            if (is_callable($route->getAction()) && !empty($URI->segment($currentSegmentIndex + 1))) {
                                $params[$route->params[$pCount]->getName()] = $URI->segment($currentSegmentIndex + 1);
                            }

                            // Removing "sticky" route parameters
                            if (substr($param->getName(), 0, 1) !== '_') {
                                $params_result[] = $route->params[$pCount]->value;
                            }

                            $pCount++;
                        }
                    }
                    $sCount++;
                }
            }

            $params = $params_result;
        } else {
            if (!empty($route->params)) {
                $argv = array_slice($_SERVER['argv'], 1);

                if ($argv) {
                    $params = array_slice($argv, $route->paramOffset);
                }

                foreach ($route->params as $i => &$param) {
                    $param->value = $params[$i] ?? NULL;
                }
            }
        }

        Route::setCurrentRoute($route);

        // If the current route is an anonymous route, we must prevent
        // the execution of their 'traditional' counterpart (if exists)
        if (is_callable($route->getAction())) {
            $RTR = &load_class('Router', 'core');

            $class = Route::DEFAULT_CONTROLLER;

            if (!class_exists($class)) {
                require_once APPPATH . '/controllers/' . Route::DEFAULT_CONTROLLER . '.php';
            }

            $method = 'index';
        }
    }

    public function postControllerConstructorHook(&$params)
    {
        // Current route configuration and dispatch
        app()->route = Route::getCurrentRoute();

        if (!app()->route->is404) {
            app()->load->helper('url');

            app()->middleware = new Middleware();

            if (method_exists(app(), 'preMiddleware')) {
                call_user_func([app(), 'preMiddleware']);
            }

            foreach (Route::getGlobalMiddleware()['pre_controller'] as $middleware) {
                app()->middleware->run($middleware);
            }

            // Setting "sticky" route parameters values as default for current route
            foreach (app()->route->params as &$param) {
                if (substr($param->getName(), 0, 1) == '_') {
                    Route::setDefaultParam($param->getName(), app()->route->param($param->getName()));
                }
            }

            foreach (app()->route->getMiddleware() as $middleware) {
                if (is_string($middleware)) {
                    $middleware = [$middleware];
                }

                foreach ($middleware as $_middleware) {
                    app()->middleware->run($_middleware);
                }
            }
        }

        if (is_callable(app()->route->getAction())) {
            call_user_func_array(app()->route->getAction(), $params);
        }
    }

    public function postControllerHook()
    {
        if (app()->route->is404) {
            return;
        }

        foreach (Route::getGlobalMiddleware()['post_controller'] as $middleware) {
            app()->middleware->run($middleware);
        }
    }

    public function displayOverrideHook()
    {
        $output = app()->output->get_output();

        if (isset(app()->db)) {
            $queries = app()->db->queries;
        }

        app()->output->_display($output);
    }
}
