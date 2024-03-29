<?php

namespace Elegant\Database\Model\Concerns;

use Elegant\Support\Str;

trait GuardsAttributes
{
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected array $fillable = [];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var string[]|bool
     */
    protected $guarded = ['*'];

    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static bool $unguarded = false;

    /**
     * The actual columns that exist on the database and can be guarded.
     *
     * @var array
     */
    protected static array $guardableColumns = [];

    /**
     * Get the fillable attributes for the model.
     *
     * @return array
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Set the fillable attributes for the model.
     *
     * @param array $fillable
     */
    public function fillable(array $fillable)
    {
        $this->fillable = $fillable;
    }

    /**
     * Get the guarded attributes for the model.
     *
     * @return array
     */
    public function getGuarded(): array
    {
        return $this->guarded === false
            ? []
            : $this->guarded;
    }

    /**
     * Determine if the given attribute may be mass assigned.
     *
     * @param string $key
     * @return bool
     */
    public function isFillable(string $key): bool
    {
        if (static::$unguarded) {
            return true;
        }

        // If the key is in the "fillable" array, we can of course assume that it's
        // a fillable attribute. Otherwise, we will check the guarded array when
        // we need to determine if the attribute is black-listed on the model.
        if (in_array($key, $this->getFillable())) {
            return true;
        }

        // If the attribute is explicitly listed in the "guarded" array then we can
        // return false immediately. This means this attribute is definitely not
        // fillable and there is no point in going any further in this method.
        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->getFillable()) &&
            strpos($key, '.') === false &&
            !Str::startsWith($key, '_');
    }

    /**
     * Determine if the given key is guarded.
     *
     * @param string $key
     * @return bool
     */
    public function isGuarded(string $key): bool
    {
        if (empty($this->getGuarded())) {
            return false;
        }

        return $this->getGuarded() == ['*'] ||
            !empty(preg_grep('/^' . preg_quote($key) . '$/i', $this->getGuarded())) ||
            !$this->isGuardableColumn($key);
    }

    /**
     * Determine if the given column is a valid, guardable column.
     *
     * @param string $key
     * @return bool
     */
    protected function isGuardableColumn(string $key): bool
    {
        if (!isset(static::$guardableColumns[get_class($this)])) {
            static::$guardableColumns[get_class($this)] = $this->database->list_fields($this->getTable());
        }

        return in_array($key, static::$guardableColumns[get_class($this)]);
    }

    /**
     * Determine if the model is totally guarded.
     *
     * @return bool
     */
    public function totallyGuarded(): bool
    {
        return count($this->getFillable()) === 0 && $this->getGuarded() == ['*'];
    }

    /**
     * Get the fillable attributes of a given array.
     *
     * @param array $attributes
     * @return array
     */
    protected function fillableFromArray(array $attributes): array
    {
        if (count($this->getFillable()) > 0 && !static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->getFillable()));
        }

        return $attributes;
    }
}