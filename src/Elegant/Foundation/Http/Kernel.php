<?php

namespace Elegant\Foundation\Http;

use Elegant\Contracts\Debug\ExceptionHandler;
use Elegant\Contracts\Http\Kernel as KernelContract;
use Elegant\Foundation\Application;
use Elegant\Routing\Exceptions\RouteNotFoundException;
use Elegant\Routing\Middleware\Middleware;
use Elegant\Routing\RouteBuilder as Route;
use Elegant\Support\Utils;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
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
     * Last value returned by a subsequent in-process controller dispatch.
     *
     * @var mixed
     */
    protected $lastControllerResult;

    /**
     * Request currently being handled (used to rematch routes in-process).
     *
     * @var \Elegant\Foundation\Http\Request|null
     */
    protected $currentRequest;

    /**
     * @param \Elegant\Foundation\Application $app
     * @param string|null $basePath
     */
    public function __construct(Application $app, ?string $basePath = null)
    {
        $this->app = $app;
        $this->basePath = rtrim($basePath ?? $app->basePath(), '/\\');
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
        $this->configureErrorReporting();

        $this->bootstrapped = true;
    }

    /**
     * {@inheritdoc}
     */
    public function handle($request)
    {
        try {
            $this->bootstrap();
            $this->currentRequest = $request;
            $this->applyRequestGlobals($request);

            if (! self::$codeIgniterLoaded) {
                return $this->handleFirstRequest();
            }

            return $this->handleSubsequentRequest();
        } catch (Throwable $e) {
            return $this->toExceptionResponse($e);
        }
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

            require_once $this->startScriptPath();
        } catch (Throwable $e) {
            $error = $e;
        } finally {
            $buffered = ob_get_clean();
            if ($previous) {
                chdir($previous);
            }

            // Mark loaded even if the first view throws — get_instance() already exists
            // and subsequent in-process requests must rematch instead of re-entering CI.
            if (function_exists('get_instance')) {
                try {
                    if (get_instance()) {
                        self::$codeIgniterLoaded = true;
                    }
                } catch (Throwable $e) {
                    //
                }
            }
        }

        if ($error) {
            return $this->toExceptionResponse($error);
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
        $this->lastControllerResult = null;

        try {
            $this->resetOutput();
            $this->resetQueryBuilder();
            $this->resetFormValidation();
            $this->resetUriFromGlobals();
            $this->rematchCurrentRoute();
            $this->dispatchCurrentRoute();
        } catch (Throwable $e) {
            $error = $e;
        }

        $buffered = ob_get_clean();

        if ($error) {
            return $this->toExceptionResponse($error);
        }

        if ($this->lastControllerResult instanceof SymfonyResponse) {
            $headers = [];
            foreach ($this->lastControllerResult->headers->all() as $name => $values) {
                $headers[$name] = implode(', ', $values);
            }

            return new Response(
                (string) $this->lastControllerResult->getContent(),
                $this->lastControllerResult->getStatusCode(),
                $headers
            );
        }

        if (is_object($this->lastControllerResult) && method_exists($this->lastControllerResult, 'render')) {
            try {
                $buffered = (string) $this->lastControllerResult->render();
            } catch (Throwable $e) {
                return $this->toExceptionResponse($e);
            }
        }

        return $this->captureResponse($buffered);
    }

    /**
     * Report an exception and convert it to an HTTP response (Laravel Kernel).
     *
     * @param \Throwable $e
     * @return \Elegant\Foundation\Http\Response
     */
    protected function toExceptionResponse(Throwable $e): Response
    {
        $this->reportException($e);

        return $this->renderException($this->currentRequest, $e);
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param \Throwable $e
     * @return void
     */
    protected function reportException(Throwable $e): void
    {
        try {
            $this->app->make(ExceptionHandler::class)->report($e);
        } catch (Throwable $ignored) {
            //
        }
    }

    /**
     * Render the exception to an HTTP response.
     *
     * @param mixed $request
     * @param \Throwable $e
     * @return \Elegant\Foundation\Http\Response
     */
    protected function renderException($request, Throwable $e): Response
    {
        try {
            $rendered = $this->app->make(ExceptionHandler::class)->render($request, $e);
        } catch (Throwable $ignored) {
            return new Response($e->getMessage(), 500, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        return $this->normalizeExceptionResponse($rendered, $e);
    }

    /**
     * Normalize handler output into the Kernel response object.
     *
     * @param mixed $rendered
     * @param \Throwable $e
     * @return \Elegant\Foundation\Http\Response
     */
    protected function normalizeExceptionResponse($rendered, Throwable $e): Response
    {
        if ($rendered instanceof Response) {
            return $rendered;
        }

        if ($rendered instanceof SymfonyResponse) {
            $headers = [];
            foreach ($rendered->headers->all() as $name => $values) {
                $headers[$name] = implode(', ', $values);
            }

            return new Response(
                (string) $rendered->getContent(),
                $rendered->getStatusCode(),
                $headers
            );
        }

        if (is_object($rendered) && method_exists($rendered, 'get_output')) {
            return new Response(
                (string) $rendered->get_output(),
                500,
                ['Content-Type' => 'text/html; charset=utf-8']
            );
        }

        if (is_string($rendered)) {
            return new Response($rendered, 500, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        return new Response($e->getMessage(), 500, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
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

        // CLI / PHPUnit often cannot apply header(); treat Location as a redirect.
        if ($status < 300 && $this->headerValue($headers, 'Location') !== null) {
            $status = 302;
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
     * MY_Model shares one CI DB singleton; leftover WHERE clauses bleed across requests.
     *
     * @return void
     */
    protected function resetQueryBuilder()
    {
        if (! function_exists('get_instance')) {
            return;
        }

        try {
            $ci = get_instance();
        } catch (Throwable $e) {
            return;
        }

        if ($ci && isset($ci->db) && method_exists($ci->db, 'reset_query')) {
            $ci->db->reset_query();
        }
    }

    /**
     * CI form_validation is a process singleton; leftover rules bleed across in-process requests.
     *
     * @return void
     */
    protected function resetFormValidation()
    {
        if (! function_exists('get_instance')) {
            return;
        }

        try {
            $ci = get_instance();
        } catch (Throwable $e) {
            return;
        }

        if ($ci && isset($ci->form_validation) && method_exists($ci->form_validation, 'reset_validation')) {
            $ci->form_validation->reset_validation();
        }
    }

    /**
     * Path used to rematch in-process requests (compiled routes have no leading slash).
     *
     * @return string
     */
    protected function currentRequestPath()
    {
        if ($this->currentRequest && is_string($this->currentRequest->uri) && $this->currentRequest->uri !== '') {
            $path = trim($this->currentRequest->uri, '/');

            return $path === '' ? '/' : $path;
        }

        return Utils::currentUrl();
    }

    /**
     * @return string
     */
    protected function currentRequestMethod()
    {
        if ($this->currentRequest && is_string($this->currentRequest->method) && $this->currentRequest->method !== '') {
            return strtoupper($this->currentRequest->method);
        }

        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * @return void
     */
    protected function resetUriFromGlobals()
    {
        $URI =& load_class('URI', 'core');
        $uriString = $this->currentRequestPath();

        if ($uriString === '/') {
            $uriString = '';
        } else {
            $uriString = trim($uriString, '/');
        }

        // Clear previous request segments so they do not bleed into the next URI.
        if (property_exists($URI, 'segments')) {
            $URI->segments = [];
        }
        if (property_exists($URI, 'rsegments')) {
            $URI->rsegments = [];
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
        $url = $this->currentRequestPath();
        $requestMethod = $this->currentRequestMethod();

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
                'App\\Http\\Controllers\\' . ltrim($class, '\\'),
                'App\\Http\\Controllers\\' . str_replace('/', '\\', $class),
            ];

            $namespace = $route->getNamespace();
            if (is_string($namespace) && $namespace !== '') {
                $candidates[] = 'App\\Http\\Controllers\\' . str_replace('/', '\\', trim($namespace, '/\\')) . '\\' . ltrim($class, '\\');
            }

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

        $controller = $this->resolveControllerInstance($class);
        $this->applyPendingAuthentication();
        $this->bindRouteParametersFromUri($route);
        $this->callPostControllerConstructorHook();

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

        $result = null;
        if (method_exists($controller, $method)) {
            $args = [];
            foreach ($route->params as $param) {
                if (isset($param->value) && $param->value !== null && $param->value !== '') {
                    $args[] = $param->value;
                }
            }
            $result = call_user_func_array([$controller, $method], $args);
        }

        $this->lastControllerResult = $result;
        $this->emitControllerResult($result);

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
     * Controller return values are discarded by CI; apply them so in-process
     * PHPUnit requests see redirects/views the same way a real HTTP SAPI would.
     *
     * @param mixed $result
     * @return void
     */
    protected function emitControllerResult($result)
    {
        if ($result instanceof SymfonyResponse) {
            http_response_code($result->getStatusCode());

            $location = $result->headers->get('Location');
            if (is_string($location) && $location !== '') {
                header('Location: ' . $location);
            }

            $content = $result->getContent();
            if (is_string($content) && $content !== '') {
                echo $content;
            }

            return;
        }

        if (is_object($result) && method_exists($result, '__toString')) {
            echo (string) $result;
        }
    }

    /**
     * @param array<string, string> $headers
     * @param string $name
     * @return string|null
     */
    protected function headerValue(array $headers, string $name)
    {
        foreach ($headers as $header => $value) {
            if (strcasecmp((string) $header, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Reuse or rebuild a controller without re-entering CI_Controller's
     * is_loaded() → load_class('Cache') path (which looks for the wrong file).
     *
     * @param string $class
     * @return object
     */
    protected function resolveControllerInstance(string $class)
    {
        $existing = function_exists('get_instance') ? get_instance() : null;

        if ($existing && get_class($existing) === $class) {
            return $existing;
        }

        $this->scrubLoaderIsLoadedPollution();

        $ref = new \ReflectionClass($class);
        $controller = $ref->newInstanceWithoutConstructor();

        if (class_exists('CI_Controller', false)) {
            $prop = new \ReflectionProperty(\CI_Controller::class, 'instance');
            $prop->setAccessible(true);
            $prop->setValue(null, $controller);
        }

        if ($existing) {
            foreach (get_object_vars($existing) as $key => $value) {
                $controller->$key = $existing->$key;
            }
        }

        $ref->getMethod('__construct')->invoke($controller);

        return $controller;
    }

    /**
     * Drop Loader-registered libraries/drivers from is_loaded() so a fresh
     * CI_Controller::__construct() does not call load_class('Cache') etc.
     *
     * @return void
     */
    protected function scrubLoaderIsLoadedPollution()
    {
        if (! function_exists('is_loaded') || ! function_exists('load_class')) {
            return;
        }

        $ref = new \ReflectionFunction('load_class');
        $statics = $ref->getStaticVariables();
        $coreClasses = $statics['_classes'] ?? [];

        $isLoaded =& is_loaded();

        foreach ($isLoaded as $key => $loadedClass) {
            if (! isset($coreClasses[$loadedClass])) {
                unset($isLoaded[$key]);
            }
        }
    }

    /**
     * Bind route placeholders from the current CI URI segments.
     *
     * @param \Elegant\Routing\Route $route
     * @return void
     */
    protected function bindRouteParametersFromUri($route)
    {
        if (! function_exists('load_class') || empty($route->params)) {
            return;
        }

        $URI =& load_class('URI', 'core');
        $segments = explode('/', trim($route->getFullPath(), '/'));
        $pCount = 0;

        foreach ($segments as $currentSegmentIndex => $segment) {
            if (! preg_match('/^\{(.*)\}$/', $segment)) {
                continue;
            }

            if (! isset($route->params[$pCount])) {
                break;
            }

            $route->params[$pCount]->value = $URI->segment($currentSegmentIndex + 1);
            $pCount++;
        }
    }

    /**
     * Re-run post_controller_constructor hooks (view shares, etc.) on subsequent
     * in-process requests — CodeIgniter.php only does this on the first boot.
     *
     * @return void
     */
    protected function callPostControllerConstructorHook()
    {
        if (! function_exists('get_instance')) {
            return;
        }

        try {
            $CI = get_instance();
            if ($CI && isset($CI->hooks) && method_exists($CI->hooks, 'call_hook')) {
                $CI->hooks->call_hook('post_controller_constructor');
            }
        } catch (Throwable $e) {
            //
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
        $_SERVER['PATH_INFO'] = $request->uri;
        $_SERVER['ORIG_PATH_INFO'] = $request->uri;
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
        if (PHP_SAPI === 'cli' && (getenv('APP_ENV') ?: (defined('ENVIRONMENT') ? ENVIRONMENT : '')) !== 'testing') {
            return;
        }

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
            if (isset($_SERVER['CI_ENV'])) {
                define('ENVIRONMENT', $_SERVER['CI_ENV']);
            } elseif (function_exists('env')) {
                define('ENVIRONMENT', env('APP_ENV', 'production'));
            } else {
                define('ENVIRONMENT', getenv('APP_ENV') ?: 'production');
            }
        }

        if (! defined('SELF')) {
            define('SELF', PHP_SAPI === 'cli' ? 'index.php' : pathinfo($_SERVER['SCRIPT_FILENAME'] ?? 'index.php', PATHINFO_BASENAME));
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
     * Path to the CodeIgniter start script.
     *
     * @return string
     */
    public function startScriptPath(): string
    {
        return $this->basePath
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'lara-igniter'
            . DIRECTORY_SEPARATOR . 'framework'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'Elegant'
            . DIRECTORY_SEPARATOR . 'Foundation'
            . DIRECTORY_SEPARATOR . 'Http'
            . DIRECTORY_SEPARATOR . 'start.php';
    }

    /**
     * @return void
     */
    protected function configureErrorReporting()
    {
        if (ENVIRONMENT === 'testing') {
            return;
        }

        switch (ENVIRONMENT) {
            case 'local':
                ini_set('display_errors', '1');
                ini_set('display_startup_errors', '1');
                error_reporting(E_ALL);
                break;
            case 'testing':
            case 'development':
                error_reporting(1);
                ini_set('display_errors', '1');
                break;
            case 'staging':
            case 'production':
                ini_set('display_errors', '0');
                error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
                break;
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
