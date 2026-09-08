<?php

namespace Elegant\Http;

/**
 * User-agent parser that reads config from the project root (Laravel-style).
 */
class UserAgent extends \CI_User_agent
{
    /**
     * @return bool
     */
    protected function _load_agent_file()
    {
        $found = file_exists(base_path('config/user_agents.php'));

        if ($found) {
            include base_path('config/user_agents.php');
        }

        if (file_exists(base_path('config/'.ENVIRONMENT.'/user_agents.php'))) {
            include base_path('config/'.ENVIRONMENT.'/user_agents.php');
            $found = true;
        }

        if ($found !== true) {
            return false;
        }

        $return = false;

        if (isset($platforms)) {
            $this->platforms = $platforms;
            unset($platforms);
            $return = true;
        }

        if (isset($browsers)) {
            $this->browsers = $browsers;
            unset($browsers);
            $return = true;
        }

        if (isset($mobiles)) {
            $this->mobiles = $mobiles;
            unset($mobiles);
            $return = true;
        }

        if (isset($robots)) {
            $this->robots = $robots;
            unset($robots);
            $return = true;
        }

        return $return;
    }
}
