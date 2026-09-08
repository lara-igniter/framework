<?php

namespace Elegant\Http\File;

if (! class_exists('CI_Upload', false) && defined('BASEPATH')) {
    require_once BASEPATH.'libraries/Upload.php';
}

class Upload extends \CI_Upload
{
    /**
     * Ensure the upload directory exists and is writable.
     *
     * @return bool
     */
    public function validate_upload_path()
    {
        if ($this->upload_path === '') {
            $this->set_error('upload_no_filepath', 'error');

            return false;
        }

        if (realpath($this->upload_path) !== false) {
            $this->upload_path = str_replace('\\', '/', realpath($this->upload_path));
        }

        if (! is_dir($this->upload_path) && ! mkdir($this->upload_path, 0777, true)) {
            $this->set_error('upload_no_filepath', 'error');

            return false;
        }

        if (! is_really_writable($this->upload_path) && ! chmod($this->upload_path, 0777)) {
            $this->set_error('upload_not_writable', 'error');

            return false;
        }

        $this->upload_path = preg_replace('/(.+?)\/*$/', '\\1/', $this->upload_path);

        return true;
    }

    /**
     * @param  string  $open
     * @param  string  $close
     * @return string
     */
    public function display_errors($open = '<span>', $close = '</span>')
    {
        if ($this->error_msg === []) {
            return '';
        }

        return $open.implode($close.$open, $this->error_msg).$close;
    }
}
