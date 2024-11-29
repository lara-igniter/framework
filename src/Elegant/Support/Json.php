<?php

namespace Elegant\Support;

use Elegant\Support\Facades\File;
use Elegant\Support\Traits\Macroable;

class Json
{
    use Macroable;

    /**
     * Create a nested Collection from the given JSON file containing an array of objects.
     *
     * @param string $path
     * @param string|null $attribute
     * @return \Elegant\Support\Collection<int|string, mixed>
     */
    public static function toCollection(string $path, ?string $attribute = null): Collection
    {
        $json = File::json($path, JSON_THROW_ON_ERROR);

        if (! is_array($json)) {
            return collect();
        }

        $data = collect($json)->mapInto(Collection::class);

        if ($attribute) {
            return $data->map(fn ($item) => $item->get($attribute));
        }

        return $data;
    }

    /**
     * Create a nested Collection from the given JSON file containing an array of objects.
     *
     * @param string $path
     * @param string|null $attribute
     * @return array<int|string, mixed>
     */
    public static function toArray(string $path, ?string $attribute = null): array
    {
        return static::toCollection($path, $attribute)->toArray();
    }
}
