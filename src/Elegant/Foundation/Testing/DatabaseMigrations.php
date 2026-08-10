<?php

namespace Elegant\Foundation\Testing;

trait DatabaseMigrations
{
    /**
     * @return void
     */
    public function runDatabaseMigrations()
    {
        $this->runArtisan(['migrate', '--force']);

        $this->beforeApplicationDestroyed(function () {
            $this->runArtisan(['migrate:rollback', '--force']);
        });
    }
}
