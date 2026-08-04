<?php

namespace Elegant\Foundation;

use Elegant\Contracts\Foundation\Application as ApplicationContract;
use Elegant\Foundation\Bootstrap\LoadEnvironmentVariables;

class Application implements ApplicationContract
{
    /**
     * The Laraigniter framework version.
     *
     * @var string
     */
    const VERSION = '1.64.0';

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

        (new LoadEnvironmentVariables)->bootstrap($this);
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
     * @param string $basePath
     * @return $this
     */
    public function setBasePath(string $basePath): Application
    {
        $this->basePath = rtrim($basePath, '\/');

        return $this;
    }

    /**
     * Get the path to the environment file directory.
     *
     * @return string
     */
    public function environmentPath(): string
    {
        return $this->environmentPath ?: $this->basePath;
    }

    /**
     * Set the environment file to be loaded during bootstrapping.
     *
     * @param string $file
     * @return $this
     */
    public function loadEnvironmentFrom(string $file): Application
    {
        $this->environmentFile = $file;

        return $this;
    }

    /**
     * Get the environment file the application is using.
     *
     * @return string
     */
    public function environmentFile(): string
    {
        return $this->environmentFile ?: '.env';
    }
}
