<?php

use Elegant\Contracts\Support\Arrayable;
use Elegant\Support\Facades\Route;
use Elegant\Support\Facades\View;

if (! function_exists('resource_path')) {
    /**
     * Get the path to the resources folder.
     *
     * @param  string  $path
     * @return string
     */
    function resource_path($path = '')
    {
        return FCPATH . 'resources' . ($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

if (! function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param  string  $path
     * @return string
     */
    function storage_path($path = '')
    {
        return FCPATH . 'storage' . ($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

if (! function_exists('app_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param  string  $path
     * @return string
     */
    function app_path($path = '')
    {
        return APPPATH . ($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

if (!function_exists('route')) {
    /**
     * Gets a route URL by its name
     *
     * @param string $name Route name
     * @param array $params Route parameters
     *
     * @return string
     * @throws Exception
     */
    function route($name = null, $params = []): string
    {
        if ($name === null) {
            $route = Route::getCurrentRoute();
        } else {
            $route = Route::getByName($name);
        }

        return $route->buildUrl($params);
    }
}

if (!function_exists('route_exists')) {
    /**
     * Checks if a route exists
     *
     * @param string $name Route name
     *
     * @return bool
     */
    function route_exists(string $name): bool
    {
        return isset(Route::$compiled['names'][$name]);
    }
}

if (!function_exists('app')) {
    /**
     * Returns the framework singleton
     *
     * (Alias of &get_instance() CodeIgniter function)
     *
     * @return object
     */
    function &app(): object
    {
        return get_instance();
    }
}

if (!function_exists('trigger_404')) {
    /**
     * Triggers the custom Route 404 error page, with fallback to
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

if (!function_exists('route_redirect')) {
    /**
     * Redirects to a route URL by its name
     *
     * @param string $name Route name
     * @param array $params Route parameters
     * @param array $messages Array with flashdata messages
     *
     * @return void
     * @throws Exception
     */
    function route_redirect(string $name, array $params = [], array $messages = [])
    {
        if (!empty($messages) && is_array($messages)) {
            app()->load->library('session');

            foreach ($messages as $_name => $_value) {
                app()->session->set_flashdata($_name, $_value);
            }
        }

        redirect(route($name, $params));
    }
}

if (! function_exists('view')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string|null  $view
     * @param  Arrayable|array  $data
     * @return View|void
     */
    function view($view = null, $data = [])
    {
        $factory = app()->view;

        if (func_num_args() === 0) {
            return $factory;
        }

        echo $factory->make($view, $data);
    }
}
