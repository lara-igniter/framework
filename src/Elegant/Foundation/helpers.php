<?php

use Elegant\Support\Facades\Route;

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
