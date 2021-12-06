<?php

namespace Elegant\View;

use Elegant\Filesystem\Filesystem;
use Elegant\View\Compilers\BladeCompiler;
use Elegant\View\Engines\CompilerEngine;
use Elegant\View\Engines\EngineResolver;

class Blade
{
    protected $compiled;

    protected $paths = [];

    protected $file;

    protected $compiler;

    protected $resolver;

    protected static $factory;

    public function __construct()
    {
        app()->config->load('view', TRUE);

        $this->registerFileSystem();
        $this->registerBladeCompiler();
        $this->registerEngineResolver();
        $this->registerFactory();

    }

    /**
     * Register the File system implementation.
     *
     * @return void
     */
    public function registerFileSystem()
    {
        $this->file = new Filesystem();
    }

    /**
     * Register the Blade compiler implementation.
     *
     * @return void
     */
    public function registerBladeCompiler()
    {
        $this->compiled = app()->config->item('compiled', 'view');

        $this->compiler = new BladeCompiler($this->file, $this->compiled);
    }

    /**
     * Register the engine resolver instance.
     *
     * @return void
     */
    public function registerEngineResolver()
    {
        $compiler = $this->compiled;

        $resolver = new EngineResolver;

        $resolver->register('blade', function () use ($compiler) {
            return new CompilerEngine($compiler);
        });

        $this->resolver = $resolver;
    }

    /**
     * Register the view environment.
     *
     * @return void
     */
    public function registerFactory()
    {
        $this->paths = app()->config->item('paths', 'view');

        $factory = new Factory($this->resolver, new FileViewFinder($this->file, $this->paths));

        $factory->addExtension('tpl', 'blade');

        self::$factory = $factory;
    }

    public static function make($view, $data = [])
    {
        echo self::$factory->make($view, $data);
    }

    public static function exists($view)
    {
        return self::$factory->exists($view);
    }

    public function share($key, $value)
    {
        return self::$factory->share($key, $value);
    }

    public function render($path, $vars = [])
    {
        return self::$factory->make($path, $vars)->render();
    }
}
