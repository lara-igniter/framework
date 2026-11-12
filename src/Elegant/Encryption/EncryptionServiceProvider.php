<?php

namespace Elegant\Encryption;

use Elegant\Contracts\Hook\PostControllerConstructor;

class EncryptionServiceProvider implements PostControllerConstructor
{
    /**
     * Register the encrypter and the CodeIgniter encryption library.
     *
     * The legacy Encrypt library is loaded only when Crypt::legacyEncode()
     * or Crypt::legacyDecode() is called, because it requires Mcrypt.
     *
     * @param  array  $params
     * @return void
     */
    public function postControllerConstructor(&$params)
    {
        app('load')->library('encryption');
        app('encrypter', new Encrypter());
    }
}
