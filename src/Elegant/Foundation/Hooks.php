<?php

namespace Elegant\Foundation;

use Elegant\Contracts\Hook\CacheOverride;
use Elegant\Contracts\Hook\DisplayOverride;
use Elegant\Contracts\Hook\PostControllerConstructor;
use Elegant\Contracts\Hook\PostController;
use Elegant\Contracts\Hook\PostSystem;
use Elegant\Contracts\Hook\PreController;
use Elegant\Contracts\Hook\PreSystem;
use Elegant\Support\Facades\Facade;
use Exception;

class Hooks
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

                if (method_exists($hookInstance, 'preSystem')
                    && $hookInstance instanceof PreSystem) {
                    $hookInstance->preSystem();
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

                if (method_exists($hookInstance, 'preController')
                    && $hookInstance instanceof PreController) {
                    $hookInstance->preController($params, $URI, $class, $method);
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

                if (method_exists($hookInstance, 'postControllerConstructor')
                    && $hookInstance instanceof PostControllerConstructor) {
                    $hookInstance->postControllerConstructor($params);
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

                if (method_exists($hookInstance, 'postController')
                    && $hookInstance instanceof PostController) {
                    $hookInstance->postController();
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

                if (method_exists($hookInstance, 'displayOverride')
                    && $hookInstance instanceof DisplayOverride) {
                    $hookInstance->displayOverride();
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

                if (method_exists($hookInstance, 'cacheOverride')
                    && $hookInstance instanceof CacheOverride) {
                    $hookInstance->cacheOverride();
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

                if (method_exists($hookInstance, 'postSystem')
                    && $hookInstance instanceof PostSystem) {
                    $hookInstance->postSystem();
                }
            }
        }
    }
}
