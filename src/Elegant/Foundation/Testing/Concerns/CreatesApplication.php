<?php

namespace Elegant\Foundation\Testing\Concerns;

use Elegant\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Elegant\Foundation\Application
     */
    public function createApplication()
    {
        $this->registerTestingPolyfills();

        $basePath = $this->applicationBasePath();
        $previousCwd = getcwd();

        if (! defined('ENVIRONMENT')) {
            define('ENVIRONMENT', getenv('APP_ENV') ?: 'testing');
        }

        // bootstrap/app.php uses require '../vendor/autoload.php' (relative to CWD = public/)
        chdir($basePath . DIRECTORY_SEPARATOR . 'public');

        try {
            /** @var \Elegant\Foundation\Application $app */
            $app = require $basePath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
        } finally {
            if ($previousCwd) {
                chdir($previousCwd);
            } else {
                chdir($basePath);
            }
        }

        return $app;
    }

    /**
     * @return void
     */
    protected function registerTestingPolyfills()
    {
        if (! function_exists('str_contains')) {
            function str_contains($haystack, $needle)
            {
                return $needle === '' || strpos($haystack, $needle) !== false;
            }
        }
    }
}
