<?php

namespace Elegant\Log;

class Logger extends \CI_Log
{
    /**
     * Filename of log (without extension).
     *
     * @var string
     */
    protected $_log_file;

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $config =& get_config();

        $this->_log_file = (isset($config['log_file']) && $config['log_file'] !== '')
            ? $config['log_file']
            : 'log-'.date('Y-m-d');
    }

    /**
     * @param string $level
     * @param string $msg
     * @return bool
     */
    public function write_log($level, $msg)
    {
        if ($this->_enabled === false) {
            return false;
        }

        $level = strtoupper($level);

        if ((! isset($this->_levels[$level]) || ($this->_levels[$level] > $this->_threshold))
            && ! isset($this->_threshold_array[$this->_levels[$level]])) {
            return false;
        }

        $filepath = $this->_log_path.$this->_log_file.'.'.$this->_file_ext;
        $message = '';

        if (! file_exists($filepath)) {
            $newfile = true;
            if ($this->_file_ext === 'php') {
                $message .= "<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>\n\n";
            }
        }

        if (! $fp = @fopen($filepath, 'ab')) {
            return false;
        }

        flock($fp, LOCK_EX);

        if (strpos($this->_date_fmt, 'u') !== false) {
            $microtime_full = microtime(true);
            $microtime_short = sprintf('%06d', ($microtime_full - floor($microtime_full)) * 1000000);
            $date = new \DateTime(date('Y-m-d H:i:s.'.$microtime_short, $microtime_full));
            $date = $date->format($this->_date_fmt);
        } else {
            $date = date($this->_date_fmt);
        }

        $message .= $this->_format_line($level, $date, $msg);

        for ($written = 0, $length = self::strlen($message); $written < $length; $written += $result) {
            if (($result = fwrite($fp, self::substr($message, $written))) === false) {
                break;
            }
        }

        flock($fp, LOCK_UN);
        fclose($fp);

        if (isset($newfile) && $newfile === true) {
            chmod($filepath, $this->_file_permissions);
        }

        return is_int($result);
    }
}
