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
    public function url(int $page): string;

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
     * Get the "index" of the first item being paginated.
     *
     * @return int|null
     */
    public function firstItem(): ?int;

    /**
     * Get the "index" of the last item being paginated.
     *
     * @return int|null
     */
    public function lastItem(): ?int;

    /**
     * Get the number of items shown per page.
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
     * Determine if the paginator is on the first page.
     *
     * @return bool
     */
    public function onFirstPage(): bool;

    /**
     * Get the current page.
     *
     * @return int
     */
    public function currentPage(): int;

    /**
     * Get the query string variable used to store the page.
     *
     * @return string
     */
    public function getPageName(): string;

    /**
     * Set the query string variable used to store the page.
     *
     * @param string $name
     * @return $this
     */
    public function setPageName(string $name);
}

