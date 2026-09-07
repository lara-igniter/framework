<?php

namespace Elegant\Foundation\Testing\Concerns;

trait InteractsWithDatabase
{
    /**
     * Clear leftover query-builder state on the shared CI DB singleton.
     *
     * @return $this
     */
    protected function resetQuery()
    {
        if (! function_exists('get_instance')) {
            return $this;
        }

        try {
            $ci = get_instance();
        } catch (\Throwable $e) {
            return $this;
        }

        if ($ci && isset($ci->db) && method_exists($ci->db, 'reset_query')) {
            $ci->db->reset_query();
        }

        return $this;
    }
}
