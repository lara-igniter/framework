<?php

namespace Elegant\Queue\Jobs;

use Elegant\Support\InteractsWithTime;
use stdClass;

class DatabaseJobRecord extends stdClass
{
    use InteractsWithTime;

    /**
     * The underlying job record.
     *
     * @var stdClass
     */
    protected stdClass $record;

    /**
     * Create a new job record instance.
     *
     * @param stdClass $record
     * @return void
     */
    public function __construct(stdClass $record)
    {
        $this->record = $record;
    }

    /**
     * Increment the number of times the job has been attempted.
     *
     * @return int
     */
    public function increment(): int
    {
        $this->record->attempts++;

        return $this->record->attempts;
    }

    /**
     * Update the "reserved at" timestamp of the job.
     *
     * @return int
     */
    public function touch(): int
    {
        $this->record->reserved_at = $this->currentTime();

        return $this->record->reserved_at;
    }

    /**
     * Dynamically access the underlying job information.
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->record->{$key};
    }
}
