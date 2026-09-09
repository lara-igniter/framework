<?php

use Elegant\Foundation\Exceptions\Handler;

/**
 * CodeIgniter compatibility: load core classes from Elegant modules
 * and read Laravel-style config from the project root.
 */

spl_autoload_register(static function ($class) {
    $aliases = [
        'MY_Model' => \Elegant\Database\Model\Model::class,
        'MY_Input' => \Elegant\Http\Request::class,
        'MY_Config' => \Elegant\Config\Repository::class,
        'MY_Loader' => \Elegant\Foundation\Loader::class,
        'MY_Router' => \Elegant\Routing\Router::class,
        'MY_Lang' => \Elegant\Translation\Translator::class,
        'MY_Hooks' => \Elegant\Foundation\Http\Hooks::class,
        'MY_Exceptions' => \Elegant\Foundation\Exceptions\Displayer::class,
        'MY_Log' => \Elegant\Log\Logger::class,
        'MY_Upload' => \Elegant\Http\File\Upload::class,
        'MY_Form_validation' => \Elegant\Validation\Validator::class,
        'MY_Session' => \Elegant\Session\Store::class,
        'MY_Session_files_driver' => \Elegant\Session\FileSessionHandler::class,
        'CI_DB_forge' => \Elegant\Database\Schema\Forge::class,
    ];

    if (! isset($aliases[$class])) {
        return;
    }

    $parents = [
        'MY_Input' => ['CI_Input', 'core/Input.php'],
        'MY_Config' => ['CI_Config', 'core/Config.php'],
        'MY_Loader' => ['CI_Loader', 'core/Loader.php'],
        'MY_Router' => ['CI_Router', 'core/Router.php'],
        'MY_Lang' => ['CI_Lang', 'core/Lang.php'],
        'MY_Hooks' => ['CI_Hooks', 'core/Hooks.php'],
        'MY_Exceptions' => ['CI_Exceptions', 'core/Exceptions.php'],
        'MY_Log' => ['CI_Log', 'core/Log.php'],
        'MY_Upload' => ['CI_Upload', 'libraries/Upload.php'],
        'MY_Form_validation' => ['CI_Form_validation', 'libraries/Form_validation.php'],
        'MY_Session' => ['CI_Session', 'libraries/Session/Session.php'],
        'MY_Session_files_driver' => ['CI_Session_files_driver', 'libraries/Session/drivers/Session_files_driver.php'],
    ];

    if (isset($parents[$class]) && defined('BASEPATH') && ! class_exists($parents[$class][0], false)) {
        require_once BASEPATH.$parents[$class][1];
    }

    $target = $aliases[$class];

    if (! class_exists($target, true)) {
        return;
    }

    if (! class_exists($class, false)) {
        class_alias($target, $class);
    }
}, true, true);

if (! function_exists('load_class')) {
    /**
     * Class registry.
     *
     * Loads CI parent classes from BASEPATH, then instantiates the Elegant
     * replacement when one exists. Application MY_ subclasses in APPPATH
     * remain supported for libraries that have not moved yet.
     *
     * @param string $class
     * @param string $directory
     * @param mixed $param
     * @return object
     */
    function &load_class($class, $directory = 'libraries', $param = null)
    {
        static $_classes = [];

        if (isset($_classes[$class])) {
            return $_classes[$class];
        }

        $coreMap = [
            'Config' => \Elegant\Config\Repository::class,
            'Hooks' => \Elegant\Foundation\Http\Hooks::class,
            'Router' => \Elegant\Routing\Router::class,
            'Input' => \Elegant\Http\Request::class,
            'Lang' => \Elegant\Translation\Translator::class,
            'Loader' => \Elegant\Foundation\Loader::class,
            'Exceptions' => \Elegant\Foundation\Exceptions\Displayer::class,
            'Log' => \Elegant\Log\Logger::class,
        ];

        $name = false;

        foreach ([APPPATH, BASEPATH] as $path) {
            if (file_exists($path.$directory.'/'.$class.'.php')) {
                $name = 'CI_'.$class;

                if (class_exists($name, false) === false) {
                    require_once $path.$directory.'/'.$class.'.php';
                }

                break;
            }
        }

        if (isset($coreMap[$class])) {
            $name = $coreMap[$class];
        } elseif (file_exists(APPPATH.$directory.'/'.config_item('subclass_prefix').$class.'.php')) {
            $name = config_item('subclass_prefix').$class;

            if (class_exists($name, false) === false) {
                require_once APPPATH.$directory.'/'.$name.'.php';
            }
        }

        if ($name === false) {
            set_status_header(503);
            echo 'Unable to locate the specified class: '.$class.'.php';
            exit(5);
        }

        is_loaded($class);

        $_classes[$class] = isset($param)
            ? new $name($param)
            : new $name();

        return $_classes[$class];
    }
}

if (! function_exists('get_config')) {
    /**
     * @param array $replace
     * @return array
     */
    function &get_config(array $replace = [])
    {
        static $config;

        if (empty($config)) {
            $file_path = base_path('config/config.php');
            $found = false;

            if (file_exists($file_path)) {
                $found = true;
                require $file_path;
            }

            if (file_exists($file_path = base_path('config/'.ENVIRONMENT.'/config.php'))) {
                require $file_path;
            }

            if (! isset($config) || ! is_array($config)) {
                set_status_header(503);
                echo 'Your config file does not appear to be formatted correctly.';
                exit(3);
            }
        }

        foreach ($replace as $key => $val) {
            $config[$key] = $val;
        }

        return $config;
    }
}

if (! function_exists('get_mimes')) {
    /**
     * @return array
     */
    function &get_mimes()
    {
        static $_mimes;

        if (empty($_mimes)) {
            $_mimes = file_exists(base_path('config/mimes.php'))
                ? include base_path('config/mimes.php')
                : [];

            if (file_exists(base_path('config/'.ENVIRONMENT.'/mimes.php'))) {
                $_mimes = array_merge($_mimes, include base_path('config/'.ENVIRONMENT.'/mimes.php'));
            }
        }

        return $_mimes;
    }
}

if (! function_exists('handleError')) {
    /**
     * @param int $level
     * @param string $message
     * @param string $file
     * @param int $line
     * @param array $context
     * @return bool
     */
    function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        return Handler::resolve()->handleError($level, $message, $file, $line);
    }
}

if (! function_exists('handleException')) {
    /**
     * @param \Throwable $exception
     * @return void
     */
    function handleException($exception)
    {
        Handler::resolve()->handleException($exception);
    }
}

if (! function_exists('handleShutdown')) {
    /**
     * @return void
     */
    function handleShutdown()
    {
        Handler::resolve()->handleFatalError();
    }
}

if (! function_exists('doctype')) {
    /**
     * @param string $type
     * @return string|false
     */
    function doctype($type = 'xhtml1-strict')
    {
        static $doctypes;

        if (! is_array($doctypes)) {
            if (file_exists(base_path('config/doctypes.php'))) {
                include base_path('config/doctypes.php');
            }

            if (file_exists(base_path('config/'.ENVIRONMENT.'/doctypes.php'))) {
                include base_path('config/'.ENVIRONMENT.'/doctypes.php');
            }

            if (empty($_doctypes) || ! is_array($_doctypes)) {
                $doctypes = [];

                return false;
            }

            $doctypes = $_doctypes;
        }

        return isset($doctypes[$type]) ? $doctypes[$type] : false;
    }
}

if (! function_exists('convert_accented_characters')) {
    /**
     * @param string $str
     * @return string
     */
    function convert_accented_characters($str)
    {
        static $array_from, $array_to;

        if (! is_array($array_from)) {
            if (file_exists(base_path('config/foreign_chars.php'))) {
                include base_path('config/foreign_chars.php');
            }

            if (file_exists(base_path('config/'.ENVIRONMENT.'/foreign_chars.php'))) {
                include base_path('config/'.ENVIRONMENT.'/foreign_chars.php');
            }

            if (empty($foreign_characters) || ! is_array($foreign_characters)) {
                $array_from = [];
                $array_to = [];

                return $str;
            }

            $array_from = array_keys($foreign_characters);
            $array_to = array_values($foreign_characters);
        }

        return preg_replace($array_from, $array_to, $str);
    }
}

if (! function_exists('_get_smiley_array')) {
    /**
     * @return array|false
     */
    function _get_smiley_array()
    {
        static $_smileys;

        if (! is_array($_smileys)) {
            if (file_exists(base_path('config/smileys.php'))) {
                include base_path('config/smileys.php');
            }

            if (file_exists(base_path('config/'.ENVIRONMENT.'/smileys.php'))) {
                include base_path('config/'.ENVIRONMENT.'/smileys.php');
            }

            if (empty($smileys) || ! is_array($smileys)) {
                $_smileys = [];

                return false;
            }

            $_smileys = $smileys;
        }

        return $_smileys;
    }
}

if (! function_exists('form_open')) {
    /**
     * @param string $action
     * @param array $attributes
     * @param array $hidden
     * @return string
     */
    function form_open($action = '', $attributes = [], $hidden = [])
    {
        $CI = &get_instance();

        if (! $action) {
            $action = $CI->config->site_url($CI->uri->uri_string());
        } elseif (strpos($action, '://') === false) {
            $action = $CI->config->site_url($action);
        }

        $attributes = _attributes_to_string($attributes);

        if (stripos($attributes, 'method=') === false) {
            $attributes .= ' method="post"';
        }

        if (stripos($attributes, 'accept-charset=') === false) {
            $attributes .= ' accept-charset="'.strtolower(config_item('charset')).'"';
        }

        $form = '<form action="'.$action.'"'.$attributes.">\n";

        if (is_array($hidden)) {
            foreach ($hidden as $name => $value) {
                $form .= '<input type="hidden" name="'.$name.'" value="'.html_escape($value).'" />'."\n";
            }
        }

        $token = app('session')->token();

        if (empty($token)) {
            $token = app('session')->userdata('_token') ?? app('session')->regenerateToken();
            $_SESSION['_token'] = $token;
        }

        if (is_string($token)) {
            $form .= '<input type="hidden" name="_token" value="'.$token.'" />'."\n";
        }

        return $form;
    }
}

if (! function_exists('lang')) {
    /**
     * @param string $line
     * @param array $replace
     * @return string|array|null
     */
    function lang($line, $replace = [])
    {
        $line = get_instance()->lang->line($line);

        if (is_string($line)) {
            if (empty($replace)) {
                return $line;
            }

            foreach ($replace as $key => $value) {
                $line = str_replace(
                    [':'.$key, ':'.strtoupper($key), ':'.ucfirst($key)],
                    [$value, strtoupper($value), ucfirst($value)],
                    $line
                );
            }

            return $line;
        }
    }
}

