<?php

namespace Elegant\Foundation\Bootstrap;

use Dotenv\Dotenv;
use Elegant\Contracts\Foundation\Application;
use Elegant\Support\Env;

class LoadEnvironmentVariables
{
    /**
     * Bootstrap the given application.
     *
     * @param \Elegant\Contracts\Foundation\Application $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $this->checkForSpecificEnvironmentFile($app);

        $this->createDotenv($app)->safeLoad();
    }

    /**
     * Detect if a custom environment file matching the APP_ENV exists.
     *
     * @param \Elegant\Contracts\Foundation\Application $app
     * @return void
     */
    protected function checkForSpecificEnvironmentFile($app)
    {
        $environment = Env::get('APP_ENV');

        if (is_null($environment)) {
            return;
        }

        $this->setEnvironmentFilePath(
            $app, $app->environmentFile() . '.' . $environment
        );
    }

    /**
     * Load a custom environment file.
     *
     * @param \Elegant\Contracts\Foundation\Application $app
     * @param string $file
     * @return bool
     */
    protected function setEnvironmentFilePath($app, $file)
    {
        if (is_file($app->environmentPath() . '/' . $file)) {
            $app->loadEnvironmentFrom($file);

            return true;
        }

        return false;
    }

    /**
     * Create a Dotenv instance.
     *
     * @param \Elegant\Contracts\Foundation\Application $app
     * @return \Dotenv\Dotenv
     */
    protected function createDotenv(Application $app): Dotenv
    {
        return Dotenv::create(
            Env::getRepository(),
            $app->environmentPath(),
            $app->environmentFile()
        );
    }
}
