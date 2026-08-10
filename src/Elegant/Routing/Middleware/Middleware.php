<?php

namespace Elegant\Routing\Middleware;

use Elegant\Foundation\Http\Kernel as HttpKernel;
use Elegant\Routing\Contracts\MiddlewareInterface;

class Middleware
{
    /**
     * @var array
     */
    private static $loadedMiddleware = [];

    /**
     * Loads a middleware class
     *
     * @param mixed $middleware Middleware name
     *
     * @return void
     */
    public static function load($middleware)
    {
        if (isset(self::$loadedMiddleware[$middleware])) {
            return self::$loadedMiddleware[$middleware];
        }

        $target = app_path('Middlewares/' . $middleware . '.php');

        if (file_exists($target)) {
            require_once($target);

            $middlewareInstance = new $middleware();

            if (!$middlewareInstance instanceof MiddlewareInterface) {
                show_error('Your middleware MUST implement the "MiddlewareInterface" interface');
            }

            self::$loadedMiddleware[$middleware] = $middlewareInstance;

            return $middlewareInstance;
        }

        show_error('Unable to find <strong>' . $middleware . '.php</strong> in your application/middleware folder');
    }

    /**
     * Runs middleware
     *
     * @param string|callable|object|array $middleware
     * @param array $args
     *
     * @return void
     */
    final public function run($middleware, array $args = [])
    {
        $kernel = HttpKernel::getInstance();

        if ($kernel) {
            $kernel->applyPendingAuthentication();
        }

        if ($this->shouldSkip($middleware)) {
            return;
        }

        if (is_callable($middleware)) {
            call_user_func_array($middleware, $args);
        } elseif (is_object($middleware)) {
            if (!$middleware instanceof MiddlewareInterface) {
                if (method_exists($middleware, 'run')) {
                    show_error('Your "' . get_class($middleware) . '" middleware does not have a run() public method');
                }
            }

            $middleware->run(app('input'), $args);
        } elseif (is_array($middleware)) {
            foreach ($middleware as $run) {
                $this->run($run, $args);
            }
        } elseif (is_string($middleware)) {
            if (isset(\App\Kernel::$routeMiddleware[$middleware])) {
                $middleware = new \App\Kernel::$routeMiddleware[$middleware]();

                if (!$middleware instanceof MiddlewareInterface) {
                    if (method_exists($middleware, 'run')) {
                        show_error('Your "' . get_class($middleware) . '" middleware does not have a run() public method');
                    }
                }

                $middleware->run($args);
            } else {
                show_error('Route middleware {' . $middleware . '} does not exist in application\Kernel.php');
            }
        } else {
            $middlewareInstance = self::load($middleware);

            call_user_func([$middlewareInstance, 'run'], $args);
        }
    }

    /**
     * @param mixed $middleware
     * @return bool
     */
    protected function shouldSkip($middleware): bool
    {
        $kernel = HttpKernel::getInstance();

        if ($kernel && $kernel->shouldSkipAllMiddleware()) {
            return true;
        }

        $skipList = $kernel ? $kernel->middlewareToSkip() : [];

        if ($skipList === [] || $skipList === null) {
            return false;
        }

        if (is_object($middleware)) {
            $candidates = [get_class($middleware)];
        } elseif (is_string($middleware)) {
            $candidates = [$middleware];

            if (isset(\App\Kernel::$routeMiddleware[$middleware])) {
                $candidates[] = \App\Kernel::$routeMiddleware[$middleware];
            }
        } else {
            return false;
        }

        foreach ($skipList as $item) {
            foreach ($candidates as $candidate) {
                if ($item === $candidate || ltrim((string) $candidate, '\\') === ltrim((string) $item, '\\')) {
                    return true;
                }

                if (is_string($candidate) && class_exists($candidate) && class_exists($item) && is_a($candidate, $item, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Binds a middleware to CodeIgniter hook at runtime
     *
     * @param string $hook Hook name
     * @param callable $middleware Middleware callable
     * @param array[] ...$args Middleware args
     *
     * @return void
     */
    final public function addHook($hook, $middleware, ...$args)
    {
        if (is_callable($middleware)) {
            if (empty($args)) {
                $args[] =& get_instance();
            }

            if (isset(app('hooks')->hooks[$hook]) && !is_array(app('hooks')->hooks[$hook])) {
                $_hook = app('hooks')->hooks[$hook];
                app('hooks')->hooks[$hook] = [$_hook];
            }

            app('hooks')->hooks[$hook][] = call_user_func_array($middleware, $args);
        } else {
            app('hooks')->hooks[$hook][] = call_user_func_array([$this, 'run'], [$middleware, $args]);
        }
    }
}
