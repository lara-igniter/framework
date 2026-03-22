<?php

namespace Elegant\Pagination;

use Closure;
use Elegant\Contracts\Support\Htmlable;
use Elegant\Support\Arr;
use Elegant\Support\Collection;
use Elegant\Support\Traits\ForwardsCalls;
use Elegant\Support\Traits\Tappable;
use Traversable;

/**
 * @mixin \Elegant\Support\Collection
 */
abstract class AbstractPaginator implements Htmlable
{
    use ForwardsCalls, Tappable;

    /**
     * All of the items being paginated.
     *
     * @var \Elegant\Support\Collection
     */
    protected Collection $items;

    /**
     * The number of items to be shown per page.
     *
     * @var int
     */
    protected int $perPage;

    /**
     * The current page being "viewed".
     *
     * @var int
     */
    protected int $currentPage;

    /**
     * The base path to assign to all URLs.
     *
     * @var string
     */
    protected string $path = '/';

    /**
     * The query parameters to add to all URLs.
     *
     * @var array
     */
    protected array $query = [];

    /**
     * The URL fragment to add to all URLs.
     *
     * @var string|null
     */
    protected ?string $fragment;

    /**
     * The query string variable used to store the page.
     *
     * @var string
     */
    protected string $pageName = 'page';

    /**
     * The number of links to display on each side of current page link.
     *
     * @var int
     */
    public int $onEachSide = 2;

    /**
     * The paginator options.
     *
     * @var array
     */
    protected array $options;

    /**
     * The current path resolver callback.
     *
     * @var \Closure
     */
    protected static Closure $currentPathResolver;

    /**
     * The current page resolver callback.
     *
     * @var \Closure
     */
    protected static Closure $currentPageResolver;

    /**
     * The query string resolver callback.
     *
     * @var \Closure
     */
    protected static Closure $queryStringResolver;

    /**
     * The view factory resolver callback.
     *
     * @var \Closure
     */
    protected static Closure $viewFactoryResolver;

    /**
     * The default pagination view.
     *
     * @var string
     */
    public static string $defaultView = 'pagination::bootstrap-5';

    /**
     * The default "simple" pagination view.
     *
     * @var string
     */
    public static string $defaultSimpleView = 'pagination::simple-bootstrap-5';

    /**
     * Determine if the given value is a valid page number.
     *
     * @param int $page
     * @return bool
     */
    protected function isValidPageNumber(int $page): bool
    {
        return $page >= 1 && filter_var($page, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Get the URL for the previous page.
     *
     * @return string|null
     */
    public function previousPageUrl(): ?string
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }
    }

    /**
     * Create a range of pagination URLs.
     *
     * @param int $start
     * @param int $end
     * @return array
     */
    public function getUrlRange(int $start, int $end): array
    {
        return collect(range($start, $end))->mapWithKeys(function ($page) {
            return [$page => $this->url($page)];
        })->all();
    }

    /**
     * Get the URL for a given page number.
     *
     * @param int $page
     * @return string
     */
    public function url(int $page): string
    {
        if ($page <= 0) {
            $page = 1;
        }

        // Page 1 is implied by the base URL — never append ?page=1.
        $parameters = $page > 1 ? [$this->pageName => $page] : [];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        if (empty($parameters)) {
            return $this->path() . $this->buildFragment();
        }

        return $this->path()
            . (str_contains($this->path(), '?') ? '&' : '?')
            . Arr::query($parameters)
            . $this->buildFragment();
    }

    /**
     * Get / set the URL fragment to be appended to URLs.
     *
     * @param string|null $fragment
     * @return $this|string|null
     */
    public function fragment(string $fragment = null)
    {
        if (is_null($fragment)) {
            return $this->fragment;
        }

        $this->fragment = $fragment;

        return $this;
    }

    /**
     * Add a set of query string values to the paginator.
     *
     * @param array|string|null $key
     * @param string|null $value
     * @return $this
     */
    public function appends($key, string $value = null): AbstractPaginator
    {
        if (is_null($key)) {
            return $this;
        }

        if (is_array($key)) {
            return $this->appendArray($key);
        }

        return $this->addQuery($key, $value);
    }

    /**
     * Add an array of query string values.
     *
     * @param array $keys
     * @return $this
     */
    protected function appendArray(array $keys): AbstractPaginator
    {
        foreach ($keys as $key => $value) {
            $this->addQuery($key, $value);
        }

        return $this;
    }

    /**
     * Add all current query string values to the paginator.
     *
     * @return $this
     */
    public function withQueryString(): AbstractPaginator
    {
        if (isset(static::$queryStringResolver)) {
            return $this->appends(call_user_func(static::$queryStringResolver));
        }

        return $this;
    }

    /**
     * Add a query string value to the paginator.
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    protected function addQuery(string $key, string $value): AbstractPaginator
    {
        if ($key !== $this->pageName) {
            $this->query[$key] = $value;
        }

        return $this;
    }

    /**
     * Build the full fragment portion of a URL.
     *
     * @return string
     */
    protected function buildFragment(): string
    {
        return $this->fragment ? '#' . $this->fragment : '';
    }

    /**
     * Get the slice of items being paginated.
     *
     * @return array
     */
    public function items(): array
    {
        return $this->items->all();
    }

    /**
     * Get the number of the first item in the slice.
     *
     * @return int|null
     */
    public function firstItem(): ?int
    {
        return count($this->items) > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
    }

    /**
     * Get the number of the last item in the slice.
     *
     * @return int|null
     */
    public function lastItem(): ?int
    {
        return count($this->items) > 0 ? $this->firstItem() + $this->count() - 1 : null;
    }

    /**
     * Transform each item in the slice of items using a callback.
     *
     * @param callable $callback
     * @return $this
     */
    public function through(callable $callback): AbstractPaginator
    {
        $this->items->transform($callback);

        return $this;
    }

    /**
     * Get the number of items shown per page.
     *
     * @return int
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Determine if there are enough items to split into multiple pages.
     *
     * @return bool
     */
    public function hasPages(): bool
    {
        return $this->currentPage() != 1 || $this->hasMorePages();
    }

    /**
     * Determine if the paginator is on the first page.
     *
     * @return bool
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage() <= 1;
    }

    /**
     * Determine if the paginator is on the last page.
     *
     * @return bool
     */
    public function onLastPage(): bool
    {
        return !$this->hasMorePages();
    }

    /**
     * Get the current page.
     *
     * @return int
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get the query string variable used to store the page.
     *
     * @return string
     */
    public function getPageName(): string
    {
        return $this->pageName;
    }

    /**
     * Set the query string variable used to store the page.
     *
     * @param string $name
     * @return $this
     */
    public function setPageName(string $name): AbstractPaginator
    {
        $this->pageName = $name;

        return $this;
    }

    /**
     * Set the base path to assign to all URLs.
     *
     * @param string $path
     * @return $this
     */
    public function withPath(string $path): AbstractPaginator
    {
        return $this->setPath($path);
    }

    /**
     * Set the base path to assign to all URLs.
     *
     * @param string $path
     * @return $this
     */
    public function setPath(string $path): AbstractPaginator
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Set the number of links to display on each side of current page link.
     *
     * @param int $count
     * @return $this
     */
    public function onEachSide(int $count): AbstractPaginator
    {
        $this->onEachSide = $count;

        return $this;
    }

    /**
     * Get the base path for paginator generated URLs.
     *
     * @return string|null
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * Resolve the current request path or return the default value.
     *
     * @param string $default
     * @return string
     */
    public static function resolveCurrentPath(string $default = '/'): string
    {
        if (isset(static::$currentPathResolver)) {
            return call_user_func(static::$currentPathResolver);
        }

        return $default;
    }

    /**
     * Set the current request path resolver callback.
     *
     * @param \Closure $resolver
     * @return void
     */
    public static function currentPathResolver(Closure $resolver)
    {
        static::$currentPathResolver = $resolver;
    }

    /**
     * Resolve the current page or return the default value.
     *
     * @param string $pageName
     * @param int $default
     * @return int
     */
    public static function resolveCurrentPage(string $pageName = 'page', int $default = 1): int
    {
        if (isset(static::$currentPageResolver)) {
            return (int)call_user_func(static::$currentPageResolver, $pageName);
        }

        return $default;
    }

    /**
     * Set the current page resolver callback.
     *
     * @param \Closure $resolver
     * @return void
     */
    public static function currentPageResolver(Closure $resolver)
    {
        static::$currentPageResolver = $resolver;
    }

    /**
     * Resolve the query string or return the default value.
     *
     * @param string|array|null $default
     * @return string|array|null
     */
    public static function resolveQueryString($default = null)
    {
        if (isset(static::$queryStringResolver)) {
            return (static::$queryStringResolver)();
        }

        return $default;
    }

    /**
     * Set with query string resolver callback.
     *
     * @param \Closure $resolver
     * @return void
     */
    public static function queryStringResolver(Closure $resolver)
    {
        static::$queryStringResolver = $resolver;
    }

    /**
     * Get an instance of the view factory from the resolver.
     *
     * @return \Elegant\Contracts\View\Factory
     */
    public static function viewFactory(): \Elegant\Contracts\View\Factory
    {
        return call_user_func(static::$viewFactoryResolver);
    }

    /**
     * Set the view factory resolver callback.
     *
     * @param \Closure $resolver
     * @return void
     */
    public static function viewFactoryResolver(Closure $resolver)
    {
        static::$viewFactoryResolver = $resolver;
    }

    /**
     * Set the default pagination view.
     *
     * @param string $view
     * @return void
     */
    public static function defaultView(string $view)
    {
        static::$defaultView = $view;
    }

    /**
     * Set the default "simple" pagination view.
     *
     * @param string $view
     * @return void
     */
    public static function defaultSimpleView(string $view)
    {
        static::$defaultSimpleView = $view;
    }

    /**
     * Indicate that Bootstrap 4 styling should be used for generated links.
     *
     * @return void
     */
    public static function useBootstrap()
    {
        static::useBootstrapFour();
    }

    /**
     * Indicate that Bootstrap 3 styling should be used for generated links.
     *
     * @return void
     */
    public static function useBootstrapThree()
    {
        static::defaultView('pagination::default');
        static::defaultSimpleView('pagination::simple-default');
    }

    /**
     * Indicate that Bootstrap 4 styling should be used for generated links.
     *
     * @return void
     */
    public static function useBootstrapFour()
    {
        static::defaultView('pagination::bootstrap-4');
        static::defaultSimpleView('pagination::simple-bootstrap-4');
    }

    /**
     * Indicate that Bootstrap 5 styling should be used for generated links.
     *
     * @return void
     */
    public static function useBootstrapFive()
    {
        static::defaultView('pagination::bootstrap-5');
        static::defaultSimpleView('pagination::simple-bootstrap-5');
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): Traversable
    {
        return $this->items->getIterator();
    }

    /**
     * Determine if the list of items is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Determine if the list of items is not empty.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return $this->items->isNotEmpty();
    }

    /**
     * Get the number of items for the current page.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * Get the paginator's underlying collection.
     *
     * @return \Elegant\Support\Collection
     */
    public function getCollection(): Collection
    {
        return $this->items;
    }

    /**
     * Set the paginator's underlying collection.
     *
     * @param \Elegant\Support\Collection $collection
     * @return $this
     */
    public function setCollection(Collection $collection): AbstractPaginator
    {
        $this->items = $collection;

        return $this;
    }

    /**
     * Get the paginator options.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Determine if the given item exists.
     *
     * @param mixed $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return $this->items->has($key);
    }

    /**
     * Get the item at the given offset.
     *
     * @param mixed $key
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->items->get($key);
    }

    /**
     * Set the item at the given offset.
     *
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        $this->items->put($key, $value);
    }

    /**
     * Unset the item at the given key.
     *
     * @param mixed $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        $this->items->forget($key);
    }

    /**
     * Render the contents of the paginator to HTML.
     *
     * @return string
     */
    public function toHtml(): string
    {
        return (string)$this->render();
    }

    /**
     * Make dynamic calls into the collection.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo($this->getCollection(), $method, $parameters);
    }

    /**
     * Render the contents of the paginator when casting to a string.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->render();
    }
}

