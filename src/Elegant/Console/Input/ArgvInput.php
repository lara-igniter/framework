<?php

namespace Elegant\Console\Input;

class ArgvInput
{
    /**
     * The raw CLI tokens.
     *
     * @var string[]
     */
    private array $tokens;

    /**
     * Create a new ArgvInput instance.
     *
     * @param string[]|null $argv
     */
    public function __construct(?array $argv = null)
    {
        $this->tokens = $argv ?? $_SERVER['argv'] ?? [];
    }

    /**
     * Returns the first non-option token (the command name).
     *
     * @return string|null
     */
    public function getFirstArgument(): ?string
    {
        foreach ($this->tokens as $i => $token) {
            if ($i === 0) {
                continue;
            }

            if (strpos($token, '-') !== 0) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Returns true if the given option is present in the token list.
     *
     * @param string $value
     * @return bool
     */
    public function hasParameterOption(string $value): bool
    {
        foreach (array_slice($this->tokens, 1) as $token) {
            if ($token === $value || strpos($token, $value . '=') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the value of an option, or $default if absent.
     *
     * @param string $value
     * @param mixed $default
     * @return mixed
     */
    public function getParameterOption(string $value, $default = false)
    {
        foreach (array_slice($this->tokens, 1) as $token) {
            if (strpos($token, $value . '=') === 0) {
                return substr($token, strlen($value) + 1);
            }

            if ($token === $value) {
                return true;
            }
        }

        return $default;
    }

    /**
     * Returns the raw token array.
     *
     * @return string[]
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }
}
