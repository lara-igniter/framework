<?php

namespace Elegant\Foundation\Http;

use Elegant\Contracts\Http\Kernel as KernelContract;
use Elegant\Foundation\Application;
use Elegant\Routing\Exceptions\RouteNotFoundException;
use Elegant\Routing\Middleware\Middleware;
use Elegant\Routing\RouteBuilder as Route;
use Elegant\Support\Utils;
use Throwable;

class Kernel implements KernelContract
{
    /**
     * @var \Elegant\Foundation\Http\Kernel|null
     */
    protected static $instance;

    /**
     * @var \Elegant\Foundation\Application
     */
    protected $app;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var bool
     */
    protected $bootstrapped = false;

    /**
     * @var bool
     */
    protected static $codeIgniterLoaded = false;

    /**
     * @var mixed
     */
    protected $pendingUser;

    /**
     * @var array
     */
    protected $pendingSession = [];

    /**
     * @var array
     */
    protected $withoutMiddleware = [];

    /**
     * @var bool
     */
    protected $withoutMiddlewareAll = false;

    /**
     * @param \Elegant\Foundation\Application $app
     * @param string $basePath
     */
    public function __construct(Application $app, string $basePath)
    {
        $this->app = $app;
        $this->basePath = rtrim($basePath, '/\\');
        self::$instance = $this;
    }

    /**
     * @return \Elegant\Foundation\Http\Kernel|null
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * @return \Elegant\Foundation\Application
     */
    public function getApplication()
    {
        return $this->app;
    }

    /**
     * @param mixed $user
     * @return $this
     */
    public function setPendingUser($user)
    {
        $this->pendingUser = $user;

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function setPendingSession(array $data)
    {
        $this->pendingSession = $data;

        return $this;
    }

    /**
     * @param bool $all
     * @param array $middleware
     * @return $this
     */
    public function setWithoutMiddleware($all, array $middleware = [])
    {
        $this->withoutMiddlewareAll = (bool) $all;
        $this->withoutMiddleware = $middleware;

        return $this;
    }

    /**
     * @return bool
     */
    public function shouldSkipAllMiddleware()
    {
        return $this->withoutMiddlewareAll;
    }

    /**
     * @return array
     */
    public function middlewareToSkip()
    {
        return $this->withoutMiddleware;
    }

    /**
     * Apply actingAs / withSession onto the CI session (after session is available).
     *
     * @return void
     */
    public function applyPendingAuthentication()
    {
        if (! function_exists('get_instance')) {
            return;
        }

        try {
            $CI = get_instance();
        } catch (Throwable $e) {
            return;
        }

        if (! $CI || ! isset($CI->session)) {
            return;
        }

        if ($this->pendingUser !== null) {
            $user = $this->pendingUser;
            $identityColumn = 'email';

            if (isset($CI->config) && method_exists($CI->config, 'item')) {
                $configured = $CI->config->item('identity', 'ion_auth');
                if (is_string($configured) && $configured !== '') {
                    $identityColumn = $configured;
                }
            }

            $identity = $user->{$identityColumn} ?? $user->email ?? $user->username ?? $user->id;

            $CI->session->set_userdata([
                'identity' => $identity,
                $identityColumn => $identity,
                'email' => $user->email ?? null,
                'user_id' => $user->id,
                'id' => $user->id,
                'old_last_login' => $user->last_login ?? null,
                'last_check' => time(),
                'logged_user' => $user,
            ]);
        }

        foreach ($this->pendingSession as $key => $value) {
            $CI->session->set_userdata($key, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bootstrap()
    {
        if ($this->bootstrapped) {
            return;
        }

        $this->registerTestingPolyfills();
        $this->forceWebMode();
        $this->defineConstants();

        $this->bootstrapped = true;
    }

    /**
     * {@inheritdoc}
     */
    public function handle($request)
    {
        $this->bootstrap();
        $this->applyRequestGlobals($request);

        if (! self::$codeIgniterLoaded) {
            return $this->handleFirstRequest();
        }

        return $this->handleSubsequentRequest();
    }

    /**
     * {@inheritdoc}
     */
    public function terminate($request, $response)
    {
        //
    }

    /**
     * @return \Elegant\Foundation\Http\Response
     */
    protected function handleFirstRequest()
    {
        $previous = getcwd();
        chdir($this->basePath . DIRECTORY_SEPARATOR . 'public');

        ob_start();
        $error = null;

        try {
            if (! defined('SELF')) {
                define('SELF', 'index.php');
            }

            require_once BASEPATH . 'core' . DIRECTORY_SEPARATOR . 'CodeIgniter.php';
            self::$codeIgniterLoaded = true;
        } catch (Throwable $e) {
            $error = $e;
        } finally {
            $buffered = ob_get_clean();
            if ($previous) {
                chdir($previous);
            }
        }

        if ($error) {
            return new Response($error->getMessage(), 500, []);
        }

        return $this->captureResponse($buffered);
    }

    /**
     * Re-dispatch against the already-booted CodeIgniter application.
     *
     * @return \Elegant\Foundation\Http\Response
     */
    protected function handleSubsequentRequest()
    {
        ob_start();
        $error = null;

        try {
            $this->resetOutput();
            $this->resetUriFromGlobals();
            $this->rematchCurrentRoute();
            $this->dispatchCurrentRoute();
        } catch (Throwable $e) {
            $error = $e;
        }

        $buffered = ob_get_clean();

        if ($error) {
            return new Response($error->getMessage(), 500, []);
        }

        return $this->captureResponse($buffered);
    }

    /**
     * @param string|false $buffered
     * @return \Elegant\Foundation\Http\Response
     */
    protected function captureResponse($buffered)
    {
        $content = is_string($buffered) ? $buffered : '';

        if ($content === '' && function_exists('get_instance')) {
            try {
                $CI = get_instance();
                if ($CI && isset($CI->output) && method_exists($CI->output, 'get_output')) {
                    $content = (string) $CI->output->get_output();
                }
            } catch (Throwable $e) {
                //
            }
        }

        $status = http_response_code();
        if (! $status) {
            $status = 200;
        }

        $headers = [];
        foreach (headers_list() as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $headers[$name] = $value;
        }

        return new Response($content, $status, $headers);
    }

    /**
     * @return void
     */
    protected function resetOutput()
    {
        if (! function_exists('load_class')) {
            return;
        }

        $OUT =& load_class('Output', 'core');
        $OUT->set_output('');

        if (property_exists($OUT, 'headers')) {
            $OUT->headers = [];
        }

        if (function_exists('http_response_code')) {
            http_response_code(200);
        }
    }

    /**
     * @return void
     */
    protected function resetUriFromGlobals()
    {
        $URI =& load_class('URI', 'core');
        $uriString = Utils::currentUrl();

        if ($uriString === '/') {
            $uriString = '';
        } else {
            $uriString = trim($uriString, '/');
        }

        $method = new \ReflectionMethod($URI, '_set_uri_string');
        $method->setAccessible(true);
        $method->invoke($URI, $uriString);
    }

    /**
     * @return void
     */
    protected function rematchCurrentRoute()
    {
        $url = Utils::currentUrl();
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        try {
            $currentRoute = Route::getByUrl($url, $requestMethod);
            $currentRoute->is404 = false;
        } catch (RouteNotFoundException $e) {
            $currentRoute = Route::any($url, function () use ($url) {
                show_404($url);
            });
            $currentRoute->is404 = true;
        }

        $currentRoute->requestMethod = $requestMethod;
        $currentRoute->isCli = false;
        Route::setCurrentRoute($currentRoute);

        if (function_exists('get_instance')) {
            try {
                $CI = get_instance();
                if ($CI) {
                    $CI->route = $currentRoute;
                }
            } catch (Throwable $e) {
                //
            }
        }
    }

    /**
     * @return void
     */
    protected function dispatchCurrentRoute()
    {
        $route = Route::getCurrentRoute();

        if (! $route || $route->is404) {
            http_response_code(404);
            echo 'Not Found';

            return;
        }

        $action = $route->getAction();
        $class = null;
        $method = 'index';

        if (is_array($action) && count($action) >= 2) {
            $class = $action[0];
            $method = $action[1];
        } elseif (is_string($action) && strpos($action, '@') !== false) {
            [$class, $method] = explode('@', $action, 2);
        } elseif (is_callable($action)) {
            $this->applyPendingAuthentication();
            call_user_func($action);

            return;
        }

        if (is_object($class)) {
            $class = get_class($class);
        }

        if (is_string($class) && $class !== '' && ! class_exists($class)) {
            $candidates = [
                $class,
                'App\\Controllers\\' . ltrim($class, '\\'),
                'App\\Controllers\\' . str_replace('/', '\\', $class),
            ];

            foreach ($candidates as $candidate) {
                if (class_exists($candidate)) {
                    $class = $candidate;
                    break;
                }
            }
        }

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            http_response_code(500);
            echo 'Controller ' . (is_string($class) ? $class : gettype($class)) . ' does not exist!';

            return;
        }

        $controller = new $class();
        $this->applyPendingAuthentication();

        // Setting / menu models expect the cache driver; load_class('Cache') looks for
        // libraries/Cache.php (missing) while driver() loads libraries/Cache/Cache.php.
        if (isset($controller->load) && ! isset($controller->cache)) {
            $controller->load->driver('cache', ['adapter' => 'file', 'backup' => 'dummy']);
        }

        // Route / global middleware already ran on the first boot. Re-running the
        // full stack on subsequent in-process requests can reload CI drivers
        // into a bad state. Apply only route middleware when needed.
        if (! $this->withoutMiddlewareAll && $this->withoutMiddleware === []) {
            $middlewareRunner = new Middleware();

            foreach ($route->getMiddleware() as $middleware) {
                if (is_string($middleware)) {
                    $middleware = [$middleware];
                }

                foreach ((array) $middleware as $item) {
                    $middlewareRunner->run($item);
                }
            }
        }

        if (method_exists($controller, $method)) {
            call_user_func([$controller, $method]);
        }

        if (function_exists('get_instance')) {
            $CI = get_instance();
            if ($CI && isset($CI->output)) {
                $output = $CI->output->get_output();
                if (is_string($output) && $output !== '') {
                    echo $output;
                }
            }
        }
    }

    /**
     * @param \Elegant\Foundation\Http\Request $request
     * @return void
     */
    protected function applyRequestGlobals($request)
    {
        $queryString = http_build_query($request->query);

        $_SERVER['REQUEST_METHOD'] = $request->method;
        $_SERVER['REQUEST_URI'] = $request->getRequestUri();
        $_SERVER['QUERY_STRING'] = $queryString;
        $_SERVER['SCRIPT_NAME'] = $request->server['SCRIPT_NAME'] ?? '/index.php';
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
        $_SERVER['HTTP_HOST'] = $request->server['HTTP_HOST'] ?? 'localhost';
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
        $_SERVER['SERVER_PORT'] = $request->server['SERVER_PORT'] ?? '80';
        $_SERVER['HTTPS'] = $request->server['HTTPS'] ?? 'off';
        $_SERVER['REMOTE_ADDR'] = $request->server['REMOTE_ADDR'] ?? '127.0.0.1';

        foreach ($request->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $_SERVER[$key] = $value;
            }
        }

        $_GET = $request->query;
        $_POST = $request->request;
        $_COOKIE = $request->cookies;
        $_FILES = $request->files;
        $_REQUEST = array_merge($_GET, $_POST);
    }

    /**
     * @return void
     */
    protected function forceWebMode()
    {
        // is_cli() is forced false by Elegant/Foundation/Testing/phpunit.php when
        // PHPUnit runs (Composer autoload files). Keep a defensive define here.
        if (! function_exists('is_cli')) {
            function is_cli()
            {
                return false;
            }
        }
    }

    /**
     * @return void
     */
    protected function defineConstants()
    {
        $basePath = $this->basePath;

        if (! defined('ENVIRONMENT')) {
            define('ENVIRONMENT', getenv('APP_ENV') ?: 'testing');
        }

        if (! defined('BASEPATH')) {
            $systemPath = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'codeigniter' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'system';
            define('BASEPATH', realpath($systemPath) . DIRECTORY_SEPARATOR);
        }

        if (! defined('FCPATH')) {
            define('FCPATH', $basePath . DIRECTORY_SEPARATOR);
        }

        if (! defined('SYSDIR')) {
            define('SYSDIR', basename(BASEPATH));
        }

        if (! defined('APPPATH')) {
            define('APPPATH', realpath($basePath . DIRECTORY_SEPARATOR . 'app') . DIRECTORY_SEPARATOR);
        }

        if (! defined('VIEWPATH')) {
            define('VIEWPATH', realpath($basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views') . DIRECTORY_SEPARATOR);
        }
    }

    /**
     * @return void
     */
    protected function registerTestingPolyfills()
    {
        if (! function_exists('str_contains')) {
            function str_contains($haystack, $needle)
            {
                return $needle === '' || strpos($haystack, $needle) !== false;
            }
        }
    }
}
