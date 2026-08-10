<?php

namespace Elegant\Foundation\Testing;

/**
 * Note: true per-test DB transactions do not span the HTTP subprocess.
 * Prefer RefreshDatabase for feature tests that hit the database.
 */
trait DatabaseTransactions
{
    /**
     * @return void
     */
    public function beginDatabaseTransaction()
    {
        // Intentionally no-op for subprocess HTTP testing.
    }
}
