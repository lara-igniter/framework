<?php

namespace Elegant\Pagination;

use ArrayAccess;
use Countable;
use Elegant\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Elegant\Contracts\Support\Arrayable;
use Elegant\Contracts\Support\Jsonable;
use Elegant\Support\Collection;
use IteratorAggregate;
use JsonSerializable;

class CursorPaginator extends AbstractCursorPaginator implements Arrayable, ArrayAccess, Countable, IteratorAggregate, Jsonable, JsonSerializable, CursorPaginatorContract
{
    /**
     * Indicates whether there are more items in the data source.
     *
     * @var bool
     */
    protected bool $hasMore;

    /**
     * Create a new paginator instance.
     *
     * @param mixed $items
     * @param int $perPage
     * @param \Elegant\Pagination\Cursor|null $cursor
     * @param array $options (path, query, fragment, cursorName, parameters)
     * @return void
     */
    public function __construct($items, int $perPage, Cursor $cursor = null, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->perPage = (int)$perPage;
        $this->cursor = $cursor;
        $this->path = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;

        $this->setItems($items);
    }

    /**
     * Set the items for the paginator.
     *
     * @param mixed $items
     * @return void
     */
    protected function setItems($items)
    {
        $this->items = $items instanceof Collection ? $items : Collection::make($items);

        $this->hasMore = $this->items->count() > $this->perPage;

        $this->items = $this->items->slice(0, $this->perPage);

        if (!is_null($this->cursor) && $this->cursor->pointsToPreviousItems()) {
            $this->items = $this->items->reverse()->values();
        }
    }

    /**
     * Render the paginator using the given view (alias for render).
     *
     * @param string|null $view
     * @param array $data
     * @return string
     */
    public function links(string $view = null, array $data = []): string
    {
        return $this->render($view, $data);
    }

    /**
     * Render the paginator using the given view.
     *
     * @param string|null $view
     * @param array $data
     * @return string
     */
    public function render(string $view = null, array $data = []): string
    {
        return static::viewFactory()->make($view ?: Paginator::$defaultSimpleView, array_merge($data, [
            'paginator' => $this,
        ]));
    }

    /**
     * Determine if there are more items in the data source.
     *
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return (is_null($this->cursor) && $this->hasMore) ||
            (!is_null($this->cursor) && $this->cursor->pointsToNextItems() && $this->hasMore) ||
            (!is_null($this->cursor) && $this->cursor->pointsToPreviousItems());
    }

    /**
     * Determine if there are enough items to split into multiple pages.
     *
     * @return bool
     */
    public function hasPages(): bool
    {
        return !$this->onFirstPage() || $this->hasMorePages();
    }

    /**
     * Determine if the paginator is on the first page.
     *
     * @return bool
     */
    public function onFirstPage(): bool
    {
        return is_null($this->cursor) || ($this->cursor->pointsToPreviousItems() && !$this->hasMore);
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
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items->toArray(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'next_cursor' => ($c = $this->nextCursor()) ? $c->encode() : null,
            'next_page_url' => $this->nextPageUrl(),
            'prev_cursor' => ($c = $this->previousCursor()) ? $c->encode() : null,
            'prev_page_url' => $this->previousPageUrl(),
        ];
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}

