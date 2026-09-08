<?php

namespace Elegant\Database\Model\Concerns;

trait HasTrashed
{
    private string $trashed = 'without';

    /**
     * Get data with null or is not null base on trashed variable
     *
     * @return $this
     */
    protected function whereTrashed(): self
    {
        switch ($this->trashed) {
            case 'only':
                $this->database->where($this->database->dbprefix($this->getTable()) . '.' . $this->deleted_at_column . ' IS NOT NULL', null, false);
                break;
            case 'without':
                $this->database->where($this->database->dbprefix($this->getTable()) . '.' . $this->deleted_at_column . ' IS NULL', null, false);
                break;
            case 'with':
                break;
        }
        //$this->trashed = ''; issue #208...
        return $this;
    }

    /**
     * Reset trashed data
     *
     * @return $this
     */
    private function resetTrashed(): self
    {
        return $this->withoutTrashed();
    }

    /**
     * Add the with-trashed extension to the builder.
     *
     * @return $this
     */
    public function withTrashed(): self
    {
        $this->trashed = 'with';

        return $this;
    }

    /**
     * Add the without-trashed extension to the builder.
     *
     * @return $this
     */
    public function withoutTrashed(): self
    {
        $this->trashed = 'without';

        return $this;
    }

    /**
     * Add the only-trashed extension to the builder.
     *
     * @return $this
     */
    public function onlyTrashed(): self
    {
        $this->trashed = 'only';

        return $this;
    }
}
