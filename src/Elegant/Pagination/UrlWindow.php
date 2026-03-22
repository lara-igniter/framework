<?php

namespace Elegant\Pagination;

use Elegant\Contracts\Pagination\LengthAwarePaginator as PaginatorContract;

class UrlWindow
{
    /**
     * The paginator implementation.
     *
     * @var \Elegant\Contracts\Pagination\LengthAwarePaginator
     */
    protected $paginator;

    /**
     * Create a new URL window instance.
     *
     * @param \Elegant\Contracts\Pagination\LengthAwarePaginator $paginator
     */
    public function __construct(PaginatorContract $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * Create a new URL window instance.
     *
     * @param \Elegant\Contracts\Pagination\LengthAwarePaginator $paginator
     * @return array
     */
    public static function make(PaginatorContract $paginator): array
    {
        return (new static($paginator))->get();
    }

    /**
     * Build the window of page links.
     *
     * The returned array has three keys understood by LengthAwarePaginator::elements():
     *   'first'  – always-visible leading pages (starting from page 1)
     *   'slider' – pages around the current page (preceded by "..." in the view)
     *   'last'   – always-visible trailing page (preceded by "..." in the view)
     *
     * Algorithm (mirrors the old MY_Model on_each_side behaviour):
     *   • Compute a window of [currentPage - onEachSide … currentPage + onEachSide].
     *   • Page 1 is always visible.
     *   • lastPage is always visible.
     *   • A "…" separator is inserted only when there are hidden pages in a gap
     *     (i.e. the gap is wider than one page — adjacent pages are absorbed).
     *
     * @return array{first: array|null, slider: array|null, last: array|null}
     */
    public function get(): array
    {
        $lastPage = $this->paginator->lastPage();
        $currentPage = $this->paginator->currentPage();
        $onEachSide = $this->paginator->onEachSide;

        // Nothing to paginate.
        if ($lastPage <= 1) {
            return ['first' => null, 'slider' => null, 'last' => null];
        }

        // Window boundaries clamped to [1, lastPage].
        $windowStart = max(1, $currentPage - $onEachSide);
        $windowEnd = min($lastPage, $currentPage + $onEachSide);

        // A gap exists at the START when there are pages between 2 and windowStart-1
        // that are not shown (i.e. page 1 and windowStart are not adjacent).
        $hasStartGap = $windowStart > 2;

        // A gap exists at the END when there are pages between windowEnd+1 and lastPage-1
        // that are not shown (i.e. windowEnd and lastPage are not adjacent).
        $hasEndGap = $windowEnd < ($lastPage - 1);

        // ── No gaps on either side ───────────────────────────────────────────
        if (!$hasStartGap && !$hasEndGap) {
            return [
                'first' => $this->paginator->getUrlRange(1, $lastPage),
                'slider' => null,
                'last' => null,
            ];
        }

        // ── Gap only at the END  (near the beginning) ────────────────────────
        // e.g.  1  2  3  [4]  5  6  7  …  20
        if (!$hasStartGap) {
            return [
                'first' => $this->paginator->getUrlRange(1, $windowEnd),
                'slider' => null,
                'last' => $this->paginator->getUrlRange($lastPage, $lastPage),
            ];
        }

        // ── Gap only at the START (near the end) ─────────────────────────────
        // e.g.  1  …  14  15  [16]  17  18  19  20
        if (!$hasEndGap) {
            return [
                'first' => $this->paginator->getUrlRange(1, 1),
                'slider' => $this->paginator->getUrlRange($windowStart, $lastPage),
                'last' => null,
            ];
        }

        // ── Gaps on BOTH sides (fully in the middle) ─────────────────────────
        // e.g.  1  …  7  8  9  [10]  11  12  13  …  20
        return [
            'first' => $this->paginator->getUrlRange(1, 1),
            'slider' => $this->paginator->getUrlRange($windowStart, $windowEnd),
            'last' => $this->paginator->getUrlRange($lastPage, $lastPage),
        ];
    }
}

