<?php

namespace Elegant\Foundation\Exceptions;

class Displayer extends \CI_Exceptions
{
    /**
     * 404 Error Handler
     *
     * @uses	CI_Exceptions::show_error()
     *
     * @param	string	$page		Page URI
     * @param 	bool	$log_error	Whether to log the error
     * @return	void
     */
    public function show_404($page = '', $log_error = false)
    {
        if (is_cli()) {
            $heading = 'Not Found';
            $message = 'The controller/method pair you requested was not found.';
        } else {
            $heading = '404 Page Not Found';
            $message = 'Page Not Found.';
        }

        // By default, we not log this, but allow a dev to skip it
        if ($log_error) {
            log_message('error', $heading.': '.$page);
        }

        echo $this->show_error($heading, $message, '404', 404);
        exit(4); // EXIT_UNKNOWN_FILE
    }

    // --------------------------------------------------------------------

    /**
     * General Error Page
     *
     * Takes an error message as input (either as a string or an array)
     * and displays it using the specified template.
     *
     * @param	string		$heading	Page heading
     * @param	string|string[]	$message	Error message
     * @param	string		$template	Template name
     * @param 	int		$status_code	(default: 500)
     *
     * @return	string	Error page output
     */
    public function show_error($heading, $message, $template = 'error_general', $status_code = 500)
    {
        $templates_path = config_item('error_views_path');

        // TODO: Add logic to get files from vendor if not exist in view resource path.
        if (empty($templates_path)) {
            $templates_path = VIEWPATH.'errors'.DIRECTORY_SEPARATOR;
        }

        if (ob_get_level() > $this->ob_level + 1) {
            ob_end_flush();
        }

        ob_start();

        if (is_cli()) {
            $message = (is_array($message) ? implode("\n\t", $message) : $message);
            $template = 'cli'.DIRECTORY_SEPARATOR.$template;

            include($templates_path.$template.'.php');
        } else {
            set_status_header($status_code);
            $message = '<p>'.(is_array($message) ? implode('</p><p>', $message) : $message).'</p>';

            if (file_exists($templates_path.'/'.$status_code.'.blade.php')) {
                $html = view("errors.{$template}", [
                    'heading' => $heading,
                    'message' => strip_tags($message),
                ]);

                echo $html;
            } else {
                $template = 'html'.DIRECTORY_SEPARATOR.$template;

                include($templates_path.$template.'.php');
            }
        }

        $buffer = ob_get_contents();

        ob_end_clean();

        return $buffer;
    }
}
