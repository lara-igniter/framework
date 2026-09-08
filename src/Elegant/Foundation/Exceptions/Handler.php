<?php

namespace Elegant\Foundation\Exceptions;

use Closure;
use Elegant\Database\Model\ModelNotFoundException;
use Elegant\Database\RecordsNotFoundException;
use Elegant\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Exception;
use Psr\Log\LoggerInterface;
use Throwable;

class Handler implements ExceptionHandlerContract
{
    /**
     * The container implementation.
     *
     * @var mixed
     */
    protected $container;

    /**
     * The resolved exception handler instance for early bootstrap.
     *
     * @var \Elegant\Foundation\Exceptions\Handler|null
     */
    protected static $resolvedInstance;

    /**
     * Whether an exception is currently being handled.
     *
     * @var bool
     */
    protected $handlingException = false;

    /**
     * A list of the exception types that are not reported.
     *
     * @var string[]
     */
    protected $dontReport = [];

    /**
     * The callbacks that should be used during reporting.
     *
     * @var array
     */
    protected $reportCallbacks = [];

    /**
     * The callbacks that should be used during rendering.
     *
     * @var \Closure[]
     */
    protected $renderCallbacks = [];

    /**
     * The registered exception mappings.
     *
     * @var array<string, \Closure>
     */
    protected $exceptionMap = [];

    /**
     * A list of the internal exception types that should not be reported.
     *
     * @var string[]
     */
    protected $internalDontReport = [
        ModelNotFoundException::class,
        RecordsNotFoundException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var string[]
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Create a new exception handler instance.
     *
     * @param  mixed  $container
     * @return void
     */
    public function __construct($container = null)
    {
        $this->container = $container;

        $this->register();
    }

    /**
     * Resolve the application exception handler instance.
     *
     * Prefers the instance bound by ExceptionServiceProvider; falls back to a
     * fresh App\Exceptions\Handler (or this class) for early bootstrap errors.
     *
     * @return \Elegant\Foundation\Exceptions\Handler
     */
    public static function resolve(): ?Handler
    {
        if (static::$resolvedInstance instanceof self) {
            return static::$resolvedInstance;
        }

        if (function_exists('get_instance') && function_exists('app')) {
            try {
                $ci = @get_instance();

                if (is_object($ci) && isset($ci->{'exception.handler'}) && $ci->{'exception.handler'} instanceof self) {
                    return static::$resolvedInstance = $ci->{'exception.handler'};
                }
            } catch (Throwable $e) {
                // App may not be ready yet during early bootstrap.
            }
        }

        if (class_exists(\App\Exceptions\Handler::class)) {
            static::$resolvedInstance = new \App\Exceptions\Handler();
        } else {
            static::$resolvedInstance = new static();
        }

        return static::$resolvedInstance;
    }

    /**
     * Set the resolved exception handler instance.
     *
     * @param  \Elegant\Foundation\Exceptions\Handler  $handler
     * @return void
     */
    public static function setResolvedInstance(self $handler)
    {
        static::$resolvedInstance = $handler;
    }

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Register a reportable callback.
     *
     * @param  callable  $reportUsing
     * @return $this
     */
    public function reportable(callable $reportUsing): Handler
    {
        if (!$reportUsing instanceof Closure) {
            $reportUsing = Closure::fromCallable($reportUsing);
        }

        $this->reportCallbacks[] = $reportUsing;

        return $this;
    }

    /**
     * Register a renderable callback.
     *
     * @param  callable  $renderUsing
     * @return $this
     */
    public function renderable(callable $renderUsing): Handler
    {
        if (!$renderUsing instanceof Closure) {
            $renderUsing = Closure::fromCallable($renderUsing);
        }

        $this->renderCallbacks[] = $renderUsing;

        return $this;
    }

    /**
     * Register a new exception mapping.
     *
     * @param \Closure|string $from
     * @param \Closure|string|null $to
     * @return $this
     * @throws \ReflectionException
     */
    public function map($from, $to = null): Handler
    {
        if (is_string($to)) {
            $to = function ($exception) use ($to) {
                return new $to('', 0, $exception);
            };
        }

        if (is_callable($from) && is_null($to)) {
            $to = $from;
            $from = $this->getFirstClosureParameterType($to);
        }

        if (!is_string($from) || !$to instanceof Closure) {
            throw new \InvalidArgumentException('Invalid exception mapping.');
        }

        $this->exceptionMap[$from] = $to;

        return $this;
    }

    /**
     * Indicate that the given exception type should not be reported.
     *
     * @param  string  $class
     * @return $this
     */
    public function ignore(string $class): Handler
    {
        $this->dontReport[] = $class;

        return $this;
    }

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $e)
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        // Run reportable callbacks
        foreach ($this->reportCallbacks as $callback) {
            if ($callback($e) === false) {
                return;
            }
        }

        try {
            $logger = null;

            if (is_object($this->container) && method_exists($this->container, 'make')) {
                $logger = $this->container->make(LoggerInterface::class);
            } elseif ($this->appHas('bugsnag.logger')) {
                $bound = app('bugsnag.logger');
                if ($bound instanceof LoggerInterface) {
                    $logger = $bound;
                }
            }
        } catch (Throwable $ex) {
            $logger = null;
        }

        if ($logger) {
            $logger->error(
                $e->getMessage(),
                array_merge(
                    $this->buildExceptionContext($e),
                    $this->context(),
                    ['exception' => $e]
                )
            );
        } else {
            error_log($this->formatExceptionForLog($e));
        }
    }

    /**
     * Determine if a value is bound on the CI super-object without notices.
     *
     * @param  string  $abstract
     * @return bool
     */
    protected function appHas($abstract)
    {
        if (! function_exists('get_instance')) {
            return false;
        }

        try {
            $ci = get_instance();
        } catch (Throwable $e) {
            return false;
        }

        return is_object($ci) && isset($ci->{$abstract});
    }

    /**
     * Determine if the exception should be reported.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    public function shouldReport(Throwable $e): bool
    {
        return !$this->shouldntReport($e);
    }

    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function shouldntReport(Throwable $e): bool
    {
        $dontReport = array_merge($this->dontReport, $this->internalDontReport);

        foreach ($dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  mixed  $request
     * @param  \Throwable  $e
     * @return mixed
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // Prepare and map the exception like Laravel does
        $e = $this->prepareException($this->mapException($e));

        if (method_exists($e, 'render') && $response = $e->render($request)) {
            return $response;
        }

        if ($e instanceof Exception && method_exists($e, 'getResponse')) {
            return $e->getResponse();
        }

        // Run renderable callbacks
        foreach ($this->renderCallbacks as $callback) {
            $response = $callback($e, $request);
            if ($response !== null) {
                return $response;
            }
        }

        return $this->prepareResponse($request, $e);
    }

    /**
     * Prepare the exception for rendering.
     *
     * @param  \Throwable  $e
     * @return \Throwable
     */
    protected function prepareException(Throwable $e): Throwable
    {
        return $e;
    }

    /**
     * Map the exception using the registered exception mappings.
     *
     * @param  \Throwable  $e
     * @return \Throwable
     */
    protected function mapException(Throwable $e): Throwable
    {
        $name = get_class($e);

        if (isset($this->exceptionMap[$name])) {
            return ($this->exceptionMap[$name])($e);
        }

        return $e;
    }

    /**
     * Prepare a response for the given exception.
     *
     * @param  mixed  $request
     * @param  \Throwable  $e
     * @return mixed
     */
    protected function prepareResponse($request, Throwable $e)
    {
        // Handle specific framework exceptions
        $response = $this->handleFrameworkExceptions($e, $request);
        if ($response !== null) {
            return $response;
        }

        if (!$this->isHttpException($e) && $this->shouldDisplayDebugInfo()) {
            return $this->toIlluminateResponse($this->renderExceptionContent($e), $e);
        }

        if (!$this->isHttpException($e)) {
            $e = $this->createHttpException(500, $e->getMessage(), $e);
        }

        return $this->toIlluminateResponse(
            $this->renderHttpException($e),
            $e
        );
    }

    /**
     * Render an exception using Whoops.
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderExceptionWithWhoops(Throwable $e): string
    {
        try {
            if (class_exists(\Whoops\Run::class)) {
                $whoops = new \Whoops\Run();
                $whoops->allowQuit(false);
                $whoops->writeToOutput(false);
                $whoops->pushHandler($this->whoopsHandler());

                $html = (string) $whoops->handleException($e);

                if ($html !== '') {
                    return $html;
                }
            }
        } catch (Throwable $whoopsException) {
            error_log('Whoops failed: ' . $whoopsException->getMessage());
        }

        return $this->renderExceptionAsDebugHTML($e);
    }

    /**
     * Get the Whoops handler for the application.
     *
     * @return \Whoops\Handler\HandlerInterface
     */
    protected function whoopsHandler()
    {
        try {
            return (new WhoopsHandler())->forDebug();
        } catch (Throwable $e) {
            error_log('Failed to create WhoopsHandler: ' . $e->getMessage());
        }

        $handler = new \Whoops\Handler\PrettyPageHandler();
        $handler->handleUnconditionally(true);

        return $handler;
    }

    /**
     * Render the given HTTP exception.
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderHttpException(Throwable $e): string
    {
        $status = $this->isHttpException($e) ? (int) $e->getStatusCode() : 500;
        $view = $this->getHttpExceptionView($status);

        if (function_exists('view') && $this->httpExceptionViewExists($view)) {
            try {
                return (string) view($view, [
                    'exception' => $e,
                    'heading' => $this->getHttpStatusText($status),
                    'message' => $e->getMessage() ?: $this->getHttpStatusText($status),
                ]);
            } catch (Throwable $viewException) {
                error_log('Failed to render error view: ' . $viewException->getMessage());
            }
        }

        if (function_exists('load_class')) {
            try {
                $exceptions = & load_class('Exceptions', 'core');
                $heading = $this->getHttpStatusText($status);
                $message = $e->getMessage() ?: $heading;

                return $exceptions->show_error($heading, $message, (string) $status, $status);
            } catch (Throwable $ciException) {
                error_log('Failed to render CI exception page: ' . $ciException->getMessage());
            }
        }

        return $this->renderExceptionAsGenericHTML($e);
    }

    /**
     * Get the Blade view name for an HTTP status code.
     *
     * @param int $status
     * @return string
     */
    protected function getHttpExceptionView(int $status): string
    {
        return 'errors.' . $status;
    }

    /**
     * Determine if an HTTP exception view exists.
     *
     * @param string $view
     * @return bool
     */
    protected function httpExceptionViewExists(string $view): bool
    {
        if (function_exists('view') && is_object($factory = view()) && method_exists($factory, 'exists')) {
            return $factory->exists($view);
        }

        if (defined('VIEWPATH')) {
            $path = VIEWPATH . 'errors' . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, substr($view, strlen('errors.'))) . '.blade.php';

            return is_file($path);
        }

        return false;
    }

    /**
     * Get a human-readable HTTP status text.
     *
     * @param int $status
     * @return string
     */
    protected function getHttpStatusText(int $status): string
    {
        $texts = [
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            419 => 'Page Expired',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            503 => 'Service Unavailable',
        ];

        return $texts[$status] ?? 'Error';
    }

    /**
     * Render an exception as debug HTML (our own implementation).
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderExceptionAsDebugHTML(Throwable $e): string
    {
        $title = get_class($e);
        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $trace = $this->formatTraceForDisplay($e->getTrace());

        return "<!DOCTYPE html>
<html lang=\"en\">
    <head>
        <meta charset=\"UTF-8\" />
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
        <title>Application Error - {$title}</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
            .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
            .header { background: #dc3545; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .header h1 { font-size: 24px; margin-bottom: 10px; }
            .header p { font-size: 16px; opacity: 0.9; }
            .content { background: white; border-radius: 0 0 8px 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .section { padding: 20px; border-bottom: 1px solid #eee; }
            .section:last-child { border-bottom: none; }
            .section h2 { color: #dc3545; margin-bottom: 15px; font-size: 18px; }
            .file-info { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; }
            .trace { background: #f8f9fa; border-radius: 4px; max-height: 400px; overflow-y: auto; }
            .trace-item { padding: 12px 15px; border-bottom: 1px solid #dee2e6; font-family: 'Courier New', monospace; font-size: 13px; }
            .trace-item:last-child { border-bottom: none; }
            .trace-function { color: #0066cc; font-weight: bold; }
            .trace-file { color: #666; }
            .trace-line { color: #dc3545; }
            code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        </style>
    </head>
    <body>
        <div class=\"container\">
            <div class=\"header\">
                <h1>{$title}</h1>
                <p>{$message}</p>
            </div>
            <div class=\"content\">
                <div class=\"section\">
                    <h2>Exception Details</h2>
                    <div class=\"file-info\">
                        <strong>File:</strong> {$file}<br>
                        <strong>Line:</strong> {$line}<br>
                        <strong>Type:</strong> " . get_class($e) . "
                    </div>
                </div>
                <div class=\"section\">
                    <h2>Stack Trace</h2>
                    <div class=\"trace\">
                        {$trace}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>";
    }

    /**
     * Format the stack trace for display.
     *
     * @param  array  $trace
     * @return string
     */
    protected function formatTraceForDisplay(array $trace): string
    {
        $output = '';

        foreach ($trace as $index => $item) {
            $file = $item['file'] ?? 'unknown';
            $line = $item['line'] ?? 'unknown';
            $function = '';

            if (isset($item['class'])) {
                $function .= $item['class'];
            }

            if (isset($item['type'])) {
                $function .= $item['type'];
            }

            if (isset($item['function'])) {
                $function .= $item['function'] . '()';
            }

            $output .= '<div class="trace-item">';
            $output .= '<div class="trace-function">' . htmlspecialchars($function) . '</div>';
            $output .= '<div class="trace-file">' . htmlspecialchars($file) . ' <span class="trace-line">line ' . $line . '</span></div>';
            $output .= '</div>';
        }

        return $output ?: '<div class="trace-item">No stack trace available</div>';
    }

    /**
     * Determine if we should display debug information.
     *
     * @return bool
     */
    protected function shouldDisplayDebugInfo(): bool
    {
        if (function_exists('config_item')) {
            $debug = config_item('debug');
            if ($debug === true || $debug === 'true' || $debug === '1' || $debug === 1) {
                return true;
            }
            // Explicit false from config wins over ENVIRONMENT heuristics.
            if ($debug === false || $debug === 'false' || $debug === '0' || $debug === 0) {
                // Still allow env() override below only if config key missing — config is set.
            }
        }

        if (defined('ENVIRONMENT') && in_array(ENVIRONMENT, ['local', 'development', 'testing'], true)) {
            if (function_exists('env')) {
                $debug = env('APP_DEBUG', true);

                return $debug === true || $debug === 'true' || $debug === '1' || $debug === 1;
            }

            return true;
        }

        if (function_exists('env')) {
            $debug = env('APP_DEBUG', false);
            if ($debug === true || $debug === 'true' || $debug === '1' || $debug === 1) {
                return true;
            }
        }

        if (isset($_ENV['APP_DEBUG'])) {
            $debug = $_ENV['APP_DEBUG'];
            if ($debug === true || $debug === 'true' || $debug === '1') {
                return true;
            }
        }

        $debug = getenv('APP_DEBUG');
        if ($debug === 'true' || $debug === '1' || $debug === true) {
            return true;
        }

        return false;
    }

    /**
     * Get the exception content for production.
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderExceptionContent(Throwable $e): string
    {
        // Check if we should display debug information
        if (!$this->shouldDisplayDebugInfo()) {
            // No debug mode - show simple error
            return $this->renderExceptionAsGenericHTML($e);
        }

        // We're in debug mode - now check environment and Whoops availability
        $env = 'development'; // default
        if (function_exists('config_item')) {
            $env = config_item('env') ?: 'development';
        }

        if ($env === 'production') {
            // Production + debug=true: Show CodeIgniter-style exception
            return $this->renderCodeIgniterException($e);
        } else {
            // Any other env + debug=true: Try Whoops first, fallback to CodeIgniter
            if (class_exists(\Whoops\Run::class)) {
                return $this->renderExceptionWithWhoops($e);
            } else {
                return $this->renderCodeIgniterException($e);
            }
        }
    }

    /**
     * Render an exception in CodeIgniter style.
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderCodeIgniterException(Throwable $e): string
    {
        $title = get_class($e);
        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $trace = $this->formatCodeIgniterTrace($e->getTrace());

        return "<!DOCTYPE html>
<html lang=\"en\">
    <head>
        <meta charset=\"UTF-8\" />
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
        <title>CodeIgniter Application Error</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
            .container { background: white; border: 1px solid #ddd; border-radius: 4px; padding: 20px; max-width: 1000px; margin: 0 auto; }
            .header { background: #d9534f; color: white; padding: 15px; margin: -20px -20px 20px -20px; border-radius: 4px 4px 0 0; }
            .header h1 { margin: 0; font-size: 20px; }
            .error-info { background: #f8f8f8; border-left: 4px solid #d9534f; padding: 15px; margin: 20px 0; }
            .file-info { font-family: 'Courier New', monospace; background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
            .trace { margin-top: 20px; }
            .trace h3 { color: #d9534f; margin-bottom: 10px; }
            .trace-item { background: #f9f9f9; border-left: 3px solid #ccc; padding: 10px; margin: 5px 0; font-family: 'Courier New', monospace; font-size: 12px; }
            .trace-function { color: #0066cc; font-weight: bold; }
            .trace-file { color: #666; margin-top: 3px; }
        </style>
    </head>
    <body>
        <div class=\"container\">
            <div class=\"header\">
                <h1>A PHP Error was encountered</h1>
            </div>

            <div class=\"error-info\">
                <strong>Severity:</strong> Error<br>
                <strong>Message:</strong> {$message}<br>
                <strong>Filename:</strong> {$file}<br>
                <strong>Line Number:</strong> {$line}
            </div>

            <div class=\"file-info\">
                <strong>Exception:</strong> {$title}
            </div>

            <div class=\"trace\">
                <h3>Backtrace:</h3>
                {$trace}
            </div>
        </div>
    </body>
</html>";
    }

    /**
     * Format stack trace in CodeIgniter style.
     *
     * @param  array  $trace
     * @return string
     */
    protected function formatCodeIgniterTrace(array $trace): string
    {
        $output = '';

        foreach ($trace as $index => $item) {
            $file = $item['file'] ?? 'unknown';
            $line = $item['line'] ?? 'unknown';
            $function = '';

            if (isset($item['class'])) {
                $function = $item['class'];
                if (isset($item['type'])) {
                    $function .= $item['type'];
                }
            }

            if (isset($item['function'])) {
                $function .= $item['function'] . '()';
            }

            $output .= '<div class="trace-item">';
            $output .= '<div class="trace-function">File: ' . basename($file) . '</div>';
            $output .= '<div class="trace-file">Line: ' . $line . '</div>';
            if ($function) {
                $output .= '<div class="trace-function">Function: ' . htmlspecialchars($function) . '</div>';
            }
            $output .= '</div>';
        }

        return $output ?: '<div class="trace-item">No backtrace available</div>';
    }

    /**
     * Render an exception as generic HTML.
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderExceptionAsGenericHTML(Throwable $e): string
    {
        $title = $this->shouldDisplayDebugInfo() ? get_class($e) : 'Server Error';
        $message = $this->shouldDisplayDebugInfo() ? $e->getMessage() : 'Something went wrong.';

        return "<!DOCTYPE html>
<html lang=\"en\">
    <head>
        <meta charset=\"UTF-8\" />
        <meta name=\"robots\" content=\"noindex,nofollow\" />
        <style>
            body { background-color: #F9F9F9; color: #333; font: 14px Verdana, Arial, sans-serif; }
            .container { margin: 50px auto; max-width: 600px; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            h1 { color: #B0413E; }
            .trace { background: #f1f1f1; padding: 10px; font-family: monospace; white-space: pre-wrap; }
        </style>
        <title>{$title}</title>
    </head>
    <body>
        <div class=\"container\">
            <h1>{$title}</h1>
            <p>{$message}</p>" .
            ($this->shouldDisplayDebugInfo() ? "<div class=\"trace\">{$e->getTraceAsString()}</div>" : '') .
        '</div>
    </body>
</html>';
    }

    /**
     * Convert the given exception to an Illuminate response.
     *
     * @param  mixed  $response
     * @param  \Throwable  $exception
     * @return mixed
     */
    protected function toIlluminateResponse($response, Throwable $exception)
    {
        if (is_string($response)) {
            $response = $this->createResponse($response, 500);
        }

        if (method_exists($response, 'exception')) {
            $response->exception = $exception;
        }

        return $response;
    }

    /**
     * Determine if the exception is an HTTP exception.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function isHttpException(Throwable $e): bool
    {
        return method_exists($e, 'getStatusCode') && method_exists($e, 'getHeaders');
    }


    /**
     * Build the exception context array.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function buildExceptionContext(Throwable $e): array
    {
        $trace = [];
        foreach ($e->getTrace() as $item) {
            $trace[] = array_filter($item, function ($key) {
                return $key !== 'args';
            }, ARRAY_FILTER_USE_KEY);
        }

        return [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $trace,
        ];
    }

    /**
     * Get the default context variables for logging.
     *
     * @return array
     */
    protected function context(): array
    {
        try {
            if (! function_exists('auth')) {
                return [];
            }

            $user = auth();

            if (! is_object($user)) {
                return [];
            }

            return array_filter([
                'userId' => $user->id ?? null,
                'email' => $user->email ?? null,
            ]);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Format exception for logging.
     *
     * @param  \Throwable  $exception
     * @return string
     */
    protected function formatExceptionForLog(Throwable $exception): string
    {
        return sprintf(
            "Uncaught %s: %s in %s:%d\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
    }

    /**
     * Render an exception to the console.
     *
     * @param  mixed  $output
     * @param  \Throwable  $e
     * @return void
     */
    public function renderForConsole($output, Throwable $e)
    {
        if ($output && method_exists($output, 'writeln')) {
            $output->writeln(sprintf(
                '<error>%s</error>: %s in %s:%d',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        } else {
            echo sprintf(
                "%s: %s in %s:%d\n",
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        }
    }

    /**
     * Create an HTTP response body for the exception page.
     *
     * Do not use the global response() helper — in Laraigniter it routes through
     * app('response')->json() and will break HTML/Whoops output.
     *
     * @param string $content
     * @param int $status
     * @param array $headers
     * @return mixed
     */
    protected function createResponse(string $content, int $status = 200, array $headers = [])
    {
        if (! isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'text/html; charset=utf-8';
        }

        if (function_exists('get_instance')) {
            try {
                $CI = & get_instance();

                if (is_object($CI) && isset($CI->output)) {
                    $CI->output->set_status_header($status);

                    if (method_exists($CI->output, 'set_content_type')) {
                        $CI->output->set_content_type('text/html', 'utf-8');
                    }

                    foreach ($headers as $key => $value) {
                        $CI->output->set_header($key . ': ' . $value);
                    }

                    $CI->output->set_output($content);

                    return $CI->output;
                }
            } catch (Throwable $e) {
                // Fall through to a plain string response.
            }
        }

        if (! headers_sent()) {
            http_response_code($status);

            foreach ($headers as $key => $value) {
                header($key . ': ' . $value);
            }
        }

        return $content;
    }

    /**
     * Create a JSON response.
     *
     * @param array $data
     * @param int $status
     * @param array $headers
     * @return mixed
     */
    protected function createJsonResponse(array $data, int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json';
        return $this->createResponse(json_encode($data), $status, $headers);
    }

    /**
     * Create an HTTP exception.
     *
     * @param int $statusCode
     * @param string $message
     * @param  \Throwable|null  $previous
     * @return \Exception
     */
    protected function createHttpException(int $statusCode, string $message = '', Throwable $previous = null): Exception
    {
        // Create a custom HTTP exception that mimics Symfony's HttpException
        return new class ($statusCode, $message, $previous) extends \Exception {
            protected $statusCode;
            protected $headers;

            public function __construct($statusCode, $message = '', Throwable $previous = null, $headers = [])
            {
                $this->statusCode = $statusCode;
                $this->headers = $headers;
                parent::__construct($message, 0, $previous);
            }

            public function getStatusCode()
            {
                return $this->statusCode;
            }

            public function getHeaders()
            {
                return $this->headers;
            }
        };
    }

    /**
     * Initialize the exception handler without replacing PHP handlers.
     *
     * Global handlers are registered early in CodeIgniter.php via Common.php
     * wrappers so Bugsnag can wrap them on pre_system. This only runs register().
     *
     * @return void
     */
    public function initialize()
    {
        $this->register();
    }

    /**
     * Set up the global exception handlers.
     *
     * Prefer CodeIgniter bootstrap + Common.php wrappers so Bugsnag can wrap
     * the current handler. Call this only when you intentionally own the
     * global handlers (e.g. isolated testing).
     *
     * @return void
     */
    public function setupHandlers()
    {
        error_reporting(-1);

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalError']);
    }

    /**
     * Handle uncaught exceptions.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function handleException(Throwable $exception)
    {
        if ($this->handlingException) {
            $this->renderBasicError($exception);

            exit(1);
        }

        $this->handlingException = true;

        try {
            $this->report($exception);

            if (function_exists('is_cli') && is_cli()) {
                $this->renderForConsole(null, $exception);
                exit(1);
            }

            if (! headers_sent() && function_exists('set_status_header')) {
                $status = $this->isHttpException($exception) ? (int) $exception->getStatusCode() : 500;
                set_status_header($status);
            }

            $request = $this->getRequest();
            $response = $this->render($request, $exception);

            if (is_object($response) && method_exists($response, 'send')) {
                $response->send();
            } elseif (is_object($response) && method_exists($response, '_display')) {
                $response->_display();
            } else {
                echo $response;
            }
        } catch (\Throwable $e) {
            $this->renderBasicError($exception, $e);
        } finally {
            $this->handlingException = false;
        }

        exit(1);
    }

    /**
     * Handle PHP errors.
     *
     * @param int $level
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if ($this->handlingException) {
            return true;
        }

        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }

        return false;
    }

    /**
     * Handle fatal errors.
     *
     * @return void
     * @throws \ReflectionException
     */
    public function handleFatalError()
    {
        $error = error_get_last();

        if ($error !== null && $this->isFatalError($error['type'])) {
            if (class_exists(\Elegant\Foundation\Exceptions\Error\FatalError::class)) {
                $exception = new \Elegant\Foundation\Exceptions\Error\FatalError(
                    $error['message'],
                    0,
                    $error,
                    0
                );
            } else {
                $exception = new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                );
            }

            $this->handleException($exception);
        }
    }

    /**
     * Check if the error type is fatal.
     *
     * @param int $type
     * @return bool
     */
    protected function isFatalError(int $type): bool
    {
        return in_array($type, [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_CORE_WARNING,
            E_COMPILE_ERROR,
            E_COMPILE_WARNING,
        ], true);
    }

    /**
     * Get the current request.
     *
     * @return mixed
     */
    protected function getRequest()
    {
        try {
            if ($this->appHas('input')) {
                return app('input');
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Render the basic error page as a fallback.
     *
     * @param \Throwable $exception
     * @param \Throwable|null $secondary
     * @return void
     */
    protected function renderBasicError(Throwable $exception, Throwable $secondary = null)
    {
        if (! headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        if ($this->shouldDisplayDebugInfo()) {
            echo '<h1>Application Error</h1>';
            echo '<p><strong>Message:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($exception->getFile()) . ':' . $exception->getLine() . '</p>';
            echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';

            if ($secondary !== null) {
                echo '<h2>Rendering failed with</h2>';
                echo '<p><strong>Message:</strong> ' . htmlspecialchars($secondary->getMessage()) . '</p>';
                echo '<p><strong>File:</strong> ' . htmlspecialchars($secondary->getFile()) . ':' . $secondary->getLine() . '</p>';
                echo '<pre>' . htmlspecialchars($secondary->getTraceAsString()) . '</pre>';
            }
        } else {
            echo '<h1>Internal Server Error</h1>';
            echo '<p>Something went wrong. Please try again later.</p>';
        }
    }

    /**
     * Get the first parameter type from a closure.
     *
     * @param \Closure $closure
     * @return string|null
     * @throws \ReflectionException
     */
    protected function getFirstClosureParameterType(Closure $closure): ?string
    {
        $reflection = new \ReflectionFunction($closure);
        $parameters = $reflection->getParameters();

        if (count($parameters) > 0) {
            $type = $parameters[0]->getType();
            if ($type instanceof \ReflectionNamedType) {
                return $type->getName();
            }
        }

        return null;
    }

    /**
     * Handle framework-specific exceptions.
     *
     * @param  \Throwable  $e
     * @param  mixed  $request
     * @return mixed|null
     */
    protected function handleFrameworkExceptions(Throwable $e, $request)
    {
        // Handle ModelNotFoundException
        if ($e instanceof ModelNotFoundException) {
            return $this->handleModelNotFound($e, $request);
        }

        // Handle RecordsNotFoundException
        if ($e instanceof RecordsNotFoundException) {
            return $this->handleRecordsNotFound($e, $request);
        }

        // Handle CodeIgniter 3 default exceptions
        if ($this->isCodeIgniterException($e)) {
            return $this->handleCodeIgniterException($e, $request);
        }

        return null;
    }

    /**
     * Check if the exception is a CodeIgniter exception.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function isCodeIgniterException(Throwable $e): bool
    {
        // Check for common CodeIgniter error patterns
        $message = $e->getMessage();

        return strpos($message, 'Unable to locate the model') !== false ||
               strpos($message, 'Unable to load the requested class') !== false ||
               strpos($message, 'The configuration file') !== false ||
               strpos($message, 'Unable to connect to your database server') !== false ||
               strpos($message, '404 Page Not Found') !== false ||
               strpos($message, 'The page you requested was not found') !== false ||
               (class_exists('CI_DB_Exception') && $e instanceof \CI_DB_Exception) ||
               ($e instanceof \Exception && strpos($e->getFile(), 'system/core/') !== false);
    }

    /**
     * Handle CodeIgniter-specific exceptions.
     *
     * @param  \Throwable  $e
     * @param  mixed  $request
     * @return mixed
     */
    protected function handleCodeIgniterException(Throwable $e, $request)
    {
        $message = $e->getMessage();
        $statusCode = 500;

        // Determine appropriate status code based on error type
        if (strpos($message, 'Unable to locate') !== false ||
            strpos($message, 'Unable to load') !== false ||
            strpos($message, '404 Page Not Found') !== false ||
            strpos($message, 'The page you requested was not found') !== false) {
            $statusCode = 404;
        }

        if ($request && method_exists($request, 'expectsJson') && $request->expectsJson()) {
            return $this->createJsonResponse([
                'message' => $this->shouldDisplayDebugInfo() ? $message : 'Application error occurred.'
            ], $statusCode);
        }

        $view = "errors.{$statusCode}";
        $content = function_exists('view') && view()->exists($view)
            ? view($view, ['exception' => $e])->render()
            : $this->renderExceptionAsGenericHTML($e);

        return $this->createResponse($content, $statusCode);
    }

    /**
     * Handle model didn't find exceptions.
     *
     * @param  ModelNotFoundException  $e
     * @param  mixed  $request
     * @return mixed
     */
    protected function handleModelNotFound($e, $request)
    {
        if ($request && method_exists($request, 'expectsJson') && $request->expectsJson()) {
            return $this->createJsonResponse([
                'message' => 'Resource not found.'
            ], 404);
        }

        $content = function_exists('view') && view()->exists('errors.404')
            ? view('errors.404')->render()
            : $this->renderExceptionAsGenericHTML($e);

        return $this->createResponse($content, 404);
    }

    /**
     * Handle records not found exceptions.
     *
     * @param  RecordsNotFoundException  $e
     * @param  mixed  $request
     * @return mixed
     */
    protected function handleRecordsNotFound($e, $request)
    {
        if ($request && method_exists($request, 'expectsJson') && $request->expectsJson()) {
            return $this->createJsonResponse([
                'message' => 'No records found.'
            ], 404);
        }

        $content = function_exists('view') && view()->exists('errors.404')
            ? view('errors.404', ['message' => 'No records found.'])->render()
            : $this->renderExceptionAsGenericHTML($e);

        return $this->createResponse($content, 404);
    }
}
