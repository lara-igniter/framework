<?php

namespace Elegant\Hashing;

use Elegant\Contracts\Hashing\Hasher as HasherContract;
use Elegant\Support\Manager;

class HashManager extends Manager implements HasherContract
{
    /**
     * Create an instance of the Bcrypt hash driver.
     *
     * @return \Elegant\Hashing\BcryptHasher
     */
    public function createBcryptDriver(): BcryptHasher
    {
        return new BcryptHasher($this->options('hashing.bcrypt'));
    }

    /**
     * Create an instance of the Argon2i hash driver.
     *
     * @return \Elegant\Hashing\ArgonHasher
     */
    public function createArgonDriver(): ArgonHasher
    {
        return new ArgonHasher($this->options('hashing.argon'));
    }

    /**
     * Create an instance of the Argon2id hash driver.
     *
     * @return \Elegant\Hashing\Argon2IdHasher
     */
    public function createArgon2idDriver(): Argon2IdHasher
    {
        return new Argon2IdHasher($this->options('hashing.argon'));
    }

    /**
     * Get information about the given hashed value.
     *
     * @param string $hashedValue
     * @return array
     */
    public function info(string $hashedValue): array
    {
        return $this->driver()->info($hashedValue);
    }

    /**
     * Hash the given value.
     *
     * @param string $value
     * @param  array  $options
     * @return string
     */
    public function make(string $value, array $options = []): string
    {
        return $this->driver()->make($value, $options);
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param string $value
     * @param string $hashedValue
     * @param  array  $options
     * @return bool
     */
    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        return $this->driver()->check($value, $hashedValue, $options);
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param string $hashedValue
     * @param  array  $options
     * @return bool
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return $this->driver()->needsRehash($hashedValue, $options);
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): ?string
    {
        return $this->getConfig('hashing.driver', 'bcrypt');
    }

    /**
     * Read a hasher option group from configuration.
     *
     * @param string $key
     * @return array
     */
    protected function options(string $key): array
    {
        $options = $this->getConfig($key, []);

        return is_array($options) ? $options : [];
    }
}
