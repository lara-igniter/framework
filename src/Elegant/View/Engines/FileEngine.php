<?php

namespace Elegant\View\Engines;

use Elegant\Contracts\View\Engine;
use Elegant\Filesystem\Filesystem;

class FileEngine implements Engine
{
    /**
     * The filesystem instance.
     *
     * @var \Elegant\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new file engine instance.
     *
     * @param  \Elegant\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @param  string  $path
     * @param  array   $data
     * @return string
     */
    public function get($path, array $data = [])
    {
        return $this->files->get($path);
    }
}
