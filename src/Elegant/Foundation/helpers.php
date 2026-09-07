<?php

use Elegant\Database\Model\Factories\Factory;
use Elegant\Http\RedirectResponse;
use Elegant\Routing\UrlGenerator;
use Elegant\Support\Facades\Date;
use Elegant\Support\Facades\Route;

if (!function_exists('app')) {
    /**
     * Returns the framework singleton
     *
     * (Alias of framework controller instance)
     *
     * @param $abstract
     * @param $instance
     * @return mixed
     */
    function &app($abstract = null, $instance = null)
    {
        /**
         * Return current controller instance without pass arguments.
         * Calling with app()
         */
        if (is_null($abstract)) {
            return get_instance();
        }

        /**
         * Initial controller instance with new class.
         * Calling with app('view', \Elegant\View\Factory)
         */
        if (!is_null($instance)) {
            get_instance()->{$abstract} = $instance;
        }

        /**
         * Get controller instance with class argument.
         * Calling with app(\Elegant\View\Factory)
         */
        if (class_exists($abstract)) {
            $reflection = new ReflectionObject(get_instance());
            $properties = $reflection->getProperties();

            foreach ($properties as $property) {
                $property->setAccessible(true);

                $value = $property->getValue(get_instance());
                $key = $property->getName();

                if ($value instanceof $abstract) {
                    return get_instance()->{$key};
                }
            }

            if (is_subclass_of($abstract, 'Elegant\View\Component')) {
                // ...array_values() is for error "Cannot unpack array with string keys"
                $component = new $abstract(...array_values($instance ?: []));

                return $component;
            }
        }

        /**
         * Get controller instance with string argument.
         * Calling with app('view')
         */
        return get_instance()->{$abstract};
    }
}

if (!function_exists('app_path')) {
    /**
     * Get the path to the application folder.
     *
     * @param string $path
     * @return string
     */
    function app_path(string $path = ''): string
    {
        return base_path('app' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset path for the application.
     *
     * @param string $path
     * @return string
     */
    function asset(string $path): string
    {
        return base_url($path);
    }
}

if (!function_exists('auth')) {
    /**
     *  Get the available auth instance.
     *
     * @return mixed
     */
    function auth()
    {
        return app('session')->userdata('logged_user');
    }
}

if (!function_exists('back')) {
    /**
     * Create a new redirect response to the previous location.
     *
     * @param int $status
     * @param array $headers
     * @param mixed $fallback
     * @return \Elegant\Http\RedirectResponse
     */
    function back(int $status = 302, array $headers = [], $fallback = false): RedirectResponse
    {
        return app('redirect')->back($status, $headers, $fallback);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param string $path
     *
     * @return string
     */
    function base_path(string $path = ''): string
    {
        return FCPATH . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('bcrypt')) {
    /**
     * Hash string with default ion_auth hashing method.
     *
     * @param string $password
     * @return mixed
     */
    function bcrypt(string $password)
    {
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12
        ]);
    }
}

if (!function_exists('ci')) {
    /**
     * @return object
     * @deprecated Use app() instead
     *
     */
    function &ci()
    {
        return get_instance();
    }
}

if (!function_exists('config')) {
    /**
     * Get the specified configuration value.
     *
     * @param string $field
     * @return string|array
     */
    function config(string $field)
    {
        if (empty($field)) {
            return '';
        }

        $load_path = explode('.', $field);

        if (isset($load_path[0])) {
            app('config')->load($load_path[0], true);
        }

        if (isset($load_path[3])) {
            $config = app('config')->item($load_path[1], $load_path[0]);

            return $config[$load_path[2]][$load_path[3]];
        }

        if (isset($load_path[2])) {
            $config = app('config')->item($load_path[1], $load_path[0]);

            return $config[$load_path[2]];
        }

        if (isset($load_path[1])) {
            return app('config')->item($load_path[1], $load_path[0]);
        }

        return '';
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the configuration path.
     *
     * @param string $path
     * @return string
     */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}

if (!function_exists('core_path')) {
    /**
     * Get the path to the system.
     *
     * @param string $path
     *
     * @return string
     */
    function core_path(string $path = ''): string
    {
        return app_path('core' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a CSRF token form field.
     *
     * @return string
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the CSRF token value.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    function csrf_token(): string
    {
        $session = app('session');

        if (isset($session)) {
            return $session->token() ?? '';
        }

        throw new RuntimeException('Application session not set.');
    }
}

if (!function_exists('database_path')) {
    /**
     * Get the database path.
     *
     * @param string $path
     * @return string
     */
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}

if (! function_exists('dispatch')) {
    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param mixed $job
     * @return mixed
     * @throws ReflectionException
     */
    function dispatch($job)
    {
        return (new \Elegant\Bus\Dispatcher())->dispatch($job);
    }
}

if (! function_exists('dispatch_sync')) {
    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param mixed $job
     * @return mixed
     * @throws Exception
     */
    function dispatch_sync($job)
    {
        return (new \Elegant\Bus\Dispatcher())->dispatchSync($job);
    }
}

if (! function_exists('dispatch_now')) {
    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @param  mixed  $job
     * @return mixed
     */
    function dispatch_now($job)
    {
        return (new \Elegant\Bus\Dispatcher())->dispatchNow($job);
    }
}

if (!function_exists('factory')) {
    /**
     * Get factory class and make a model object.
     *
     * @param string $abstract
     * @param int|null $count
     *
     * @return \Elegant\Database\Model\Factories\Factory
     */
    function factory(string $abstract, ?int $count = null): Factory
    {
        return (new $abstract($count));
    }
}

if (!function_exists('fake') && class_exists(\Faker\Factory::class)) {
    /**
     * Get a faker instance.
     *
     * @param string|null $locale
     * @return \Faker\Generator
     */
    function fake(?string $locale = null): \Faker\Generator
    {
        if (app('config')) {
            $locale ??= app('config')->config['faker_locale'];
        }

        $locale ??= 'en_US';

        return app('faker', \Faker\Factory::create($locale));
    }
}

if (!function_exists('info')) {
    /**
     * Write some information to the log.
     *
     * @param string $message
     * @return void
     */
    function info(string $message)
    {
        log_message('info', $message);
    }
}

if (!function_exists('logger')) {
    /**
     * Log a debug message to the logs.
     *
     * @param string|null $message
     * @return array|void
     */
    function logger(?string $message = null)
    {
        if (is_null($message)) {
            return app('log');
        }

        log_message('debug', $message);
    }
}

if (!function_exists('lang_path')) {
    /**
     * Get the path to the language folder.
     *
     * @param string $path
     * @return string
     */
    function lang_path(string $path = ''): string
    {
        return base_path('lang' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}

if (!function_exists('mix')) {
    /**
     * Get the path to a versioned Mix file.
     *
     * @param string $path
     * @param string $manifestDirectory
     * @return string
     *
     * @throws Exception
     */
    function mix(string $path, string $manifestDirectory = ''): string
    {
        static $manifests = [];

        if (!str_starts_with($path, '/')) {
            $path = "/{$path}";
        }

        if ($manifestDirectory && !str_starts_with($manifestDirectory, '/')) {
            $manifestDirectory = "/{$manifestDirectory}";
        }

        $manifestPath = public_path($manifestDirectory . '/build/mix-manifest.json');

        if (!isset($manifests[$manifestPath])) {
            if (!is_file($manifestPath)) {
                throw new Exception('The Mix manifest does not exist.');
            }

            $manifests[$manifestPath] = json_decode(file_get_contents($manifestPath), true);
        }

        $manifest = $manifests[$manifestPath];

        if (!isset($manifest[$path])) {
            $exception = new Exception("Unable to locate Mix file: {$path}.");

            if (!config_item('debug')) {
                log_message('error', $exception);

                return $path;
            } else {
                throw $exception;
            }
        }

        return asset($manifestDirectory . 'build' . $manifest[$path]);
    }
}

if (!function_exists('now')) {
    /**
     * Create a new Carbon instance for the current time.
     *
     * @param \DateTimeZone|string|null $tz
     * @return \Elegant\Support\Carbon
     */
    function now($tz = null)
    {
        $tz ??= config_item('timezone');

        return Date::now($tz);
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve an old input item.
     *
     * @param string $field
     * @param null $data_value
     * @return array|string
     */
    function old(string $field, $data_value = null)
    {
        if (is_null($data_value)) {
            return set_value($field, '', false);
        } else {
            return set_value($field, $data_value, false);
        }
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     *
     * @param string $path
     * @return string
     */
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}

if (!function_exists('query_string')) {
    /**
     * Returns query string with added or removed key/value pairs.
     *
     * @param mixed $add (default: '') can be string or array
     * @param mixed $remove (default: '') can be string or array
     * @param bool $includeCurrent (default: true)
     * @return string
     */
    function query_string($add = '', $remove = '', bool $includeCurrent = true): string
    {
        // set initial query string
        $queryString = [];

        if ($includeCurrent && request()->get() !== false) {
            $queryString = (array)request()->get();
        }

        // add to query string
        if ($add != '') {
            // convert to array
            if (is_string($add)) {
                $add = array($add);
            }

            $queryString = array_merge($queryString, $add);
        }

        // remove from query string
        if ($remove != '') {
            // convert to array
            if (is_string($remove)) {
                $remove = array($remove);
            }

            // remove from query_string
            foreach ($remove as $rm) {
                $key = in_array($rm, array_keys($queryString));
                if ($key !== false) {
                    \Elegant\Support\Arr::forget($queryString, $rm);
                }
            }
        }

        // return result
        $return = '';
        if (count($queryString) > 0) {
            $return = '?' . http_build_query($queryString);
        }

        return $return;
    }
}

if (!function_exists('redirect')) {
    /**
     * Get an instance of the redirector.
     *
     * @param string|null $to
     * @param int $status
     * @param array $headers
     * @param bool|null $secure
     * @return \Elegant\Routing\Redirector|\Elegant\Http\RedirectResponse
     */
    function redirector(?string $to = null, int $status = 302, array $headers = [], ?bool $secure = null)
    {
        if (is_null($to)) {
            return app('redirect');
        }

        return app('redirect')->to($to, $status, $headers, $secure);
    }
}

if (!function_exists('request')) {
    /**
     * Get an instance of the current request or an input item from the request.
     *
     * @param array|string|null $key
     * @param mixed $default
     * @return \CI_Input|string|array|null
     */
    function request($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('input');
        }

        if (is_array($key)) {
            return app('input')->only($key);
        }

        $value = app('input')->post($key);

        return is_null($value) ? value($default) : $value;
    }
}

if (!function_exists('resource_path')) {
    /**
     * Get the path to the resource's folder.
     *
     * @param string $path
     *
     * @return string
     */
    function resource_path(string $path = ''): string
    {
        return base_path('resources' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}

if (!function_exists('response')) {
    /**
     * Return a new response from the application.
     *
     * @param string|array|null $content
     * @param int $status
     * @param array $headers
     * @return \Elegant\Http\JsonResponse|\Elegant\Http\Response
     */
    function response($content = '', int $status = 200, array $headers = [])
    {
        if (empty($content)) {
            return app('response');
        }

        return app('response')->json($content, $status, $headers);
    }
}

if (!function_exists('route')) {
    /**
     * Generate the URL to a named route.
     *
     * @param array|string $name
     * @param mixed $parameters
     * @return string
     *
     * @throws \Elegant\Routing\Exceptions\RouteNotFoundException
     * @throws Exception
     */
    function route($name = null, $parameters = []): string
    {
        if ($name === null) {
            $route = Route::getCurrentRoute();
        } else {
            $route = Route::getByName($name);
        }

        return $route->buildUrl($parameters);
    }
}

if (!function_exists('route_exists')) {
    /**
     * Checks if a route exists
     *
     * @param string $name
     *
     * @return bool
     */
    function route_exists(string $name): bool
    {
        return isset(Route::$compiled['names'][$name]);
    }
}

if (!function_exists('route_redirect')) {
    /**
     * Redirects to a route URL by its name
     *
     * @deprecated Use the "redirector()" helper
     *
     * @param string $name Route name
     * @param array $params Route parameters
     * @param array $messages Array with flash data messages
     * @param string $query query string data pass
     * @param string $fragment fragment data pass
     *
     * @return void
     *
     * @throws \Elegant\Routing\Exceptions\RouteNotFoundException
     */
    function route_redirect(string $name, array $params = [], array $messages = [], string $query = '', string $fragment = '')
    {
        if (!empty($messages) && is_array($messages)) {
            app('load')->library('session');

            foreach ($messages as $_name => $_value) {
                app('session')->set_flashdata($_name, $_value);
            }
        }

        if ($fragment !== '') {
            $fragment = '#' . $fragment;

            $query = $query !== '' ? query_string('', $query) : query_string();

            redirect(route($name, $params) . $query . $fragment, 'refresh');
        }

        $query = $query !== '' ? query_string('', $query) : query_string();

        redirect(route($name, $params) . $query, 'refresh');
    }
}

if (!function_exists('routeIs')) {
    /**
     * Determine if the route name matches a given pattern.
     *
     * @param $patterns
     * @return bool
     */
    function routeIs($patterns): bool
    {
        if (is_array($patterns)) {
            foreach ($patterns as $pattern) {
                if (Route::named($pattern)) {
                    return true;
                }
            }
        }

        if (is_string($patterns)) {
            return Route::named($patterns);
        }

        return false;
    }
}

if (!function_exists('session')) {
    /**
     * Get the specified session value.
     *
     * @param string|null $key
     * @return array|string
     */
    function session(?string $key = null)
    {
        if (is_null($key)) {
            return app('session')->userdata();
        }

        return app('session')->{$key};
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param string $path
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}

if (!function_exists('system_path')) {
    /**
     * Get the path to the system.
     *
     * @param string $path
     *
     * @return string
     */
    function system_path(string $path = ''): string
    {
        return BASEPATH . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('to_route')) {
    /**
     * Create a new redirect response to a named route.
     *
     * @param string $route
     * @param array $parameters
     * @return void
     * @throws Exception
     */
    function to_route(string $route, array $parameters = [])
    {
        redirect(route($route, $parameters) . query_string(), 'refresh');
    }
}

if (!function_exists('today')) {
    /**
     * Create a new Carbon instance for the current date.
     *
     * @param \DateTimeZone|string|null $tz
     * @return \Elegant\Support\Carbon
     */
    function today($tz = null)
    {
        return Date::today($tz);
    }
}

if (!function_exists('trans')) {
    /**
     * Convert the given string to title case.
     *
     * @param string $key
     * @param array $replace
     * @return string
     */
    function trans(string $key, array $replace = [])
    {
        $explode = explode('.', $key);

        app('lang')->load($explode[0]);

        return empty($replace) ? lang($explode[1]) : lang($explode[1], $replace);
    }
}

if (!function_exists('trigger_404')) {
    /**
     * Triggers the custom error page, with fallback to
     * native show_404() function
     *
     * @return void
     */
    function trigger_404()
    {
        $_404 = Route::get404();

        if (is_null($_404) || !is_callable($_404)) {
            show_404();
        }

        call_user_func($_404);
        exit;
    }
}

if (!function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param string|null $key
     * @param array $replace
     * @return string|null
     */
    function __(?string $key = null, array $replace = [])
    {
        if (is_null($key)) {
            return $key;
        }

        return trans($key, $replace);
    }
}


if (! function_exists('url')) {
    /**
     * Generate a url for the application.
     *
     * @param  string|null  $path
     * @param  mixed  $parameters
     * @param  bool|null  $secure
     * @return \Elegant\Contracts\Routing\UrlGenerator|string
     */
    function url(?string $path = null, $parameters = [], ?bool $secure = null)
    {
        if (is_null($path)) {
            return app(UrlGenerator::class);
        }

        return app(UrlGenerator::class)->to($path, $parameters, $secure);
    }
}

if (!function_exists('view')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param string|null $view
     * @param \Elegant\Contracts\Support\Arrayable|array $data
     *
     * @return \Elegant\Contracts\View\View|\Elegant\Contracts\View\Factory|void
     */
    function view(?string $view = null, array $data = [])
    {
        $factory = app('view');

        if (func_num_args() === 0) {
            return $factory;
        }

        /**
         * Add return when fixed based controller
         * check return type if view instance
         * then echo response
         */
        echo $factory->make($view, $data);
    }
}
