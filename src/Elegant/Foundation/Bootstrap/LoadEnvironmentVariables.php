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
     * @param  \Elegant\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $this->createDotenv($app)->load();
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
