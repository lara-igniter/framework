<?php

namespace Elegant\Console;

use CI_Controller;

abstract class Command extends CI_Controller
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

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = '';

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
    protected function configureUsingFluentDefinition(): void
    {
        [$name, $arguments, $options] = Parser::parse($this->signature);

        $this->arguments = $arguments;
        $this->options = $options;

        $this->specifyParameters();

        parent::__construct();
    }

    /**
     * CI router entry-point — registered by Kernel::registerDiscoveredRoutes().
     *
     * @return void
     */
    final public function execute(): void
    {
        if ($this->option('h') || $this->option('help')) {
            $this->printHelp();
            return;
        }

        $this->handle();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    abstract public function handle(): void;

    /**
     * Print command help: Description, Usage, Arguments, Options.
     *
     * @return void
     */
    public function printHelp(): void
    {
        [$name, $arguments, $options] = Parser::parse($this->signature);

        $maxLen = 24;

        if ($this->description !== '') {
            OutputStyle::write('Description:', 'yellow');
            OutputStyle::write('  ' . $this->description, 'light_gray');
            OutputStyle::newLine();
        }

        // Usage line
        $usage = '  ' . $name;
        foreach ($arguments as $arg) {
            $usage .= $arg['required'] ? ' <' . $arg['name'] . '>' : ' [' . $arg['name'] . ']';
        }
        if (!empty($options)) {
            $usage .= ' [options]';
        }

        OutputStyle::write('Usage:', 'yellow');
        OutputStyle::write($usage, 'light_gray');
        OutputStyle::newLine();

        if (!empty($arguments)) {
            OutputStyle::write('Arguments:', 'yellow');
            foreach ($arguments as $arg) {
                $title = '  ' . $arg['name'];
                $desc = $arg['description'] ?? '';
                OutputStyle::write(
                    substr($title . str_repeat(' ', $maxLen + 3), 0, $maxLen + 3)
                    . OutputStyle::wrap(OutputStyle::color($desc, 'light_gray'), 120, $maxLen + 3),
                    'green'
                );
            }
            OutputStyle::newLine();
        }

        if (!empty($options)) {
            OutputStyle::write('Options:', 'yellow');
            foreach ($options as $opt) {
                $short = !empty($opt['shortcut']) ? '-' . $opt['shortcut'] . ', ' : '    ';
                $title = '  ' . $short . '--' . $opt['name'];
                $desc = $opt['description'] ?? '';
                OutputStyle::write(
                    substr($title . str_repeat(' ', $maxLen + 3), 0, $maxLen + 3)
                    . OutputStyle::wrap(OutputStyle::color($desc, 'light_gray'), 120, $maxLen + 3),
                    'green'
                );
            }
            OutputStyle::newLine();
        }
    }
}
