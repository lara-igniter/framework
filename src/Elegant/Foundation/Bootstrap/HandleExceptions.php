<?php

namespace Elegant\Foundation\Bootstrap;

use Elegant\Foundation\Application;
use Elegant\Foundation\Exceptions\Handler;

class HandleExceptions
{
    /**
     * @var \Elegant\Foundation\Application
     */
    protected $app;

    /**
     * @param \Elegant\Foundation\Application $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $this->app = $app;

        error_reporting(-1);

        set_error_handler('handleError');
        set_exception_handler('handleException');
        register_shutdown_function('handleShutdown');

        if (config_item('env') === 'testing') {
            ini_set('display_errors', 'Off');
        }
    }

    /**
     * @return \Elegant\Foundation\Exceptions\Handler
     */
    public static function handler()
    {
        return Handler::resolve();
    }
}
