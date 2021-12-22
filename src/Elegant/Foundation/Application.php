<?php

namespace Elegant\Foundation;

use Dotenv\Dotenv;
use Elegant\Container\Container;
use Elegant\Contracts\Container\BindingResolutionException;
use Elegant\Filesystem\Filesystem;
use Elegant\Support\Env;
use Elegant\Contracts\Foundation\Application as ApplicationContract;

class Application extends Container implements ApplicationContract
{
    /**
     * The Laraigniter framework version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

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
     * @throws BindingResolutionException
     */
    public function __construct($basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings();
        $this->registerCoreContainerAliases();

        $this->createDotenv();
    }

    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version()
    {
        return static::VERSION;
    }

    /**
     * Register the basic bindings into the container.
     *
     * @return void
     * @throws BindingResolutionException
     */
    protected function registerBaseBindings()
    {
        static::setInstance($this);

        $this->instance('app', $this);

        $this->instance(Container::class, $this);

        $this->instance(PackageManifest::class, new PackageManifest(
            new Filesystem, $this->basePath(), $this->getCachedPackagesPath()
        ));
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

    /**
     * Register the core class aliases in the container.
     *
     * @return void
     */
    public function registerCoreContainerAliases()
    {
        foreach ([
                     'app'                  => [self::class, \Elegant\Contracts\Container\Container::class, \Elegant\Contracts\Foundation\Application::class, \Psr\Container\ContainerInterface::class],
                     'blade.compiler'       => [\Elegant\View\Compilers\BladeCompiler::class],
                     'files'                => [\Elegant\Filesystem\Filesystem::class],
                     'view'                 => [\Elegant\View\Factory::class, \Elegant\Contracts\View\Factory::class],
                 ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }
}
