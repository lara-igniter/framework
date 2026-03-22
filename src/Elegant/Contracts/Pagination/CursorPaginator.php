<?php

namespace Elegant\Contracts\Pagination;

use Elegant\Pagination\Cursor;

interface CursorPaginator
{
    /**
     * Get the URL for a given cursor.
     *
     * @param \Elegant\Pagination\Cursor|null $cursor
     * @return string
     */
    public function url(?Cursor $cursor): string;

    /**
     * Add a set of query string values to the paginator.
     *
     * @param array|string|null $key
     * @param string|null $value
     * @return $this
     */
    public function appends($key, string $value = null);

    /**
     * Get / set the URL fragment to be appended to URLs.
     *
     * @param string|null $fragment
     * @return $this|string|null
     */
    public function fragment(string $fragment = null);

    /**
     * The URL for the next page, or null.
     *
     * @return string|null
     */
    public function nextPageUrl(): ?string;

    /**
     * Get the URL for the previous page, or null.
     *
     * @return string|null
     */
    public function previousPageUrl(): ?string;

    /**
     * Get all of the items being paginated.
     *
     * @return array
     */
    public function items(): array;

    /**
     * Get the number of items being shown per page.
     *
     * @return int
     */
    public function perPage(): int;

    /**
     * Determine if there are enough items to split into multiple pages.
     *
     * @return bool
     */
    public function hasPages(): bool;

    /**
     * Determine if there are more items in the data source.
     *
     * @return bool
     */
    public function hasMorePages(): bool;

    /**
     * Get the base path for paginator generated URLs.
     *
     * @return string|null
     */
    public function path(): ?string;

    /**
     * Determine if the list of items is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Get the number of items for the current page.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Render the paginator using the given view.
     *
     * @param string|null $view
     * @param array $data
     * @return mixed
     */
    public function render(string $view = null, array $data = []);
}

