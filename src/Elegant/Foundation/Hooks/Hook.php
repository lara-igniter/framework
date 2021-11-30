<?php

namespace Elegant\Foundation\Hooks;

use Elegant\Foundation\Hooks\Contracts\DisplayOverrideHookInterface;
use Elegant\Foundation\Hooks\Contracts\PostControllerConstructorHookInterface;
use Elegant\Foundation\Hooks\Contracts\PostControllerHookInterface;
use Elegant\Foundation\Hooks\Contracts\PreControllerHookInterface;
use Elegant\Foundation\Hooks\Contracts\PreSystemHookInterface;

class Hook
{
    /**
     * Gets the Routing hooks
     *
     * @param array|null $config Routing CI configuration
     *
     * @return array
     */
    public static function autoload($config = []): array
    {
        $hooks = [];

        $hooks['pre_system'][] = function () use ($config) {
            self::preSystemHook($config);
        };

        $hooks['pre_controller'][] = function () use ($config) {
            global $params, $URI, $class, $method;

            self::preControllerHook($config, $params, $URI, $class, $method);
        };

        $hooks['post_controller_constructor'][] = function () use ($config) {
            global $params;

            self::postControllerConstructorHook($config, $params);
        };

        $hooks['post_controller'][] = function () use ($config) {
            self::postControllerHook($config);
        };

        $hooks['display_override'][] = function () use ($config) {
            self::displayOverrideHook($config);
        };

//        $hooks['cache_override'][] = function () {
//            self::cacheOverrideHook();
//        };

//        $hooks['post_system'][] = function () {
//            self::postSystemHook();
//        };

        return $hooks;
    }

    /**
     * "pre_system" hook
     *
     * @param array $hooks
     *
     * @return void
     * @throws Exception
     */
    private static function preSystemHook($hooks)
    {
//        if(array_key_exists('whoops', $hooks)) {
            foreach($hooks as $hook) {
                $hookInstance = new $hook();

                if(method_exists($hookInstance, 'preSystemHook')
                    && $hookInstance instanceof PreSystemHookInterface) {
                    $hookInstance->preSystemHook();
                }
            }
//        }
    }

    /**
     * "pre_controller" hook
     *
     * @param array $hooks
     * @param array $params
     * @param string $URI
     * @param string $class
     * @param string $method
     *
     * @return void
     */
    private static function preControllerHook($hooks, &$params, &$URI, &$class, &$method)
    {
        foreach($hooks as $hook) {
            $hookInstance = new $hook();

            if(method_exists($hookInstance, 'preControllerHook')
                && $hookInstance instanceof PreControllerHookInterface) {
                $hookInstance->preControllerHook($params,$URI,$class,$method);
            }
        }
    }

    /**
     * "post_controller" hook
     *
     * @param array $hooks
     * @param array $params
     *
     * @return void
     */
    private static function postControllerConstructorHook($hooks, &$params)
    {
        foreach($hooks as $hook) {
            $hookInstance = new $hook();

            if(method_exists($hookInstance, 'postControllerConstructorHook')
                && $hookInstance instanceof PostControllerConstructorHookInterface) {
                $hookInstance->postControllerConstructorHook($params);
            }
        }
    }

    /**
     * "post_controller" hook
     *
     * @param array $hooks
     *
     * @return void
     */
    private static function postControllerHook($hooks)
    {
        foreach($hooks as $hook) {
            $hookInstance = new $hook();

            if(method_exists($hookInstance, 'postControllerHook')
                && $hookInstance instanceof PostControllerHookInterface) {
                $hookInstance->postControllerHook();
            }
        }
    }

    /**
     * "display_override" hook
     *
     * @param array $hooks
     *
     * @return void
     */
    private static function displayOverrideHook($hooks)
    {
        foreach($hooks as $hook) {
            $hookInstance = new $hook();

            if(method_exists($hookInstance, 'displayOverrideHook')
                && $hookInstance instanceof DisplayOverrideHookInterface) {
                $hookInstance->displayOverrideHook();
            }
        }
    }
}
