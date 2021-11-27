<?php

namespace Elegant\Foundation\Hooks;

use Elegant\Routing\Exceptions\RouteNotFoundException;
use Elegant\Routing\Middleware\Middleware;
use Elegant\Routing\Middleware\RouteAjaxMiddleware;
use Elegant\Routing\RouteBuilder as Route;
use Elegant\Support\Utils;
use Exception;

class RouteHooks
{
    /**
     * Gets the Routing hooks
     *
     * @param string|null $config Routing CI configuration
     *
     * @return array
     */
    public static function load($config = null): array
    {
        if (empty($config)) {
            $config = [
                'modules' => [],
            ];
        }

        $hooks = [];

        $hooks['pre_system'][] = function () use ($config) {
            self::preSystemHook($config);
        };

        $hooks['pre_controller'][] = function () {
            global $params, $URI, $class, $method;

            self::preControllerHook($params, $URI, $class, $method);
        };

        $hooks['post_controller_constructor'][] = function () use ($config) {
            global $params;
            self::postControllerConstructorHook($config, $params);
        };

        $hooks['post_controller'][] = function () use ($config) {
            self::postControllerHook($config);
        };

        $hooks['display_override'][] = function () {
            self::displayOverrideHook();
        };

        return $hooks;
    }

    /**
     * "pre_system" hook
     *
     * @param array $config
     *
     * @return void
     * @throws Exception
     */
    private static function preSystemHook($config)
    {
//        define('LUTHIER_CI_VERSION', '1.0.5');
//        define('LUTHIER_CI_DIR', __DIR__);

        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $isCli = is_cli();
        $isWeb = !is_cli();


//echo realpath(dirname(__DIR__) . '../../Support/Facades/Route.php'); die();
        require_once realpath(dirname(__DIR__) . '../../Support/Facades/Route.php');
//        require_once __DIR__ . '/Facades/Route.php';

//        if (in_array('auth', $config['modules'])) {
//            require_once __DIR__ . '/Facades/Auth.php';
//        }

        if (!file_exists(APPPATH . '/routes')) {
            mkdir(APPPATH . '/routes');
        }

        if (!file_exists(APPPATH . '/middleware')) {
            mkdir(APPPATH . '/middleware');
        }

        if (!file_exists(APPPATH . '/routes/web.php')) {
            copy(realpath(dirname(__DIR__) . '../../Routing/Resources/WebRoutes.php'), APPPATH . '/routes/web.php');
        }

        if ($isWeb) {
            require_once(APPPATH . '/routes/web.php');
        }

        if (!file_exists(APPPATH . '/routes/api.php')) {
            copy(realpath(dirname(__DIR__) . '../../Routing/Resources/ApiRoutes.php'), APPPATH . '/routes/api.php');
        }

        if ($isAjax || $isWeb) {
            Route::group('/', ['middleware' => [new RouteAjaxMiddleware()]],
                function () {
                    require_once(APPPATH . '/routes/api.php');
                }
            );
        }

        if (!file_exists(APPPATH . '/routes/console.php')) {
            copy(realpath(dirname(__DIR__) . '../../Routing/Resources/ConsoleRoutes.php'), APPPATH . '/routes/console.php');
        }

        if ($isCli) {
            require_once(APPPATH . '/routes/console.php');
            Route::set('default_controller', Route::DEFAULT_CONTROLLER);
        }

        if (!file_exists(APPPATH . '/controllers/' . Route::DEFAULT_CONTROLLER . '.php')) {
            copy(realpath(dirname(__DIR__) . '../../Routing/Resources/Controller.php'), APPPATH . '/controllers/' . Route::DEFAULT_CONTROLLER . '.php');
        }

        require_once(realpath(dirname(__DIR__) . '../../Foundation/helpers.php'));
//        require_once(__DIR__ . '/helpers.php');

        // Auth module
//        if (in_array('auth', $config['modules'])) {
//            Route::middleware(new AuthDispatcher());
//        }

        // Debug module
//        if (ENVIRONMENT != 'production' && !$isCli && !$isAjax && in_array('debug', $config['modules'])) {
//            Debug::init();
//            Debug::addCollector(new MessagesCollector('auth'));
//            Debug::addCollector(new MessagesCollector('routing'));
//            Debug::log('Welcome to Luthier-CI ' . LUTHIER_CI_VERSION . '!');
//        }

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

//        Debug::log('>>> CURRENT ROUTE:', 'info', 'routing');
//        Debug::log($currentRoute, 'info', 'routing');
//        Debug::log('>>> RAW ROUTING:', 'info', 'routing');
//        Debug::log(Route::$compiled['routes'], 'info', 'routing');

        Route::setCurrentRoute($currentRoute);
    }

    /**
     * "pre_controller" hook
     *
     * @param array $params
     * @param string $URI
     * @param string $class
     * @param string $method
     *
     * @return void
     */
    private static function preControllerHook(&$params, &$URI, &$class, &$method)
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

    /**
     * "post_controller" hook
     *
     * @param array $config
     * @param array $params
     *
     * @return void
     */
    private static function postControllerConstructorHook($config, &$params)
    {
//        if (!is_cli()) {
        // Auth module bootstrap
//            if (in_array('auth', $config['modules']) || in_array('debug', $config['modules'])) {
//                app()->load->library('session');
//            }
//
//            if (in_array('auth', $config['modules'])) {
//                if (file_exists(APPPATH . '/config/auth.php')) {
//                    app()->load->config('auth');
//                }
//                Auth::init();
//                Auth::user(true);
//            }

        // Restoring flash debug messages
//            if (ENVIRONMENT != 'production' && in_array('debug', $config['modules'])) {
//                $debugBarFlashMessages = app()->session->flashdata('_debug_bar_flash');
//
//                if (!empty($debugBarFlashMessages) && is_array($debugBarFlashMessages)) {
//                    foreach ($debugBarFlashMessages as $message) {
//                        list($message, $type, $collector) = $message;
//                        Debug::log($message, $type, $collector);
//                    }
//                }
//            }
//
//            if (in_array('auth', $config['modules'])) {
//                Debug::log('>>> CURRENT AUTH SESSION:', 'info', 'auth');
//                Debug::log(Auth::session(), 'info', 'auth');
//                Debug::log('>>> CURRENT USER:', 'info', 'auth');
//                Debug::log(Auth::user(), 'info', 'auth');
//            }
//        }

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

    /**
     * "post_controller" hook
     *
     * @param array $config
     *
     * @return void
     */
    private static function postControllerHook($config)
    {
        if (app()->route->is404) {
            return;
        }

        foreach (Route::getGlobalMiddleware()['post_controller'] as $middleware) {
            app()->middleware->run($middleware);
        }

//        if (!is_cli() && in_array('auth', $config['modules'])) {
//        }
//            Auth::session('validated', false);
    }

    /**
     * "display_override" hook
     *
     * @return void
     */
    private static function displayOverrideHook()
    {
        $output = app()->output->get_output();

        if (isset(app()->db)) {
            $queries = app()->db->queries;
//            if (!empty($queries)) {
//                Debug::addCollector(new MessagesCollector('database'));
//                foreach ($queries as $query) {
//                    Debug::log($query, 'info', 'database');
//                }
//            }
        }

//        Debug::prepareOutput($output);
        app()->output->_display($output);
    }
}
