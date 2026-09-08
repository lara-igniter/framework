<?php

namespace Elegant\Session;

use DirectoryIterator;

if (defined('BASEPATH')) {
    if (! class_exists('CI_Session_driver', false)) {
        require_once BASEPATH.'libraries/Session/Session_driver.php';
    }

    if (! class_exists('CI_Session_files_driver', false)) {
        require_once BASEPATH.'libraries/Session/drivers/Session_files_driver.php';
    }
}

class FileSessionHandler extends \CI_Session_files_driver
{
    /**
     * Garbage-collect expired session files.
     *
     * @param  int  $maxlifetime
     * @return bool|int
     */
    public function gc($maxlifetime)
    {
        $path = $this->_config['save_path'];

        if (! is_dir($path)) {
            log_message('debug', "Session: Garbage collector couldn't list files under directory '{$path}'.");

            return $this->_failure;
        }

        foreach (new DirectoryIterator($path) as $file) {
            if (! $file->isFile() || $file->getExtension() === 'gitignore') {
                continue;
            }

            if ($file->getMTime() > $maxlifetime) {
                continue;
            }

            @unlink($file->getPathname());
        }

        return $this->_success;
    }
}
