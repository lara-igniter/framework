<?php

namespace Elegant\Foundation\Http\File;

use RuntimeException;
use Symfony\Component\Mime\MimeTypes;

class UploadedFile extends File
{
    protected string $originalName;
    protected ?string $mimeType;
    protected ?int $error;

    /**
     * Accepts the file information as would be filled in from the $_FILES array.
     *
     * @param string $path The full temporary path to the file
     * @param string $originalName The original file name of the uploaded file
     * @param string|null $mimeType The type of the file as provided by PHP; null defaults to application/octet-stream
     * @param int|null $error The error constant of the upload (one of PHP's UPLOAD_ERR_XXX constants); null defaults to UPLOAD_ERR_OK
     */
    public function __construct(string $path, string $originalName, ?string $mimeType = null, ?int $error = null)
    {
        $this->originalName = $this->getName($originalName);
        $this->mimeType = $mimeType ?: 'application/octet-stream';
        $this->error = $error ?: \UPLOAD_ERR_OK;

        parent::__construct($path, \UPLOAD_ERR_OK === $this->error);
    }

    /**
     * Returns the original file name.
     *
     * It is extracted from the request from which the file has been uploaded.
     * Then it should not be considered as a safe value.
     *
     * @return string
     */
    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * Returns the original file extension.
     *
     * It is extracted from the original file name that was uploaded.
     * Then it should not be considered as a safe value.
     *
     * @return string
     */
    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->originalName, \PATHINFO_EXTENSION);
    }

    /**
     * Returns the file mime type.
     *
     * The client mime type is extracted from the request from which the file
     * was uploaded, so it should not be considered as a safe value.
     *
     * For a trusted mime type, use getMimeType() instead (which guesses the mime
     * type based on the file content).
     *
     * @return string
     */
    public function getClientMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Returns the extension based on the client mime type.
     *
     * If the mime type is unknown, returns null.
     *
     * This method uses the mime type as guessed by getClientMimeType()
     * to guess the file extension. As such, the extension returned
     * by this method cannot be trusted.
     *
     * For a trusted extension, use guessExtension() instead (which guesses
     * the extension based on the guessed mime type for the file).
     *
     * @return string|null
     *
     * @see guessExtension()
     * @see getClientMimeType()
     */
    public function guessClientExtension()
    {
        if (!class_exists(MimeTypes::class)) {
            throw new \LogicException('You cannot guess the extension as the Mime component is not installed. Try running "composer require symfony/mime".');
        }

        return MimeTypes::getDefault()->getExtensions($this->getClientMimeType())[0] ?? null;
    }

    /**
     * Returns the upload error.
     *
     * If the upload was successful, the constant UPLOAD_ERR_OK is returned.
     * Otherwise one of the other UPLOAD_ERR_XXX constants is returned.
     *
     * @return int
     */
    public function getError(): ?int
    {
        return $this->error;
    }

    /**
     * Returns whether the file has been uploaded with HTTP and no error occurred.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        $isOk = \UPLOAD_ERR_OK === $this->error;

        return $isOk && is_uploaded_file($this->getPathname());
    }

    /**
     * Moves the file to a new location.
     *
     * @param string $directory
     * @param string|null $name
     * @return \Elegant\Foundation\Http\File\File
     *
     */
    public function move(string $directory, string $name = null)
    {
        if ($this->isValid()) {
            $target = $this->getTargetFile($directory, $name);

            set_error_handler(function ($type, $msg) use (&$error) { $error = $msg; });
            try {
                $moved = move_uploaded_file($this->getPathname(), $target);
            } finally {
                restore_error_handler();
            }
            if (!$moved) {
                throw new RuntimeException(sprintf('Could not move the file "%s" to "%s" (%s).', $this->getPathname(), $target, strip_tags($error)));
            }

            @chmod($target, 0666 & ~umask());

            return $target;
        }

        switch ($this->error) {
            case \UPLOAD_ERR_INI_SIZE:
                throw new RuntimeException($this->getErrorMessage());
            case \UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException($this->getErrorMessage());
            case \UPLOAD_ERR_PARTIAL:
                throw new RuntimeException($this->getErrorMessage());
            case \UPLOAD_ERR_NO_FILE:
                throw new RuntimeException($this->getErrorMessage());
            case \UPLOAD_ERR_CANT_WRITE:
                throw new RuntimeException($this->getErrorMessage());
            case \UPLOAD_ERR_NO_TMP_DIR:
                throw new RuntimeException($this->getErrorMessage());
            case \UPLOAD_ERR_EXTENSION:
                throw new RuntimeException($this->getErrorMessage());
        }

        throw new RuntimeException($this->getErrorMessage());
    }

    /**
     * Returns the maximum size of an uploaded file as configured in php.ini.
     *
     * @return int|float The maximum size of an uploaded file in bytes (returns float if size > PHP_INT_MAX)
     */
    public static function getMaxFilesize()
    {
        $sizePostMax = self::parseFilesize(ini_get('post_max_size'));
        $sizeUploadMax = self::parseFilesize(ini_get('upload_max_filesize'));

        return min($sizePostMax ?: \PHP_INT_MAX, $sizeUploadMax ?: \PHP_INT_MAX);
    }

    /**
     * Returns the given size from an ini value in bytes.
     *
     * @return int|float Returns float if size > PHP_INT_MAX
     */
    private static function parseFilesize(string $size)
    {
        if ('' === $size) {
            return 0;
        }

        $size = strtolower($size);

        $max = ltrim($size, '+');
        if (str_starts_with($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($size, -1)) {
            case 't': $max *= 1024;
            // no break
            case 'g': $max *= 1024;
            // no break
            case 'm': $max *= 1024;
            // no break
            case 'k': $max *= 1024;
        }

        return $max;
    }

    /**
     * Get error string
     */
    /**
     * Returns an informative upload error message.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        static $errors = [
            \UPLOAD_ERR_INI_SIZE => 'The file "%s" exceeds your upload_max_filesize ini directive (limit is %d KiB).',
            \UPLOAD_ERR_FORM_SIZE => 'The file "%s" exceeds the upload limit defined in your form.',
            \UPLOAD_ERR_PARTIAL => 'The file "%s" was only partially uploaded.',
            \UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            \UPLOAD_ERR_CANT_WRITE => 'The file "%s" could not be written on disk.',
            \UPLOAD_ERR_NO_TMP_DIR => 'File could not be uploaded: missing temporary directory.',
            \UPLOAD_ERR_EXTENSION => 'File upload was stopped by a PHP extension.',
        ];

        $errorCode = $this->error;
        $maxFilesize = \UPLOAD_ERR_INI_SIZE === $errorCode ? self::getMaxFilesize() / 1024 : 0;
        $message = $errors[$errorCode] ?? 'The file "%s" was not uploaded due to an unknown error.';

        return sprintf($message, $this->getClientOriginalName(), $maxFilesize);
    }
}