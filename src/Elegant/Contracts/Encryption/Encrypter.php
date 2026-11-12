<?php

namespace Elegant\Contracts\Encryption;

interface Encrypter
{
    /**
     * Encrypt the given value.
     *
     * @param  mixed  $value
     * @param bool $serialize
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\EncryptException
     */
    public function encrypt($value, bool $serialize = true): string;

    /**
     * Decrypt the given value.
     *
     * @param string $payload
     * @param bool $unserialize
     * @return mixed
     *
     * @throws \Elegant\Contracts\Encryption\DecryptException
     */
    public function decrypt(string $payload, bool $unserialize = true);
}
