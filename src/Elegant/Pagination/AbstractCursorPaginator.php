<?php

namespace Elegant\Pagination;

use ArrayAccess;
use Closure;
use Exception;
use Elegant\Contracts\Support\Htmlable;
use Elegant\Support\Arr;
use Elegant\Support\Collection;
use Elegant\Support\Str;
use Elegant\Support\Traits\ForwardsCalls;
use Elegant\Support\Traits\Tappable;
use Traversable;

/**
 * @mixin \Elegant\Support\Collection
 */
abstract class AbstractCursorPaginator implements Htmlable
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
     * The cursor string variable used to store the page.
     *
     * @var string
     */
    protected string $cursorName = 'cursor';

    /**
     * The current cursor.
     *
     * @var \Elegant\Pagination\Cursor|null
     */
    protected ?Cursor $cursor;

    /**
     * The paginator parameters for the cursor.
     *
     * @var array
     */
    protected array $parameters;

    /**
     * The paginator options.
     *
     * @var array
     */
    protected array $options;

    /**
     * The current cursor resolver callback.
     *
     * @var \Closure
     */
    protected static Closure $currentCursorResolver;

    /**
     * Get the URL for a given cursor.
     *
     * @param \Elegant\Pagination\Cursor|null $cursor
     * @return string
     */
    public function url(?Cursor $cursor): string
    {
        $parameters = is_null($cursor) ? [] : [$this->cursorName => $cursor->encode()];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        return $this->path()
            . (str_contains($this->path(), '?') ? '&' : '?')
            . Arr::query($parameters)
            . $this->buildFragment();
    }

    /**
     * Get the URL for the previous page.
     *
     * @return string|null
     */
    public function previousPageUrl(): ?string
    {
        if (is_null($previousCursor = $this->previousCursor())) {
            return null;
        }

        return $this->url($previousCursor);
    }

    /**
     * The URL for the next page, or null.
     *
     * @return string|null
     */
    public function nextPageUrl(): ?string
    {
        if (is_null($nextCursor = $this->nextCursor())) {
            return null;
        }

        return $this->url($nextCursor);
    }

    /**
     * Get the "cursor" that points to the previous set of items.
     *
     * @return \Elegant\Pagination\Cursor|null
     */
    public function previousCursor(): ?Cursor
    {
        if (is_null($this->cursor) ||
            ($this->cursor->pointsToPreviousItems() && !$this->hasMore)) {
            return null;
        }

        return $this->getCursorForItem($this->items->first(), false);
    }

    /**
     * Get the "cursor" that points to the next set of items.
     *
     * @return \Elegant\Pagination\Cursor|null
     */
    public function nextCursor(): ?Cursor
    {
        if ((is_null($this->cursor) && !$this->hasMore) ||
            (!is_null($this->cursor) && $this->cursor->pointsToNextItems() && !$this->hasMore)) {
            return null;
        }

        return $this->getCursorForItem($this->items->last(), true);
    }

    /**
     * Get a cursor instance for the given item.
     *
     * @param \ArrayAccess|\stdClass $item
     * @param bool $isNext
     * @return \Elegant\Pagination\Cursor
     * @throws Exception
     */
    public function getCursorForItem($item, bool $isNext = true): Cursor
    {
        return new Cursor($this->getParametersForItem($item), $isNext);
    }

    /**
     * Get the cursor parameters for a given object.
     *
     * @param \ArrayAccess|\stdClass $item
     * @return array
     *
     * @throws \Exception
     */
    public function getParametersForItem($item): array
    {
        return collect($this->parameters)
            ->flip()
            ->map(function ($_, $parameterName) use ($item) {
                if ($item instanceof ArrayAccess || is_array($item)) {
                    return $this->ensureParameterIsPrimitive(
                        $item[$parameterName] ?? $item[Str::afterLast($parameterName, '.')]
                    );
                } elseif (is_object($item)) {
                    return $this->ensureParameterIsPrimitive(
                        $item->{$parameterName} ?? $item->{Str::afterLast($parameterName, '.')}
                    );
                }

                throw new Exception('Only arrays and objects are supported when cursor paginating items.');
            })->toArray();
    }

    /**
     * Ensure the parameter is a primitive type.
     *
     * @param mixed $parameter
     * @return mixed
     */
    protected function ensureParameterIsPrimitive($parameter)
    {
        return is_object($parameter) && method_exists($parameter, '__toString')
            ? (string)$parameter
            : $parameter;
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
    public function appends($key, string $value = null)
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
    protected function appendArray(array $keys): AbstractCursorPaginator
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
    public function withQueryString(): AbstractCursorPaginator
    {
        if (!is_null($query = Paginator::resolveQueryString())) {
            return $this->appends($query);
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
    protected function addQuery(string $key, string $value): AbstractCursorPaginator
    {
        if ($key !== $this->cursorName) {
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
     * Transform each item in the slice of items using a callback.
     *
     * @param callable $callback
     * @return $this
     */
    public function through(callable $callback): AbstractCursorPaginator
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
     * Get the current cursor being paginated.
     *
     * @return \Elegant\Pagination\Cursor|null
     */
    public function cursor(): ?Cursor
    {
        return $this->cursor;
    }

    /**
     * Get the query string variable used to store the cursor.
     *
     * @return string
     */
    public function getCursorName(): string
    {
        return $this->cursorName;
    }

    /**
     * Set the query string variable used to store the cursor.
     *
     * @param string $name
     * @return $this
     */
    public function setCursorName(string $name): AbstractCursorPaginator
    {
        $this->cursorName = $name;

        return $this;
    }

    /**
     * Set the base path to assign to all URLs.
     *
     * @param string $path
     * @return $this
     */
    public function withPath(string $path): AbstractCursorPaginator
    {
        return $this->setPath($path);
    }

    /**
     * Set the base path to assign to all URLs.
     *
     * @param string $path
     * @return $this
     */
    public function setPath(string $path): AbstractCursorPaginator
    {
        $this->path = $path;

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
     * Resolve the current cursor or return the default value.
     *
     * @param string $cursorName
     * @param mixed $default
     * @return \Elegant\Pagination\Cursor|null
     */
    public static function resolveCurrentCursor(string $cursorName = 'cursor', $default = null): ?Cursor
    {
        if (isset(static::$currentCursorResolver)) {
            return call_user_func(static::$currentCursorResolver, $cursorName);
        }

        return $default;
    }

    /**
     * Set the current cursor resolver callback.
     *
     * @param \Closure $resolver
     * @return void
     */
    public static function currentCursorResolver(Closure $resolver)
    {
        static::$currentCursorResolver = $resolver;
    }

    /**
     * Get an instance of the view factory from the resolver.
     *
     * @return \Elegant\Contracts\View\Factory
     */
    public static function viewFactory(): \Elegant\Contracts\View\Factory
    {
        return Paginator::viewFactory();
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
    public function setCollection(Collection $collection): AbstractCursorPaginator
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
    public function offsetGet($key)
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

