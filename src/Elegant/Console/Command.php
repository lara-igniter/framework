<?php

namespace Elegant\Console;

use CI_Controller;

class Command extends CI_Controller
{
    use Concerns\CallsCommands,
        Concerns\HasParameters,
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

    /**
     * When true the CI_Controller parent constructor is skipped.
     * Set by CallsCommands::runCommand() for nested (inner) command calls so that
     * instantiating a command inside another command's handle() does not trigger
     * CI's library-loading chain (which fails for drivers like Cache).
     *
     * @var bool
     */
    public static bool $skipCiConstruct = false;

    public function __construct()
    {
        if (isset($this->signature)) {
            $this->configureUsingFluentDefinition();
        } elseif (! self::$skipCiConstruct) {
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

        if (! self::$skipCiConstruct) {
            parent::__construct();
        }
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
            exit(0);
        }

        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        $statusCode = $this->$method();

        exit(is_numeric($statusCode) ? (int) $statusCode : 0);
    }

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
            $this->warn('Description:');
            $this->line('  ' . $this->description, 'light_gray');
            $this->newLine();
        }

        $usage = '  ' . $name;
        foreach ($arguments as $arg) {
            $usage .= $arg['required'] ? ' <' . $arg['name'] . '>' : ' [' . $arg['name'] . ']';
        }
        if (!empty($options)) {
            $usage .= ' [options]';
        }

        $this->warn('Usage:');
        $this->line($usage, 'light_gray');
        $this->newLine();

        if (!empty($arguments)) {
            $this->warn('Arguments:');
            foreach ($arguments as $arg) {
                $title = '  ' . $arg['name'];
                $desc = $arg['description'] ?? '';
                $this->line(
                    substr($title . str_repeat(' ', $maxLen + 3), 0, $maxLen + 3)
                    . OutputStyle::wrap(OutputStyle::color($desc, 'light_gray'), 120, $maxLen + 3),
                    'green'
                );
            }
            $this->newLine();
        }

        if (!empty($options)) {
            $this->warn('Options:');
            foreach ($options as $opt) {
                $short = !empty($opt['shortcut']) ? '-' . $opt['shortcut'] . ', ' : '    ';
                $title = '  ' . $short . '--' . $opt['name'];
                $desc = $opt['description'] ?? '';
                $this->line(
                    substr($title . str_repeat(' ', $maxLen + 3), 0, $maxLen + 3)
                    . OutputStyle::wrap(OutputStyle::color($desc, 'light_gray'), 120, $maxLen + 3),
                    'green'
                );
            }
            $this->newLine();
        }
    }
}
