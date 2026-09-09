<?php

namespace Elegant\Foundation;

use Closure;
use Elegant\Contracts\Foundation\Application as ApplicationContract;
use Elegant\Foundation\Bootstrap\LoadEnvironmentVariables;

class Application implements ApplicationContract
{
    /**
     * The Laraigniter framework version.
     *
     * @var string
     */
    const VERSION = '1.68.2';

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

    /**
     * The container's shared instances.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * The container's bindings.
     *
     * @var array<string, \Closure|string>
     */
    protected array $bindings = [];

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
     * Get the base path of the Laraigniter installation.
     *
     * @param string $path
     * @return string
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath.($path !== '' ? DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    /**
     * Register a shared binding in the container.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     * @return void
     */
    public function singleton($abstract, $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param string $abstract
     * @param mixed $instance
     * @return mixed
     */
    public function instance($abstract, $instance)
    {
        $this->instances[$abstract] = $instance;

        return $instance;
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @return mixed
     */
    public function make($abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract] ?? $abstract;

        if ($concrete instanceof Closure) {
            $object = $concrete($this);
        } elseif (is_object($concrete)) {
            $object = $concrete;
        } else {
            $object = new $concrete($this);
        }

        return $this->instances[$abstract] = $object;
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
