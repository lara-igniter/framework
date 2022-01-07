<?php

namespace Elegant\Foundation\Hooks;

use Elegant\Foundation\AliasLoader;
use Elegant\Foundation\Hooks\Contracts\CacheOverrideHookInterface;
use Elegant\Foundation\Hooks\Contracts\DisplayOverrideHookInterface;
use Elegant\Foundation\Hooks\Contracts\PostControllerConstructorHookInterface;
use Elegant\Foundation\Hooks\Contracts\PostControllerHookInterface;
use Elegant\Foundation\Hooks\Contracts\PostSystemHookInterface;
use Elegant\Foundation\Hooks\Contracts\PreControllerHookInterface;
use Elegant\Foundation\Hooks\Contracts\PreSystemHookInterface;
use Elegant\Support\Facades\Facade;
use Exception;

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

        $hooks['cache_override'][] =  function () use ($config) {
            self::cacheOverrideHook($config);
        };

        $hooks['post_system'][] = function () use ($config) {
            self::postSystemHook($config);
        };

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
        if(array_key_exists('providers', $hooks)) {
            foreach($hooks['providers'] as $hook) {
                $hookInstance = new $hook();

                if(method_exists($hookInstance, 'preSystemHook')
                    && $hookInstance instanceof PreSystemHookInterface) {
                    $hookInstance->preSystemHook();
                }
            }
        }
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
        if(array_key_exists('providers', $hooks)) {
            foreach ($hooks['providers'] as $hook) {
                $hookInstance = new $hook();

                if (method_exists($hookInstance, 'preControllerHook')
                    && $hookInstance instanceof PreControllerHookInterface) {
                    $hookInstance->preControllerHook($params, $URI, $class, $method);
                }
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
        Facade::setFacadeApplication(app());

        // Comment for now!!
//        if(array_key_exists('aliases', $hooks)) {
//            AliasLoader::getInstance($hooks['aliases'])->register();
//        }

        if(array_key_exists('providers', $hooks)) {
            foreach ($hooks['providers'] as $hook) {
                $hookInstance = new $hook();

                if (method_exists($hookInstance, 'postControllerConstructorHook')
                    && $hookInstance instanceof PostControllerConstructorHookInterface) {
                    $hookInstance->postControllerConstructorHook($params);
                }
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
        if(array_key_exists('providers', $hooks)) {
            foreach ($hooks['providers'] as $hook) {
                $hookInstance = new $hook();

                if (method_exists($hookInstance, 'postControllerHook')
                    && $hookInstance instanceof PostControllerHookInterface) {
                    $hookInstance->postControllerHook();
                }
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
        if(array_key_exists('providers', $hooks)) {
            foreach ($hooks['providers'] as $hook) {
                $hookInstance = new $hook();

                if (method_exists($hookInstance, 'displayOverrideHook')
                    && $hookInstance instanceof DisplayOverrideHookInterface) {
                    $hookInstance->displayOverrideHook();
                }
            }
        }
    }

    /**
     * "cache_override" hook
     *
     * @param array $hooks
     *
     * @return void
     */
    private static function cacheOverrideHook($hooks)
    {
        if(array_key_exists('providers', $hooks)) {
            foreach ($hooks['providers'] as $hook) {
                $hookInstance = new $hook();

                if (method_exists($hookInstance, 'cacheOverrideHook')
                    && $hookInstance instanceof CacheOverrideHookInterface) {
                    $hookInstance->cacheOverrideHook();
                }
            }
        }
    }

    /**
     * "post_system" hook
     *
     * @param array $hooks
     *
     * @return void
     */
    private static function postSystemHook($hooks)
    {
        if(array_key_exists('providers', $hooks)) {
            foreach ($hooks['providers'] as $hook) {
                $hookInstance = new $hook();

                if (method_exists($hookInstance, 'postSystemHook')
                    && $hookInstance instanceof PostSystemHookInterface) {
                    $hookInstance->postSystemHook();
                }
            }
        }
    }
}
