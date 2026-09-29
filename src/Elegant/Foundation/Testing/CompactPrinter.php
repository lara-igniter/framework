<?php

namespace Elegant\Foundation\Testing;

/**
 * Compact Collision-style printer: ticks on one line instead of one test per line.
 *
 * Selected by `php artisan test --compact`.
 */
class CompactPrinter extends Printer
{
    /**
     * @param null|resource|string $out
     * @param int|string $numberOfColumns
     */
    public function __construct($out = null, bool $verbose = false, string $colors = self::COLOR_DEFAULT, bool $debug = false, $numberOfColumns = 80, bool $reverse = false)
    {
        self::compact(true);

        parent::__construct($out, $verbose, $colors, $debug, $numberOfColumns, $reverse);
    }
}

