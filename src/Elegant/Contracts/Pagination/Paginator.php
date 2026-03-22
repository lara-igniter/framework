<?php

namespace Elegant\Contracts\Pagination;

interface Paginator
{
    /**
     * Get the URL for a given page.
     *
     * @param int $page
     * @return string
     */
    public function url($page);

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
     * Get the "index" of the first item being paginated.
     *
     * @return int|null
     */
    public function firstItem();

    /**
     * Get the "index" of the last item being paginated.
     *
     * @return int|null
     */
    public function lastItem();

    /**
     * Get the number of items shown per page.
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
     * Determine if the paginator is on the first page.
     *
     * @return bool
     */
    public function onFirstPage();

    /**
     * Get the current page.
     *
     * @return int
     */
    public function currentPage();

    /**
     * Get the query string variable used to store the page.
     *
     * @return string
     */
    public function getPageName();

    /**
     * Set the query string variable used to store the page.
     *
     * @param string $name
     * @return $this
     */
    public function setPageName($name);
}

