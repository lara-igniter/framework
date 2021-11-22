<?php

namespace Elegant\Foundation\Hooks;

use Elegant\Routing\Exceptions\RouteNotFoundException;
use Elegant\Routing\Middleware\Middleware;
use Elegant\Routing\Middleware\RouteAjaxMiddleware;
use Elegant\Routing\RouteBuilder;
use Elegant\Support\Facades\Route;
use Elegant\Support\Utils;
use Exception;

class Routing
{
    /**
     * Gets the Routing hooks
     *
     * @param string|null $config Luthier CI configuration
     *
     * @return array
     */
    public static function getHooks(string $config = null): array
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
        define('LUTHIER_CI_VERSION', '1.0.5');
        define('LUTHIER_CI_DIR', __DIR__);

        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $isCli = is_cli();
        $isWeb = !is_cli();

        require_once realpath(dirname(__FILE__) . '../../Support/Facades/Route.php');

//        if (in_array('auth', $config['modules'])) {
//            require_once __DIR__ . '/Facades/Auth.php';
//        }

        if (!file_exists(FCPATH . '/routes')) {
            mkdir(FCPATH . '/routes');
        }

        if (!file_exists(APPPATH . '/middleware')) {
            mkdir(APPPATH . '/middleware');
        }

        if (!file_exists(FCPATH . '/routes/web.php')) {
            copy(__DIR__ . '../../Routing/Resources/WebRoutes.php', FCPATH . '/routes/web.php');
        }

        if ($isWeb) {
            require_once(FCPATH . '/routes/web.php');
        }

        if (!file_exists(FCPATH . '/routes/api.php')) {
            copy(__DIR__ . '../../Routing/Resources/ApiRoutes.php', FCPATH . '/routes/api.php');
        }

        if ($isAjax || $isWeb) {
            Route::group('/', ['middleware' => [new RouteAjaxMiddleware()]],
                function () {
                    require_once(FCPATH . '/routes/api.php');
                }
            );
        }

        if (!file_exists(FCPATH . '/routes/cli.php')) {
            copy(__DIR__ . '../../Routing/Resources/CliRoutes.php', FCPATH . '/routes/cli.php');
        }

        if ($isCli) {
            require_once(FCPATH . '/routes/cli.php');
            Route::set('default_controller', RouteBuilder::DEFAULT_CONTROLLER);
        }

        if (!file_exists(APPPATH . '/controllers/' . RouteBuilder::DEFAULT_CONTROLLER . '.php')) {
            copy(__DIR__ . '../../Routing/Resources/Controller.php', APPPATH . '/controllers/' . RouteBuilder::DEFAULT_CONTROLLER . '.php');
        }

        require_once(__DIR__ . '../../Foundation/helpers.php');

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
            Route::$compiled['routes'][$url] = RouteBuilder::DEFAULT_CONTROLLER . '/index';
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
    private static function preControllerHook(array &$params, string &$URI, string &$class, string &$method)
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

            $class = RouteBuilder::DEFAULT_CONTROLLER;

            if (!class_exists($class)) {
                require_once APPPATH . '/controllers/' . RouteBuilder::DEFAULT_CONTROLLER . '.php';
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
    private static function postControllerConstructorHook(array $config, array &$params)
    {
        if (!is_cli()) {
            // Auth module bootstrap
//            if (in_array('auth', $config['modules']) || in_array('debug', $config['modules'])) {
//                ci()->load->library('session');
//            }
//
//            if (in_array('auth', $config['modules'])) {
//                if (file_exists(APPPATH . '/config/auth.php')) {
//                    ci()->load->config('auth');
//                }
//                Auth::init();
//                Auth::user(true);
//            }

            // Restoring flash debug messages
//            if (ENVIRONMENT != 'production' && in_array('debug', $config['modules'])) {
//                $debugBarFlashMessages = ci()->session->flashdata('_debug_bar_flash');
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
        }

        // Current route configuration and dispatch
        ci()->route = Route::getCurrentRoute();

        if (!ci()->route->is404) {
            ci()->load->helper('url');

            ci()->middleware = new Middleware();

            if (method_exists(ci(), 'preMiddleware')) {
                call_user_func([ci(), 'preMiddleware']);
            }

            foreach (Route::getGlobalMiddleware()['pre_controller'] as $middleware) {
                ci()->middleware->run($middleware);
            }

            // Setting "sticky" route parameters values as default for current route
            foreach (ci()->route->params as &$param) {
                if (substr($param->getName(), 0, 1) == '_') {
                    Route::setDefaultParam($param->getName(), ci()->route->param($param->getName()));
                }
            }

            foreach (ci()->route->getMiddleware() as $middleware) {
                if (is_string($middleware)) {
                    $middleware = [$middleware];
                }

                foreach ($middleware as $_middleware) {
                    ci()->middleware->run($_middleware);
                }
            }
        }

        if (is_callable(ci()->route->getAction())) {
            call_user_func_array(ci()->route->getAction(), $params);
        }
    }

    /**
     * "post_controller" hook
     *
     * @param array $config
     *
     * @return void
     */
    private static function postControllerHook(array $config)
    {
        if (ci()->route->is404) {
            return;
        }

        foreach (Route::getGlobalMiddleware()['post_controller'] as $middleware) {
            ci()->middleware->run($middleware);
        }

//        if (!is_cli() && in_array('auth', $config['modules'])) {
//            Auth::session('validated', false);
//        }
    }

    /**
     * "display_override" hook
     *
     * @return void
     */
    private static function displayOverrideHook()
    {
        $output = ci()->output->get_output();

        if (isset(ci()->db)) {
            $queries = ci()->db->queries;
//            if (!empty($queries)) {
//                Debug::addCollector(new MessagesCollector('database'));
//                foreach ($queries as $query) {
//                    Debug::log($query, 'info', 'database');
//                }
//            }
        }

//        Debug::prepareOutput($output);
        ci()->output->_display($output);
    }
}
