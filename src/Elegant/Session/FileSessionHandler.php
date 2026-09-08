<?php

namespace Elegant\Session;

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
    public function __construct($params) {
        parent::__construct($params);
    }

    public function gc($maxlifetime)
    {
        if ( ! is_dir($this->_config['save_path']) OR ($directory = opendir($this->_config['save_path'])) === FALSE)
        {
            log_message('debug', "Session: Garbage collector couldn't list files under directory '".$this->_config['save_path']."'.");
            return $this->_failure;
        }

        // TODO: Add pattern to the files based on cookie name and ip
//        $pattern = ($this->_config['match_ip'] === TRUE)
//            ? '[0-9a-f]{32}'
//            : '';
//
//        $pattern = sprintf(
//            '#\A%s'.$pattern.$this->_sid_regexp.'\z#',
//            preg_quote($this->_config['cookie_name'])
//        );

        $dir = new \DirectoryIterator($this->_config['save_path']);

        foreach ($dir as $file) {
            if ($file->isFile()) {
                if($file->getExtension() === 'gitignore') {
                    continue;
                }

                if ($file->getMTime() > $maxlifetime) {
                    continue;
                }

                @unlink($file->getPathname());
            }
        }

        return $this->_success;
    }
}
