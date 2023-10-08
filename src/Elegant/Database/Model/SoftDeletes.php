<?php

namespace Elegant\Database\Model;

trait SoftDeletes
{
    /** @var bool
     * Enables soft_deletes
     */
    protected bool $soft_deletes = true;

    /**
     * The "deleted at" attribute.
     *
     * @var string
     */
    protected string $deleted_at_column;

    /**
     * Initialize the soft deleting trait for an instance.
     *
     * @return void
     */
    protected function initializeSoftDeletes()
    {
        if (!isset($this->casts[$this->getDeletedAtColumn()])) {
            $this->casts[$this->getDeletedAtColumn()] = 'datetime';
        }
    }

    /**
     * Force a hard delete on a soft deleted model.
     *
     * @param null $where
     * @return bool|null
     */
    public function forceDelete($where = null): ?bool
    {
        if (isset($where)) {
            $this->where($where);
        }

        if ($this->database->delete($this->table)) {
            return $this->database->affected_rows();
        }

        return false;
    }

    /**
     * Restore a soft-deleted model instance.
     *
     * @param null $where
     * @return bool
     */
    public function restore($where = null): bool
    {
        $this->with_trashed();

        if (isset($where)) {
            $this->where($where);
        }

        if ($affected_rows = $this->database->update($this->table, [$this->getDeletedAtColumn() => null])) {
            return $affected_rows;
        }

        return false;
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getDeletedAtColumn(): string
    {
        return defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }

    /**
     * Set the name of the "created at" column.
     *
     * @param mixed $value
     */
    public function setDeletedAtColumn($value)
    {
        $this->{$this->getDeletedAtColumn() . '_column'} = $value;
    }
}