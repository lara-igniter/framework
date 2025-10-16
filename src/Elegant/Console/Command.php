<?php

namespace Elegant\Console;

use CI_Controller;

class Command extends CI_Controller
{
    use Concerns\HasParameters,
        Concerns\InteractsWithIO;

    /**
     * The console command argument definitions.
     *
     * @var array
     */
    protected array $arguments = [];

    /**
     * The console command option definitions.
     *
     * @var array
     */
    protected array $options = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature;


    public function __construct()
    {
        if (isset($this->signature)) {
            $this->configureUsingFluentDefinition();
        } else {
            parent::__construct();
        }
    }


    /**
     * Configure the console command using a fluent definition.
     *
     * @return void
     */
    protected function configureUsingFluentDefinition()
    {
        [$name, $arguments, $options] = Parser::parse($this->signature);

        $this->arguments = $arguments;
        $this->options = $options;

        $this->specifyParameters();

        parent::__construct();
    }
}
