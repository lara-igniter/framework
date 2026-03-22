<?php

namespace Elegant\Pagination;

/**
 * Binds the pagination state resolvers using CI/Laraigniter conventions.
 */
class PaginationState
{
    /**
     * Bind the pagination state resolvers.
     *
     * @return void
     */
    public static function resolveUsing()
    {
        // Resolve the view factory via the Laraigniter app container.
        Paginator::viewFactoryResolver(function () {
            return app('view');
        });

        LengthAwarePaginator::viewFactoryResolver(function () {
            return app('view');
        });

        // Resolve the current URL path — strip the query string.
        Paginator::currentPathResolver(function () {
            return static::currentPath();
        });

        LengthAwarePaginator::currentPathResolver(function () {
            return static::currentPath();
        });

        // Resolve the current page from $_GET.
        Paginator::currentPageResolver(function ($pageName = 'page') {
            return static::currentPage($pageName);
        });

        LengthAwarePaginator::currentPageResolver(function ($pageName = 'page') {
            return static::currentPage($pageName);
        });

        // Resolve the full query string (excluding the page parameter).
        Paginator::queryStringResolver(function () {
            return static::queryString();
        });

        LengthAwarePaginator::queryStringResolver(function () {
            return static::queryString();
        });

        // Cursor paginator — resolves current cursor from $_GET.
        CursorPaginator::currentCursorResolver(function ($cursorName = 'cursor') {
            return Cursor::fromEncoded($_GET[$cursorName] ?? null);
        });
    }

    /**
     * Get the current request URL without the query string.
     *
     * @return string
     */
    protected static function currentPath(): string
    {
        if (function_exists('current_url')) {
            $url = current_url();
        } else {
            $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        }

        return rtrim(strtok($url, '?'), '/') ?: '/';
    }

    /**
     * Get the current page number from the request.
     *
     * @param string $pageName
     * @return int
     */
    protected static function currentPage(string $pageName = 'page'): int
    {
        $page = $_GET[$pageName] ?? 1;

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int)$page >= 1) {
            return (int)$page;
        }

        return 1;
    }

    /**
     * Get the current query string as an array, excluding the page key.
     *
     * @param string $pageName
     * @return array
     */
    protected static function queryString(string $pageName = 'page'): array
    {
        return array_filter(
            $_GET ?? [],
            fn($key) => $key !== $pageName,
            ARRAY_FILTER_USE_KEY
        );
    }
}

