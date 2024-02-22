<?php

use Elegant\Support\Facades\Date;

if (!function_exists('app')) {
    /**
     * Returns the framework singleton
     *
     * (Alias of framework controller instance)
     *
     * @param null $abstract
     * @return object
     */
    function &app($abstract = null): object
    {
        if (is_null($abstract)) {
            return get_instance();
        }

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
     * @param  string  $path
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
        return '<input type="hidden" name="' . csrf_name() . '" value="' . csrf_token() . '">';
    }
}

if (!function_exists('csrf_name')) {
    /**
     * Get the CSRF token name.
     *
     * @return string
     */
    function csrf_name(): string
    {
        return app('security')->get_csrf_token_name();
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the CSRF token value.
     *
     * @return string
     */
    function csrf_token(): string
    {
        return app('security')->get_csrf_hash();
    }
}

if (!function_exists('database_path')) {
    /**
     * Get the database path.
     *
     * @param  string  $path
     * @return string
     */
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}

if (! function_exists('fake') && class_exists(\Faker\Factory::class)) {
    /**
     * Get a faker instance.
     *
     * @param  string|null  $locale
     * @return \Faker\Generator
     */
    function fake(string $locale = null): \Faker\Generator
    {
        if (app('config')) {
            $locale ??= app('config')->config['faker_locale'];
        }

        $locale ??= 'en_US';

        return app()->faker = \Faker\Factory::create($locale);
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
    function logger(string $message = null)
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

        $manifestPath = public_path($manifestDirectory . '/mix-manifest.json');

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

        return asset($manifestDirectory . $manifest[$path]);
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
            return set_value($field);
        } else {
            return set_value($field, $data_value);
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
     * @param  mixed  $add  (default: '') can be string or array
     * @param  mixed  $remove  (default: '') can be string or array
     * @param bool $includeCurrent  (default: true)
     * @return string
     */
    function query_string($add = '', $remove = '', bool $includeCurrent = true): string
    {
        // set initial query string
        $queryString = [];

        if ($includeCurrent && request()->get() !== false) {
            $queryString = (array) request()->get();
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
            $return = '?'.http_build_query($queryString);
        }

        return $return;
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
    function session(string $key = null)
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

if (!function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param string|null $key
     * @param array $replace
     * @return string|null
     */
    function __(string $key = null, array $replace = [])
    {
        if (is_null($key)) {
            return $key;
        }

        return trans($key, $replace);
    }
}

if (!function_exists('view')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param string|null $view
     * @param array $data
     * @return \Elegant\Support\Facades\View|\Elegant\View\View
     */
    function view(string $view = null, array $data = [])
    {
        $factory = app('view');

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($view, $data);
    }
}