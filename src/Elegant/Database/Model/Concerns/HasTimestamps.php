<?php

namespace Elegant\Database\Model\Concerns;

use Elegant\Support\Facades\Date;

trait HasTimestamps
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public bool $timestamps = true;

    /**
     * The "created at" attribute.
     *
     * @var string
     */
    protected string $created_at_column;

    /**
     * The "updated at" attribute.
     *
     * @var string
     */
    protected string $updated_at_column;

    protected function initializeHasTimestamps()
    {
        foreach ($this->getDates() as $date) {
            if (!isset($this->casts[$this->{$date . '_column'}])) {
                $this->casts[$this->{$date . '_column'}] = 'datetime';
            }
        }
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return \Elegant\Support\Carbon
     */
    public function freshTimestamp()
    {
        return Date::now();
    }

    /**
     * Get a fresh timestamp formatted for the model.
     *
     * @return string
     */
    public function freshTimestampString(): string
    {
        return $this->fromDateTime($this->freshTimestamp());
    }

    /**
     * Determine if the model uses timestamps.
     *
     * @return bool
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string|null
     */
    public function getCreatedAtColumn(): ?string
    {
        return static::CREATED_AT;
    }

    /**
     * Set the name of the "created at" column.
     *
     * @param mixed $value
     */
    public function setCreatedAtColumn($value)
    {
        $this->{$this->getCreatedAtColumn() . '_column'} = $value;
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string|null
     */
    public function getUpdatedAtColumn(): ?string
    {
        return static::UPDATED_AT;
    }

    /**
     * Set the name of the "updated at" column.
     *
     * @param mixed $value
     */
    public function setUpdatedAtColumn($value)
    {
        $this->{$this->getUpdatedAtColumn() . '_column'} = $value;
    }
}