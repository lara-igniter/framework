<?php

namespace Elegant\Console;

use Elegant\Routing\Controller as BaseController;
use ReflectionException;

class Controller extends BaseController
{
    private $cli;

    protected $available_commands = [];

    /**
     * @throws ReflectionException
     */
    public function __construct()
    {
        is_cli() or die('This class should be called via CLI only');

        parent::__construct();

        $this->lang->load('command');

        $configs = [];
        if ($this->load->config('command', true, true)) {
            $configs = $this->config->item('command');
        }

        if ($this->load->config('command', true, true)) {
            $configs = array_merge($configs, $this->config->item('command'));
        }

        $this->cli = new Cli($configs);
    }

    /**
     * @throws ReflectionException
     */
    final public function index()
    {
        $args = func_get_args();

        if (!empty($this->available_commands)) {
            $this->cli->addCommands($this->available_commands);
        }

        return $this->cli->execute($args);
    }
}
