<?php

namespace Elegant\Console\Arguments;

use Elegant\Console\Cli;
use League\CLImate\Argument\Summary as BaseSummary;

class Summary extends BaseSummary
{
    /**
     * {inheritdoc}
     */
    public function output()
    {
        // Print the description if it's defined.
        if ($this->description) {
            $this->climate->out($this->description)->br();
        }

        // Print the usage statement with the arguments without a prefix at the end.
        $this->climate->out(
            sprintf('<yellow>%s</yellow>: %s ', Cli::lang('console_commands_usage'), $this->command) . $this->short($this->getOrderedArguments())
        );

        // Print argument details.
        foreach (['required', 'optional'] as $type) {
            $this->outputArguments($this->filter->{$type}(), $type);
        }
    }

    /**
     * {inheritdoc}
     */
    protected function outputArguments($arguments, $type)
    {
        if (count($arguments) == 0) {
            return;
        }

        $this->climate->br()->out(
            sprintf('<yellow>%s</yellow>:', Cli::lang('console_argument_'.$type))
        );

        $len = [];
        foreach ($arguments as $argument) {
            $len[] = strlen($this->argument($argument));
        }

        foreach ($arguments as $argument) {
            $arg = $this->argument($argument);
            $spc = (max($len) + 2) - strlen($arg);
            $str = '<green>' . $arg . '</green>' . str_repeat(' ', $spc);

            if ($argument->description()) {
                $str .= $argument->description();
            }

            $this->climate->tab()->out($str);
        }
    }
}
