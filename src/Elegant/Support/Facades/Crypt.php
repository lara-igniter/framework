<?php

namespace Elegant\Support\Facades;

/**
 * @method static string decrypt(string $payload, bool $unserialize = true)
 * @method static string decryptString(string $payload)
 * @method static string encrypt(mixed $value, bool $serialize = true)
 * @method static string encryptString(string $value)
 * @method static string generateKey(string $cipher = 'AES-256-CBC')
 * @method static string legacyDecode(string $value, string $key = '')
 * @method static string legacyEncode(string $value, string $key = '')
 * @method static string randomBytes(int $length)
 * @method static string randomHex(int $length = 40)
 *
 * @see \Elegant\Encryption\Encrypter
 */
class Crypt extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'encrypter';
    }
}
