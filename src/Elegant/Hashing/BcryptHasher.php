<?php

namespace Elegant\Hashing;

use Elegant\Contracts\Hashing\Hasher as HasherContract;
use RuntimeException;

class BcryptHasher extends AbstractHasher implements HasherContract
{
    /**
     * The default cost factor.
     *
     * @var int
     */
    protected $rounds = 12;

    /**
     * Create a new hasher instance.
     *
     * @param  array  $options
     * @return void
     */
    public function __construct(array $options = [])
    {
        $this->rounds = isset($options['rounds']) ? (int) $options['rounds'] : $this->rounds;
    }

    /**
     * Hash the given value.
     *
     * @param string $value
     * @param  array  $options
     * @return string
     *
     * @throws RuntimeException
     */
    public function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);

        if ($hash === false) {
            throw new RuntimeException('Bcrypt hashing not supported.');
        }

        return $hash;
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
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);
    }

    /**
     * Extract the cost value from the options array.
     *
     * @param  array  $options
     * @return int
     */
    protected function cost(array $options = []): int
    {
        return isset($options['rounds']) ? (int) $options['rounds'] : $this->rounds;
    }
}
