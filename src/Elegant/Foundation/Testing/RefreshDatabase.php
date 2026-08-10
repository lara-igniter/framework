<?php

namespace Elegant\Foundation\Testing;

/**
 * Refresh the test database once per PHPUnit process (Laravel-style).
 */
trait RefreshDatabase
{
    /**
     * @return void
     */
    public function refreshDatabase()
    {
        if (RefreshDatabaseState::$migrated) {
            return;
        }

        $this->runArtisan(['migrate:fresh', '--force']);

        RefreshDatabaseState::$migrated = true;
    }
}
