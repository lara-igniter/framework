<?php

namespace Elegant\Foundation\Testing;

use Elegant\Database\Migrations\Migrator;
use RuntimeException;

/**
 * Refresh the test database once per PHPUnit process (Laravel-style).
 *
 * Testing must use sqlite3 `:memory:` (or a `*.sqlite` file). In-process
 * migrate:fresh is required for `:memory:` — a subprocess artisan call
 * would open a different empty database.
 */
trait RefreshDatabase
{
    /**
     * @return void
     */
    public function refreshDatabase()
    {
        $this->assertSafeTestingDatabase();
        $this->ensureCodeIgniterBootedForDatabase();

        if (RefreshDatabaseState::$migrated) {
            $this->truncateSqliteTestingTables();

            return;
        }

        (new Migrator())
            ->setOutput(static function (): void {
            })
            ->fresh();

        $this->clearSettingsFileCache();

        RefreshDatabaseState::$migrated = true;
    }

    /**
     * Avoid leaking the host app's cached settings into the sqlite test DB.
     *
     * @return void
     */
    protected function clearSettingsFileCache(): void
    {
        if (! function_exists('get_instance')) {
            return;
        }

        try {
            $ci = get_instance();
        } catch (\Throwable $e) {
            return;
        }

        if ($ci && isset($ci->cache) && isset($ci->cache->file) && method_exists($ci->cache->file, 'delete')) {
            $ci->cache->file->delete('settings');
        }
    }

    /**
     * Refuse to refresh anything except the isolated sqlite test database.
     *
     * @return void
     */
    protected function assertSafeTestingDatabase(): void
    {
        $driver = (string) (getenv('DB_CONNECTION') ?: '');
        $database = (string) (getenv('DB_DATABASE') ?: '');

        $isSqlite = in_array($driver, ['sqlite', 'sqlite3'], true);
        $isMemory = $database === ':memory:';
        $isSqliteFile = (bool) preg_match('/\.sqlite3?$/', $database);

        if (! $isSqlite || (! $isMemory && ! $isSqliteFile)) {
            throw new RuntimeException(
                "Refusing to refresh database: expected sqlite :memory: (or *.sqlite), got [{$driver}] database [{$database}]."
            );
        }
    }

    /**
     * CodeIgniter (and its DB singleton) only exists after the first HTTP dispatch.
     *
     * @return void
     */
    protected function ensureCodeIgniterBootedForDatabase(): void
    {
        if ($this->codeIgniterDatabaseIsReady()) {
            return;
        }

        $response = $this->get('/login');

        if ($this->codeIgniterDatabaseIsReady()) {
            return;
        }

        $this->connectCodeIgniterDatabase();

        if ($this->codeIgniterDatabaseIsReady()) {
            return;
        }

        $this->fail(
            'CodeIgniter did not boot a database connection; cannot refresh the sqlite test database.'
            . $this->describeCodeIgniterDatabaseFailure($response)
        );
    }

    /**
     * Autoload may have failed before DB connected; retry on the live CI instance.
     *
     * @return void
     */
    protected function connectCodeIgniterDatabase(): void
    {
        if (! function_exists('get_instance')) {
            return;
        }

        try {
            $ci = get_instance();
        } catch (\Throwable $e) {
            return;
        }

        if (! $ci || ! isset($ci->load) || ! method_exists($ci->load, 'database')) {
            return;
        }

        try {
            $ci->load->database();
        } catch (\Throwable $e) {
            // Failure details are appended to the PHPUnit message.
        }
    }

    /**
     * @param mixed $response
     * @return string
     */
    protected function describeCodeIgniterDatabaseFailure($response): string
    {
        $parts = [];

        if (is_object($response) && method_exists($response, 'getStatusCode')) {
            $parts[] = 'login status ' . $response->getStatusCode();
        }

        if (is_object($response) && method_exists($response, 'getContent')) {
            $content = trim(preg_replace('/\s+/', ' ', strip_tags((string) $response->getContent())));
            if ($content !== '') {
                $parts[] = substr($content, 0, 500);
            }
        }

        $ciState = 'no CI instance';

        if (function_exists('get_instance')) {
            try {
                $ci = get_instance();
                if ($ci && isset($ci->db) && is_object($ci->db)) {
                    $driver = (string) ($ci->db->dbdriver ?? '');
                    $connected = ! empty($ci->db->conn_id) ? 'yes' : 'no';
                    $ciState = "dbdriver [{$driver}] connected [{$connected}]";
                } elseif ($ci) {
                    $ciState = 'CI booted without $db';
                }
            } catch (\Throwable $e) {
                $ciState = $e->getMessage();
            }
        }

        $parts[] = $ciState;

        return ' (' . implode('; ', $parts) . ')';
    }

    /**
     * @return bool
     */
    protected function codeIgniterDatabaseIsReady(): bool
    {
        if (! function_exists('get_instance')) {
            return false;
        }

        try {
            $ci = get_instance();
        } catch (\Throwable $e) {
            return false;
        }

        return $ci
            && isset($ci->db)
            && is_object($ci->db)
            && ! empty($ci->db->dbdriver)
            && ! empty($ci->db->conn_id);
    }

    /**
     * Wipe application tables via the CI query builder (same connection MY_Model uses).
     *
     * @return void
     */
    protected function truncateSqliteTestingTables(): void
    {
        $db = get_instance()->db;

        $db->query('PRAGMA foreign_keys = OFF');

        foreach ($db->list_tables() as $table) {
            if ($table === 'sqlite_sequence' || preg_match('/migrations$/', (string) $table)) {
                continue;
            }

            $db->query('DELETE FROM ' . $db->escape_identifiers($table));
        }

        if ($db->table_exists('sqlite_sequence')) {
            $db->query('DELETE FROM sqlite_sequence');
        }

        $db->query('PRAGMA foreign_keys = ON');
        $db->reset_query();
    }
}
