<?php

namespace Elegant\Console;

class Parser
{
    /**
     * Parse the given console command definition into an array.
     *
     * @param string $expression
     * @return array
     */
    public static function parse(string $expression): array
    {
        $name = static::name($expression);

        if (preg_match_all('/\{\s*(.*?)\s*}/', $expression, $matches) && count($matches[1])) {
            return array_merge([$name], static::parameters($matches[1]));
        }

        return [$name, [], []];
    }

    /**
     * Extract the name of the command from the expression.
     *
     * @param string $expression
     * @return string
     */
    protected static function name(string $expression): string
    {
        if (!preg_match('/[^\s]+/', $expression, $matches)) {
            throw new \InvalidArgumentException('Unable to determine command name from signature.');
        }

        return $matches[0];
    }

    /**
     * Extract all of the parameters from the tokens.
     *
     * @param array $tokens
     * @return array
     */
    protected static function parameters(array $tokens): array
    {
        $arguments = [];
        $options = [];

        foreach ($tokens as $token) {
            if (preg_match('/-{2,}(.*)/', $token, $matches)) {
                $options[] = static::parseOption($matches[1]);
            } else {
                $arguments[] = static::parseArgument($token);
            }
        }

        return [$arguments, $options];
    }

    /**
     * Parse an argument expression.
     *
     * @param string $token
     * @return array
     */
    protected static function parseArgument($token)
    {
        [$token, $description] = static::extractDescription($token);

        $argument = [
            'is_array' => false,
            'default' => null,
            'description' => $description
        ];

        switch (true) {
            case str_ends_with($token, '?*'):
                $argument['name'] = trim($token, '?*');
                $argument['required'] = false;
                $argument['is_array'] = true;
                break;
            case str_ends_with($token, '*'):
                $argument['name'] = trim($token, '*');
                $argument['required'] = true;
                $argument['is_array'] = true;
                break;
            case str_ends_with($token, '?'):
                $argument['name'] = trim($token, '?');
                $argument['required'] = false;
                break;
            case preg_match('/(.+)=\*(.+)/', $token, $matches):
                $argument['name'] = $matches[1];
                $argument['required'] = false;
                $argument['is_array'] = true;
                $argument['default'] = preg_split('/,\s?/', $matches[2]);
                break;
            case preg_match('/(.+)=(.+)/', $token, $matches):
                $argument['name'] = $matches[1];
                $argument['required'] = false;
                $argument['default'] = $matches[2];
                break;
            default:
                $argument['name'] = $token;
                $argument['required'] = true;
        }

        return $argument;
    }

    /**
     * Parse an option expression.
     *
     * @param string $token
     * @return array
     */
    protected static function parseOption(string $token): array
    {
        [$token, $description] = static::extractDescription($token);

        $matches = preg_split('/\s*\|\s*/', $token, 2);
        $shortcut = null;

        if (isset($matches[1])) {
            if (str_starts_with($matches[1], '-')) {
                $token = $matches[0];
                $shortcut = trim($matches[1], '-=*');
            } elseif (str_starts_with($matches[0], '-')) {
                $shortcut = trim($matches[0], '-=*');
                $token = $matches[1];
            } else {
                $shortcut = trim($matches[0], '=*');
                $token = $matches[1];
            }
        }

        $option = [
            'shortcut' => $shortcut,
            'is_array' => false,
            'default' => null,
            'description' => $description
        ];

        switch (true) {
            case str_ends_with($token, '='):
                $option['name'] = trim($token, '=');
                $option['value_required'] = true;
                break;
            case str_ends_with($token, '=*'):
                $option['name'] = trim($token, '=*');
                $option['value_required'] = true;
                $option['is_array'] = true;
                break;
            case preg_match('/(.+)=\*(.+)/', $token, $matches):
                $option['name'] = $matches[1];
                $option['value_required'] = true;
                $option['is_array'] = true;
                $option['default'] = preg_split('/,\s?/', $matches[2]);
                break;
            case preg_match('/(.+)=(.+)/', $token, $matches):
                $option['name'] = $matches[1];
                $option['value_required'] = true;
                $option['default'] = $matches[2];
                break;
            default:
                $option['name'] = $token;
                $option['value_required'] = false;
        }

        return $option;
    }

    /**
     * Parse the token into its token and description segments.
     *
     * @param string $token
     * @return array
     */
    protected static function extractDescription(string $token): array
    {
        $parts = preg_split('/\s+:\s+/', trim($token), 2);

        return count($parts) === 2 ? $parts : [$token, ''];
    }
}
