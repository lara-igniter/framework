<?php

namespace Elegant\Console\Concerns;

trait HasParameters
{
    protected array $arguments = [];

    protected array $options = [];

    /**
     * Specify the parameters for the command.
     *
     * @return void
     */
    protected function specifyParameters()
    {
        global $argv;

        $inputArguments = [];
        $inputOptions = [];
        $currentOptionKey = null;

        $args = array_slice($argv, 2);

        foreach ($args as $arg) {
            if (preg_match('/^--([^=]+)=(.*)$/', $arg, $matches)) {
                $inputOptions[$matches[1]] = $matches[2];
                $currentOptionKey = null;
            } elseif (preg_match('/^--(.+)$/', $arg, $matches)) {
                $currentOptionKey = $matches[1];
                $inputOptions[$matches[1]] = true;
            } elseif (preg_match('/^-([a-zA-Z])=(.+)$/', $arg, $matches)) {
                $optionName = $this->getOptionNameByShortcut($matches[1]) ?? $matches[1];
                $inputOptions[$optionName] = $matches[2];
                $currentOptionKey = null;
            } elseif (preg_match('/^-([a-zA-Z]{2,})$/', $arg, $matches)) {
                $flags = str_split($matches[1]);
                foreach ($flags as $sc) {
                    $optionName = $this->getOptionNameByShortcut($sc) ?? $sc;
                    $inputOptions[$optionName] = true;
                    if ($sc === end($flags) && $this->optionRequiresValue($optionName)) {
                        $currentOptionKey = $optionName;
                    }
                }
                if (!$this->optionRequiresValue($this->getOptionNameByShortcut(end($flags)) ?? end($flags))) {
                    $currentOptionKey = null;
                }
            } elseif (preg_match('/^-([a-zA-Z])$/', $arg, $matches)) {
                $shortcut = $matches[1];
                $optionName = $this->getOptionNameByShortcut($shortcut) ?? $shortcut;
                $inputOptions[$optionName] = true;
                $currentOptionKey = $this->optionRequiresValue($optionName) ? $optionName : null;
            } elseif ($currentOptionKey !== null) {
                $inputOptions[$currentOptionKey] = $arg;
                $currentOptionKey = null;
            } else {
                $inputArguments[] = $arg;
            }
        }

        $this->arguments = $this->mapArguments($inputArguments);
        $this->options = $this->mapOptions($inputOptions);
    }

    /**
     * Determine whether a named option (already resolved from shortcut) requires a value.
     *
     * @param string $name
     * @return bool
     */
    protected function optionRequiresValue(string $name): bool
    {
        foreach ($this->getOptions() as $definition) {
            if (($definition['name'] ?? '') === $name) {
                return !empty($definition['value_required']);
            }
        }

        return false;
    }

    /**
     * Map input arguments to command arguments.
     *
     * @param array $inputArguments
     * @return array
     */
    protected function mapArguments(array $inputArguments): array
    {
        $mappedArguments = [];
        $argumentDefinitions = $this->getArguments();

        foreach ($argumentDefinitions as $index => $definition) {
            $name = $definition['name'];

            if (isset($inputArguments[$index])) {
                $mappedArguments[$name] = $inputArguments[$index];
            } elseif (isset($definition['default'])) {
                $mappedArguments[$name] = $definition['default'];
            } elseif (!$definition['required']) {
                $mappedArguments[$name] = null;
            }
        }

        return $mappedArguments;
    }

    /**
     * Map input options to command options.
     *
     * @param array $inputOptions
     * @return array
     */
    protected function mapOptions(array $inputOptions): array
    {
        $mappedOptions = [];
        $optionDefinitions = $this->getOptions();

        foreach ($optionDefinitions as $definition) {
            $name = $definition['name'];

            if (isset($inputOptions[$name])) {
                if ($definition['value_required'] && $inputOptions[$name] === true) {
                    $mappedOptions[$name] = $definition['default'];
                } else {
                    $mappedOptions[$name] = $inputOptions[$name];
                }
            } elseif (isset($definition['default'])) {
                $mappedOptions[$name] = $definition['default'];
            } else {
                $mappedOptions[$name] = $definition['value_required'] ? null : false;
            }
        }

        foreach ($inputOptions as $key => $value) {
            if (!isset($mappedOptions[$key])) {
                $mappedOptions[$key] = $value;
            }
        }

        return $mappedOptions;
    }

    /**
     * Get the option name by its shortcut.
     *
     * @param string $shortcut
     * @return string|null
     */
    protected function getOptionNameByShortcut(string $shortcut): ?string
    {
        foreach ($this->getOptions() as $definition) {
            if ($definition['shortcut'] === $shortcut) {
                return $definition['name'];
            }
        }

        return null;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return $this->options;
    }
}
