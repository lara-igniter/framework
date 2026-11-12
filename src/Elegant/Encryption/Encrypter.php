<?php

namespace Elegant\Encryption;

use Elegant\Contracts\Encryption\DecryptException;
use Elegant\Contracts\Encryption\Encrypter as EncrypterContract;
use Elegant\Contracts\Encryption\EncryptException;
use Elegant\Contracts\Encryption\StringEncrypter;

class Encrypter implements EncrypterContract, StringEncrypter
{
    /**
     * Encrypt the given value.
     *
     * Uses the CodeIgniter encryption library. Serialized values stay compatible
     * with Crypt::decrypt().
     *
     * @param  mixed  $value
     * @param bool $serialize
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\EncryptException
     */
    public function encrypt($value, bool $serialize = true): string
    {
        $payload = $serialize ? serialize($value) : (string) $value;
        $encrypted = $this->encryption()->encrypt($payload);

        if ($encrypted === false || $encrypted === null) {
            throw new EncryptException('Could not encrypt the data.');
        }

        return $encrypted;
    }

    /**
     * Decrypt the given value.
     *
     * @param string $payload
     * @param bool $unserialize
     * @return mixed
     *
     * @throws \Elegant\Contracts\Encryption\DecryptException
     */
    public function decrypt(string $payload, bool $unserialize = true)
    {
        $decrypted = $this->encryption()->decrypt($payload);

        if ($decrypted === false || $decrypted === null) {
            throw new DecryptException('Could not decrypt the data.');
        }

        if (!$unserialize) {
            return $decrypted;
        }

        $value = @unserialize($decrypted);

        if ($value === false && $decrypted !== serialize(false)) {
            throw new DecryptException('Could not decrypt the data.');
        }

        return $value;
    }

    /**
     * Encrypt a string without serialization.
     *
     * @param string $value
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\EncryptException
     */
    public function encryptString(string $value): string
    {
        return $this->encrypt($value, false);
    }

    /**
     * Decrypt the given string without unserialization.
     *
     * @param string $payload
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\DecryptException
     */
    public function decryptString(string $payload): string
    {
        return $this->decrypt($payload, false);
    }

    /**
     * Create a new raw encryption key.
     *
     * @param string $cipher
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\EncryptException
     */
    public function generateKey(string $cipher = 'AES-256-CBC'): string
    {
        $length = $cipher === 'AES-128-CBC' ? 16 : 32;

        return $this->randomBytes($length);
    }

    /**
     * Create a random hex string with the given number of characters.
     *
     * @param int $length
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\EncryptException
     */
    public function randomHex(int $length = 40): string
    {
        if ($length < 2 || $length % 2 !== 0) {
            throw new EncryptException('Hex length must be a positive even number.');
        }

        return bin2hex($this->randomBytes((int) ($length / 2)));
    }

    /**
     * Create a random binary string using the CodeIgniter encryption library.
     *
     * @param int $length
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\EncryptException
     */
    public function randomBytes(int $length): string
    {
        $bytes = $this->encryption()->create_key((int) $length);

        if (!is_string($bytes) || strlen($bytes) !== (int) $length) {
            throw new EncryptException('Could not generate random bytes.');
        }

        return $bytes;
    }

    /**
     * Encode a value with the legacy CodeIgniter Encrypt library.
     *
     * The legacy library needs the Mcrypt extension and is not loaded until
     * this method is called.
     *
     * @param string $value
     * @param string $key
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\EncryptException
     */
    public function legacyEncode(string $value, string $key = ''): string
    {
        $encoded = $this->legacy()->encode($value, $key);

        if ($encoded === false || $encoded === null) {
            throw new EncryptException('Could not encode the data with the legacy encrypter.');
        }

        return $encoded;
    }

    /**
     * Decode a value with the legacy CodeIgniter Encrypt library.
     *
     * @param string $value
     * @param string $key
     * @return string
     *
     * @throws \Elegant\Contracts\Encryption\DecryptException
     */
    public function legacyDecode(string $value, string $key = ''): string
    {
        $decoded = $this->legacy()->decode($value, $key);

        if ($decoded === false || $decoded === null) {
            throw new DecryptException('Could not decode the data with the legacy encrypter.');
        }

        return $decoded;
    }

    /**
     * Resolve the OpenSSL CodeIgniter encryption library.
     *
     * @return object
     */
    protected function encryption(): object
    {
        $ci = get_instance();

        if (!isset($ci->encryption)) {
            $ci->load->library('encryption');
        }

        return $ci->encryption;
    }

    /**
     * Resolve the legacy Mcrypt CodeIgniter encrypt library.
     *
     * @return object
     *
     * @throws \Elegant\Contracts\Encryption\EncryptException
     */
    protected function legacy(): object
    {
        if (!function_exists('mcrypt_encrypt')) {
            throw new EncryptException('The legacy Encrypt library requires the Mcrypt extension.');
        }

        $ci = get_instance();

        if (!isset($ci->encrypt)) {
            $ci->load->library('encrypt');
        }

        return $ci->encrypt;
    }
}
