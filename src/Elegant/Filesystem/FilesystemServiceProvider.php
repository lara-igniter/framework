<?php

namespace Elegant\Filesystem;

use Elegant\Contracts\Hook\PostControllerConstructor;

class FilesystemServiceProvider implements PostControllerConstructor
{
    public function postControllerConstructor(&$params)
    {
        app('config')->load('filesystems', TRUE);

        $this->registerNativeFilesystem();
        $this->registerFilesystem();
    }

    /**
     * Register the native filesystem implementation.
     *
     * @return void
     */
    protected function registerNativeFilesystem()
    {
        app('files', new Filesystem());
    }

    /**
     * Register the driver based filesystem.
     *
     * @return void
     */
    protected function registerFilesystem()
    {
        $this->registerManager();

        app('filesystem.disk', app('filesystem')->disk($this->getDefaultDriver()));

        app('filesystem.cloud', app('filesystem')->disk($this->getCloudDriver()));
    }

    /**
     * Register the filesystem manager.
     *
     * @return void
     */
    protected function registerManager()
    {
        app('filesystem', new FilesystemManager(app('config')));
    }

    /**
     * Get the default file driver.
     *
     * @return string
     */
    protected function getDefaultDriver(): string
    {
        return app('config')->config['filesystems']['default'];
    }

    /**
     * Get the default cloud based file driver.
     *
     * @return string
     */
    protected function getCloudDriver()
    {
        return app('config')->config['filesystems']['cloud'] ?? '';
    }
}
