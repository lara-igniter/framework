<?php

namespace Elegant\Foundation\Http\File;

use Elegant\Support\Facades\Crypt;

trait FileHelpers
{
    /**
     * The cache copy of the file's hash name.
     *
     * @var string|null
     */
    protected ?string $hashName = null;

    /**
     * Get the fully qualified path to the file.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->getRealPath();
    }

    /**
     * Get the file's extension.
     *
     * @return string
     */
    public function extension(): string
    {
        return $this->guessExtension();
    }

    /**
     * Get a filename for the file.
     *
     * @param  string|null  $path
     * @return string
     */
    public function hashName(string $path = null): string
    {
        if ($path) {
            $path = rtrim($path, '/').'/';
        }

        $hash = $this->hashName ?: $this->hashName = Crypt::randomHex(80);

        if ($extension = $this->guessExtension()) {
            $extension = '.'.$extension;
        }

        return $path.$hash.$extension;
    }
}
