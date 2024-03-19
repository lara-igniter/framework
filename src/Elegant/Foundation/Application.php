<?php

namespace Elegant\Foundation;

use Dotenv\Dotenv;
use Elegant\Support\Env;

class Application
{
    /**
     * The Laraigniter framework version.
     *
     * @var string
     */
    const VERSION = '1.21.4';

    /**
     * The base path for the Laraigniter installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * The custom environment path defined by the developer.
     *
     * @var string
     */
    protected $environmentPath;

    /**
     * The environment file to load during bootstrapping.
     *
     * @var string
     */
    protected $environmentFile = '.env';

    public function __construct($basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->createDotenv();
    }

    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * Set the base path for the application.
     *
     * @param  string  $basePath
     * @return $this
     */
    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        return $this;
    }

    public function environmentPath()
    {
        return $this->environmentPath ?: $this->basePath;
    }

    /**
     * Get the environment file the application is using.
     *
     * @return string
     */
    public function environmentFile()
    {
        return $this->environmentFile ?: '.env';
    }

    protected function createDotenv()
    {
        return Dotenv::create(
            Env::getRepository(),
            $this->environmentPath(),
            $this->environmentFile()
        )->load();
    }
}
