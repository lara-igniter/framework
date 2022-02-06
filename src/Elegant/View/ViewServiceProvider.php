<?php

namespace Elegant\View;

use Elegant\Contracts\Hook\PostControllerConstructor;
use Elegant\Contracts\Hook\PreSystem;
use Elegant\View\Compilers\BladeCompiler;
use Elegant\View\Engines\CompilerEngine;
use Elegant\View\Engines\EngineResolver;
use Elegant\View\Engines\FileEngine;
use Elegant\View\Engines\PhpEngine;

class ViewServiceProvider implements PreSystem, PostControllerConstructor
{
    public function preSystemHook()
    {
        if (!file_exists(APPPATH . '/config/view.php')) {
            copy(realpath(dirname(__DIR__) . './Resources/ConfigView.php'), APPPATH . '/config/view.php');
        }
    }

    public function postControllerConstructorHook(&$params)
    {
        app()->config->load('view', TRUE);

        $app['view.compiled'] = app()->config->item('compiled', 'view');

        $app['view.paths'] = app()->config->item('paths', 'view');

        $this->registerViewFinder($app);
        $this->registerEngineResolver($app);
        app()->view = $this->registerFactory();
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerViewFinder($app)
    {
        app()->{'view.finder'} = new FileViewFinder(app()->files, $app['view.paths']);
    }

    /**
     * Register the engine resolver instance.
     *
     * @return void
     */
    public function registerEngineResolver($app)
    {
        $resolver = new EngineResolver;

        app()->{'blade.compiler'} = new BladeCompiler(app()->files, $app['view.compiled']);

        foreach (['file', 'php', 'blade'] as $engine) {
            $this->{'register'.ucfirst($engine).'Engine'}($resolver);
        }

        app()->{'view.engine.resolver'} = $resolver;
    }

    /**
     * Register the file engine implementation.
     *
     * @param EngineResolver $resolver
     * @return void
     */
    public function registerFileEngine($resolver)
    {
        $resolver->register('file', function () {
            return new FileEngine;
        });
    }

    /**
     * Register the PHP engine implementation.
     *
     * @param EngineResolver $resolver
     * @return void
     */
    public function registerPhpEngine($resolver)
    {
        $resolver->register('php', function () {
            return new PhpEngine;
        });
    }

    /**
     * Register the Blade engine implementation.
     *
     * @param EngineResolver $resolver
     * @return void
     */
    public function registerBladeEngine($resolver)
    {
        $resolver->register('blade', function () {
            return new CompilerEngine(app()->{'blade.compiler'});
        });
    }

    /**
     * Register the view environment.
     *
     * @return Factory
     */
    public function registerFactory()
    {
        $resolver = app()->{'view.engine.resolver'};

        $finder = app()->{'view.finder'};

        $factory = $this->createFactory($resolver, $finder);

//        $factory->share('app', 'test_data');

        return $factory;
    }

    /**
     * Create a new Factory Instance.
     *
     * @param EngineResolver $resolver
     * @param ViewFinderInterface $finder
     * @return Factory
     */
    public function createFactory($resolver, $finder)
    {
        return new Factory($resolver, $finder);
    }
}
