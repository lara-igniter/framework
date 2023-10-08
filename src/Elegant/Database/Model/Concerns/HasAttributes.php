<?php

namespace Elegant\Database\Model\Concerns;

use Elegant\Support\Carbon;
use Elegant\Support\Facades\Date;
use InvalidArgumentException;
use Elegant\Database\Model\Model;

trait HasAttributes
{
    /**
     * The model's attributes.
     *
     * @var array
     */
//    protected array $attributes = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected array $casts = [];

    /**
     * The built-in, primitive cast types supported by Eloquent.
     *
     * @var string[]
     */
    protected static array $primitiveCastTypes = [
        'custom_datetime',
        'date',
        'datetime',
        'timestamp',
    ];

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param string $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute(string $key, $value)
    {
        $castType = $this->getCastType($key);

        if (is_null($value) && in_array($castType, static::$primitiveCastTypes)) {
            return null;
        }

        switch ($castType) {
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asTimestamp($value);
        }

        return $value;
    }

    /**
     * Return a timestamp as DateTime object with time set to 00:00:00.
     *
     * @param  mixed  $value
     * @return \Elegant\Support\Carbon
     */
    protected function asDate($value)
    {
        return $this->asDateTime($value)->startOfDay();
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return \Elegant\Support\Carbon
     */
    protected function asDateTime($value)
    {
        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Date::createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if ($this->isStandardDateFormat($value)) {
            return Date::instance(Carbon::createFromFormat('Y-m-d', $value)->startOfDay());
        }


        $format = $this->getDateFormat();

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        try {
            $date = Date::createFromFormat($format, $value);
        } catch (InvalidArgumentException $e) {
            $date = false;
        }

        return $date ?: Date::parse($value);
    }

    /**
     * Return a timestamp as unix timestamp.
     *
     * @param  mixed  $value
     * @return int
     */
    protected function asTimestamp($value): int
    {
        return $this->asDateTime($value)->getTimestamp();
    }

    /**
     * Determine if the cast type is a custom date time cast.
     *
     * @param  string  $cast
     * @return bool
     */
    protected function isCustomDateTimeCast(string $cast): bool
    {
        return strncmp($cast, 'date:', 5) === 0 ||
            strncmp($cast, 'datetime:', 9) === 0;
    }

    /**
     * Determine if the given value is a standard date format.
     *
     * @param  string  $value
     * @return bool
     */
    protected function isStandardDateFormat(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }

    /**
     * Convert a DateTime to a storable string.
     *
     * @param  mixed  $value
     * @return string|null
     */
    public function fromDateTime($value): ?string
    {
        return empty($value) ? $value : $this->asDateTime($value)->format(
            $this->getDateFormat()
        );
    }
    /**
     * Get a given attribute on the model.
     *
     * @param $data
     * @param $name
     * @param $callback
     * @return mixed
     */
    protected function getAttribute($data, $name, $callback)
    {
        if (isset($data[$name])) {
            if (count($data) == count($data, COUNT_RECURSIVE)) {
                $data[$name] = is_callable($callback)
                    ? call_user_func($callback, $data[$name])
                    : $callback;
            } else {
                foreach ($data as &$item) {
                    $item[$name] = is_callable($callback)
                        ? call_user_func($callback, $item[$name])
                        : $callback;
                }
            }
        }

        return $data;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param $data
     * @param $name
     * @param $callback
     * @return mixed
     */
    protected function setAttribute($data, $name, $callback)
    {
        if (isset($data[$name])) {
            $data[$name] = is_callable($callback)
                ? call_user_func($callback, is_string($data)
                    ? $data
                    : $data[$name]
                ) : $callback;
        } elseif (is_callable($callback)) {
            $data[$name] = call_user_func($callback, $data);
        }

        return $data;
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param  string  $key
     * @return string
     */
    protected function getCastType(string $key): string
    {
        if(!isset($this->getCasts()[$key])) {
            return '';
        }

        if ($this->isCustomDateTimeCast($this->getCasts()[$key])) {
            return 'custom_datetime';
        }

        return trim(strtolower($this->getCasts()[$key]));
    }

    /**
     * Get the attributes that should be converted to dates.
     *
     * @return array
     */
    public function getDates(): array
    {
        return [
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ];
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }


    /**
     * Set the date format used by the model.
     *
     * @param string $format
     * @return Model
     */
    public function setDateFormat(string $format): Model
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     *
     * @param  string  $key
     * @param  array|string|null  $types
     * @return bool
     */
    public function hasCast($key, $types = null): bool
    {
        if (array_key_exists($key, $this->getCasts())) {
            return $types ? in_array($this->getCastType($key), (array) $types, true) : true;
        }

        return false;
    }

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts(): array
    {
        return $this->casts;
    }
}