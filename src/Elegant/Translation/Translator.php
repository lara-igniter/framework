<?php

namespace Elegant\Translation;

use Exception;

class Translator extends \CI_Lang
{
    /**
     * List of translations
     *
     * @var    array
     */
    public $language = [];

    /**
     * List of loaded language files
     *
     * @var    array
     */
    public $is_loaded = [];

    /**
     * Cached language lines, grouped by locale and language file.
     *
     * @var array<string, array<string, string>>
     */
    protected array $loadedLanguageLines = [];

    /**
     * Fallback language
     *
     * @var    string
     */
    public $base_language = '';

    /**
     * Class constructor
     *
     * @return    void
     */
    public function __construct()
    {
        parent::__construct();

        log_message('info', 'Language Class Initialized');

        $this->base_language = config_item('fallback_locale');
    }

    // --------------------------------------------------------------------

    /**
     * Load a language file
     *
     * @param mixed $langfile Language file name
     * @param string $idiom Language name (english, etc.)
     * @param bool $return Whether to return the loaded array of translations
     * @param bool $add_suffix Whether to add suffix to $langfile
     * @param string $alt_path Alternative path to look for the language file
     *
     * @return    void|string[]    Array containing translations, if $return is set to TRUE
     * @throws \Exception
     */
    public function load($langfile, $idiom = '', $return = false, $add_suffix = true, $alt_path = '')
    {
        if (is_array($langfile)) {
            foreach ($langfile as $value) {
                $this->load($value, $idiom, $return, $add_suffix, $alt_path);
            }

            return;
        }

        $langfile = str_replace('.php', '', $langfile);

        if ($add_suffix === true) {
            $langfile = preg_replace('/_lang$/', '', $langfile) . '_lang';
        }

        $langfile .= '.php';

        if (empty($idiom) or !preg_match('/^[a-z_-]+$/i', $idiom)) {
            $config =& get_config();
            $idiom = empty($config['locale']) ? $this->base_language : $config['locale'];
        }

        $languageCacheKey = $idiom . '/' . $langfile;

        if ($return === false && isset($this->is_loaded[$langfile]) && $this->is_loaded[$langfile] === $idiom) {
            $this->language = array_merge(
                $this->language,
                $this->loadedLanguageLines[$languageCacheKey] ?? []
            );

            return;
        }

        // load the default language first, if necessary
        // only do this for the language files under system/
        // https://github.com/bcit-ci/codeigniter3-translations
        // $basepath = SYSDIR . '/language/' . $this->base_language . '/' . $langfile;
        // if (($found = file_exists($basepath)) === TRUE) {
        //    include($basepath);
        // }


        // Load the base file, so any others found can override it
        $basepath = base_path('lang/' . $idiom . '/' . $langfile);
        if (($found = file_exists($basepath)) === true) {
            include($basepath);
        }

        // Do we have an alternative path to look in?
        if ($alt_path !== '') {
            $alt_path .= 'language/' . $idiom . '/' . $langfile;
            if (file_exists($alt_path)) {
                include($alt_path);
                $found = true;
            }
        } else {
            foreach (get_instance()->load->get_package_paths(true) as $package_path) {
                $package_path .= 'language/' . $idiom . '/' . $langfile;
                if ($basepath !== $package_path && file_exists($package_path)) {
                    include($package_path);
                    $found = true;
                    break;
                }
            }
        }

        if ($found !== true) {
            throw new Exception(sprintf(
                'Language file [%s/%s] not defined.', $idiom, $langfile
            ));
        }

        if (!isset($lang) or !is_array($lang)) {
            log_message('error', 'Language file contains no data: language/' . $idiom . '/' . $langfile);

            if ($return === true) {
                return [];
            }
            return;
        }

        if ($return === true) {
            return $lang;
        }

        $this->is_loaded[$langfile] = $idiom;
        $this->loadedLanguageLines[$languageCacheKey] = $lang;
        $this->language = array_merge($this->language, $lang);

        log_message('info', 'Language file loaded: language/' . $idiom . '/' . $langfile);
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Language line
     *
     * Fetches a single line of text from the language array
     *
     * @param string $line Language line key
     * @param bool $log_errors Whether to log an error message if the line is not found
     * @return    string    Translation
     */
    public function line($line, $log_errors = true)
    {
        $value = $this->language[$line] ?? false;

        // Because killer robots like unicorns!
        if ($value === false && $log_errors === true) {
            log_message('error', 'Could not find the language line "' . $line . '"');
        }

        return $value;
    }

}
