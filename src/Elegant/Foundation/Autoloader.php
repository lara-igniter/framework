<?php

namespace Elegant\Foundation;

class Autoloader
{
    /**
     * @var string Nampsapce prefix refered to application root
     */
    const DEFAULT_PREFIX = "App";

    /**
     * Register Autoloader
     *
     * @param string|null $prefix PSR-4 namespace prefix
     */
    public static function register(string $prefix = null)
    {
        $prefix = ($prefix) ? (string)$prefix : self::DEFAULT_PREFIX;

        spl_autoload_register(function ($classname) use ($prefix) {
            if (strpos(strtolower($classname), "{$prefix}\\") === 0) {
                $classname = str_replace("{$prefix}\\", "", $classname);
                $filepath = APPPATH . str_replace('\\', DIRECTORY_SEPARATOR, ltrim($classname, '\\')) . '.php';

                if (file_exists($filepath)) {
                    require $filepath;
                }
            }
        });
    }
}
