<?php

namespace Elegant\Contracts\Pagination;

interface CursorPaginator
{
    /**
     * Get the URL for a given cursor.
     *
     * @param \Elegant\Pagination\Cursor|null $cursor
     * @return string
     */
    public function url($cursor);

    /**
     * Add a set of query string values to the paginator.
     *
     * @param array|string|null $key
     * @param string|null $value
     * @return $this
     */
    public function appends($key, $value = null);

    /**
     * Get / set the URL fragment to be appended to URLs.
     *
     * @param string|null $fragment
     * @return $this|string|null
     */
    public function fragment($fragment = null);

    /**
     * The URL for the next page, or null.
     *
     * @return string|null
     */
    public function nextPageUrl();

    /**
     * Get the URL for the previous page, or null.
     *
     * @return string|null
     */
    public function previousPageUrl();

    /**
     * Get all of the items being paginated.
     *
     * @return array
     */
    public function items();

    /**
     * Get the number of items being shown per page.
     *
     * @return int
     */
    public function perPage();

    /**
     * Determine if there are enough items to split into multiple pages.
     *
     * @return bool
     */
    public function hasPages();

    /**
     * Determine if there are more items in the data source.
     *
     * @return bool
     */
    public function hasMorePages();

    /**
     * Get the base path for paginator generated URLs.
     *
     * @return string|null
     */
    public function path();

    /**
     * Determine if the list of items is empty.
     *
     * @return bool
     */
    public function isEmpty();

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
    public function render($view = null, $data = []);
}

