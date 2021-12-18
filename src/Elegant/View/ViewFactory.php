<?php

namespace Elegant\View;

use Elegant\Filesystem\Filesystem;
use Elegant\View\Compilers\BladeCompiler;
use Elegant\View\Engines\CompilerEngine;
use Elegant\View\Engines\EngineResolver;

class ViewFactory
{
    protected $compiled;

    protected $paths = [];

    protected $file;

    public $compiler;

    protected $resolver;

    protected $factory;

    public function __construct()
    {
        app()->config->load('view', TRUE);

        $this->compiled = app()->config->item('compiled', 'view');

        $this->paths = app()->config->item('paths', 'view');

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
    protected function registerFileSystem()
    {
        $this->file = new Filesystem();
    }

    /**
     * Register the Blade compiler implementation.
     *
     * @return void
     */
    protected function registerBladeCompiler()
    {
        $this->compiler = new BladeCompiler($this->file, $this->compiled);
    }

    /**
     * Register the engine resolver instance.
     *
     * @return void
     */
    protected function registerEngineResolver()
    {
        $compiler = $this->compiler;

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
    protected function registerFactory()
    {
        $factory = new Factory($this->resolver, new FileViewFinder($this->file, $this->paths));

        $factory->addExtension('tpl', 'blade');

        $this->factory = $factory;
    }

    public function make($view, $data = [])
    {
        echo $this->factory->make($view, $data);
    }

    public function exists($view)
    {
        return $this->factory->exists($view);
    }

    public function share($key, $value)
    {
        return $this->factory->share($key, $value);
    }

    public function render($path, $vars = [])
    {
        return $this->factory->make($path, $vars)->render();
    }
}
