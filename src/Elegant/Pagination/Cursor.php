<?php

namespace Elegant\Pagination;

use Elegant\Contracts\Support\Arrayable;
use UnexpectedValueException;

class Cursor implements Arrayable
{
    /**
     * The parameters associated with the cursor.
     *
     * @var array
     */
    protected array $parameters;

    /**
     * Determine whether the cursor points to the next or previous set of items.
     *
     * @var bool
     */
    protected bool $pointsToNextItems;

    /**
     * Create a new cursor instance.
     *
     * @param array $parameters
     * @param bool $pointsToNextItems
     */
    public function __construct(array $parameters, bool $pointsToNextItems = true)
    {
        $this->parameters = $parameters;
        $this->pointsToNextItems = $pointsToNextItems;
    }

    /**
     * Get the given parameter from the cursor.
     *
     * @param string $parameterName
     * @return string|null
     *
     * @throws \UnexpectedValueException
     */
    public function parameter(string $parameterName): ?string
    {
        if (!array_key_exists($parameterName, $this->parameters)) {
            throw new UnexpectedValueException("Unable to find parameter [{$parameterName}] in pagination item.");
        }

        return $this->parameters[$parameterName];
    }

    /**
     * Get the given parameters from the cursor.
     *
     * @param array $parameterNames
     * @return array
     */
    public function parameters(array $parameterNames): array
    {
        return collect($parameterNames)->map(function ($parameterName) {
            return $this->parameter($parameterName);
        })->toArray();
    }

    /**
     * Determine whether the cursor points to the next set of items.
     *
     * @return bool
     */
    public function pointsToNextItems(): bool
    {
        return $this->pointsToNextItems;
    }

    /**
     * Determine whether the cursor points to the previous set of items.
     *
     * @return bool
     */
    public function pointsToPreviousItems(): bool
    {
        return !$this->pointsToNextItems;
    }

    /**
     * Get the array representation of the cursor.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge($this->parameters, [
            '_pointsToNextItems' => $this->pointsToNextItems,
        ]);
    }

    /**
     * Get the encoded string representation of the cursor to construct a URL.
     *
     * @return string
     */
    public function encode(): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($this->toArray())));
    }

    /**
     * Get a cursor instance from the encoded string representation.
     *
     * @param string|null $encodedString
     * @return static|null
     */
    public static function fromEncoded(?string $encodedString): ?Cursor
    {
        if (!is_string($encodedString)) {
            return null;
        }

        $parameters = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $encodedString)), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $pointsToNextItems = $parameters['_pointsToNextItems'];

        unset($parameters['_pointsToNextItems']);

        return new static($parameters, $pointsToNextItems);
    }
}

