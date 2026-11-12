<?php

namespace Elegant\Support;

use Closure;
use InvalidArgumentException;

abstract class Manager
{
    /**
     * The container instance.
     *
     * @var object|null
     */
    protected ?object $container;

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected array $customCreators = [];

    /**
     * The array of created drivers.
     *
     * @var array
     */
    protected array $drivers = [];

    /**
     * Create a new manager instance.
     *
     * @param object|null $container
     * @return void
     */
    public function __construct(object $container = null)
    {
        $this->container = $container;
    }

    /**
     * Get the default driver name.
     *
     * @return string|null
     */
    abstract public function getDefaultDriver(): ?string;

    /**
     * Get a driver instance.
     *
     * @param string|null $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function driver(string $driver = null)
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (is_null($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].', static::class
            ));
        }

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver(string $driver)
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create'.Str::studly($driver).'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param string $driver
     * @return mixed
     */
    protected function callCustomCreator(string $driver)
    {
        return $this->customCreators[$driver]($this->container);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend(string $driver, Closure $callback): Manager
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all of the created drivers.
     *
     * @return array
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Get the container instance used by the manager.
     *
     * @return object|null
     */
    public function getContainer(): ?object
    {
        return $this->container;
    }

    /**
     * Set the container instance used by the manager.
     *
     * @param object|null $container
     * @return $this
     */
    public function setContainer(?object $container): Manager
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Forget all of the resolved driver instances.
     *
     * @return $this
     */
    public function forgetDrivers(): Manager
    {
        $this->drivers = [];

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }

    /**
     * Read a configuration value through the framework helper.
     *
     * @param string $key
     * @param  mixed  $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        if (!function_exists('config')) {
            return $default;
        }

        $value = config($key);

        return $value === null || $value === '' ? $default : $value;
    }
}
