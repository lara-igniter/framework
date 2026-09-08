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
        if(isset($this->soft_deletes) && $this->soft_deletes) {
            $this->setDeletedAtColumn($this->getDeletedAtColumn());
        }

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
    public function forceDelete($where = null): bool
    {
        if (isset($where)) {
            $this->where($where);
        }

        // Read through a clone so the original builder preserves any chained
        // where clauses for the subsequent DELETE statement.
        $query = (clone $this->database)->get($this->table);
        $models = $query->result();

        foreach ($models as $model) {
            static::forceDeleting((array) $model);
        }

        try {
            if (! $this->database->delete($this->table)) {
                return false;
            }

            if ($this->database->affected_rows() > 0 && $models !== []) {
                static::forceDeleted($models);
            }

            return $this->database->affected_rows() > 0;
        } finally {
            $this->database->reset_query();
        }
    }

    /**
     * Force delete the model in the database without raising any events.
     *
     * @param null $where
     * @return bool|null
     */
    public function forceDeleteQuietly($where = null): bool
    {
        return static::withoutEvents(function () use ($where) {
            return $this->forceDelete($where);
        });
    }

    /**
     * Restore a soft-deleted model instance.
     *
     * @param null $where
     * @return bool
     */
    public function restore($where = null): bool
    {
        $this->withTrashed();

        if (isset($where)) {
            $this->where($where);
        }

        // Read through a clone so the original builder preserves any chained
        // where clauses for the subsequent UPDATE statement. Only rows that
        // are actually soft-deleted can produce a restored activity.
        $restoredQuery = clone $this->database;
        $restoredQuery->where(
            $restoredQuery->dbprefix($this->getTable()) . '.' . $this->getDeletedAtColumn() . ' IS NOT NULL',
            null,
            false
        );
        $models = $restoredQuery->get($this->table)->result();

        $data = static::restoring([
            $this->getDeletedAtColumn() => null
        ]);

        try {
            if (! $this->database->update($this->table, $data)) {
                return false;
            }

            if ($this->database->affected_rows() > 0 && $models !== []) {
                static::restored($models);
            }

            return true;
        } finally {
            $this->database->reset_query();
        }
    }

    /**
     * Restore a soft-deleted model instance without raising any events.
     *
     * @param null $where
     * @return bool
     */
    public function restoreQuietly($where = null): bool
    {
        return self::withoutEvents(function () use ($where) {
            return $this->restore($where);
        });
    }

    /**
     * Determine if the model instance has been soft-deleted.
     *
     * @return bool
     */
    public function trashed($where = null)
    {
        $this->onlyTrashed();

        if (isset($where)) {
            $this->where($where);
        }

        $this->limit(1);

        $query = $this->database->get($this->table);

        if ($query->num_rows() == 1) {
            return true;
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
        $this->{$this->getDeletedAtColumn(). '_column'} = $value;
    }
}
