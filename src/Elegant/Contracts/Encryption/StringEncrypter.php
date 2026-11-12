<?php

namespace Elegant\Contracts\Encryption;

interface StringEncrypter
{
    /**
     * Encrypt a string without serialization.
     *
     * @param string $value
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\EncryptException
     */
    public function encryptString(string $value): string;

    /**
     * Decrypt the given string without unserialization.
     *
     * @param string $payload
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\DecryptException
     */
    public function decryptString(string $payload): string;
}
