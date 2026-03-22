<?php

namespace Elegant\Contracts\Pagination;

interface LengthAwarePaginator extends Paginator
{
    /**
     * Create a range of pagination URLs.
     *
     * @param int $start
     * @param int $end
     * @return array
     */
    public function getUrlRange(int $start, int $end): array;

    /**
     * Determine the total number of items in the data store.
     *
     * @return int
     */
    public function total(): int;

    /**
     * Get the page number of the last available page.
     *
     * @return int
     */
    public function lastPage(): int;
}

