<?php

namespace Elegant\Database\Model\Concerns;

trait Cancellable
{
    /**
     * Initialize the cancellable trait for an instance.
     *
     * @return void
     */
    protected function initializeCancellable(): void
    {
        if (!isset($this->casts['cancelled_at'])) {
            $this->casts['cancelled_at'] = 'datetime';
        }
    }

    /**
     * Determine if the model has been cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return !is_null($this->cancelled_at);
    }
}
