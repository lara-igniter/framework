<?php

namespace Elegant\Console;

use Exception;

abstract class Commands
{
    /**
     * Command name
     *
     * @var string
     */
    protected $name = null;

    /**
     * Command description
     *
     * @var string
     */
    protected $description = null;

    /**
     * Codeigniter instance
     *
     * @var mixed
     */
    protected $CI;

    public function __construct($ci)
    {
        $this->CI =& $ci;
    }

    /**
     * Get command name
     *
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Get command description
     *
     * @return string
     */
    public function description()
    {
        if (substr($this->description, 0, 5) == 'lang:') {
            $description = substr($this->description, 5);

            if ($desc = $this->CI->lang->line($description)) {
                return $desc;
            }

            return $description;
        }

        return $this->description;
    }

    /**
     * Register arguments
     *
     * @param Cli
     */
    abstract protected function register(Cli $console);

    /**
     * Execute command
     *
     * @param Cli
     */
    abstract protected function execute(Cli $console);

    /**
     * Initialize commands
     *
     * @param  array                 $args    Arguments
     * @param  Cli $command
     * @return mixed
     */
    public function initialize($args, Cli $command)
    {
        // Set command description
        $command->set_description($this->description);
        // Every single command should have help, right?
        $command->add_arg('help', [
            'prefix'      => 'h',
            'longPrefix'  => 'help',
            'description' => Cli::lang('console_display_help'),
            'noValue'     => true
        ]);

        // Register command arguments
        $this->register($command);

        try {
            // Parse the arguments
            $command->parse_arg($args);

            // If ask for help
            if ($command->get_arg('help') !== false) {
                return $command->usage([], $this->name);
            }

            // Execute actual arguments
            return $this->execute($command);
        } catch (Exception $e) {
            $executed = false;
            return EXIT_USER_INPUT;
        }
    }
}
