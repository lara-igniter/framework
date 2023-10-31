<?php

namespace Elegant\Foundation\Exceptions;

use Elegant\Filesystem\Filesystem;
use Elegant\Support\Arr;
use Whoops\Handler\PrettyPageHandler;

class WhoopsHandler
{
    /**
     * Create a new Whoops handler for debug mode.
     *
     * @return \Whoops\Handler\PrettyPageHandler
     */
    public function forDebug(): PrettyPageHandler
    {
        return tap(new PrettyPageHandler, function ($handler) {
            $handler->handleUnconditionally(true);

            $this->registerApplicationPaths($handler)
                ->registerBlacklist($handler)
                ->registerEditor($handler);
        });
    }

    /**
     * Register the application paths with the handler.
     *
     * @param \Whoops\Handler\PrettyPageHandler $handler
     * @return $this
     */
    protected function registerApplicationPaths(PrettyPageHandler $handler): WhoopsHandler
    {
        $handler->setApplicationPaths(
            array_flip($this->directoriesExceptVendor())
        );

        return $this;
    }

    /**
     * Get the application paths except for the "vendor" directory.
     *
     * @return array
     */
    protected function directoriesExceptVendor(): array
    {
        return Arr::except(
            array_flip((new Filesystem)->directories(base_path())),
            [base_path('vendor')]
        );
    }

    /**
     * Register the blacklist with the handler.
     *
     * @param \Whoops\Handler\PrettyPageHandler $handler
     * @return $this
     */
    protected function registerBlacklist(PrettyPageHandler $handler): WhoopsHandler
    {
        foreach (config_item('debug_blacklist') as $key => $secrets) {
            foreach ($secrets as $secret) {
                $handler->blacklist($key, $secret);
            }
        }

        return $this;
    }

    /**
     * Register the editor with the handler.
     *
     * @param \Whoops\Handler\PrettyPageHandler $handler
     * @return $this
     */
    protected function registerEditor(PrettyPageHandler $handler): WhoopsHandler
    {
        if ($editor = config_item('editor')) {
            if($editor === 'phpstorm') {
                $handler->setEditor(function ($file, $line) {
                    // IntelliJ platform requires that you send an Ajax request
                    return [
                        'label' => 'PHPStorm Remote',
                        'url' => "http://localhost:63342/api/file/$file:$line",
                        'ajax' => true
                    ];
                });
            } else {
                $handler->setEditor($editor);
            }
        }

        return $this;
    }
}