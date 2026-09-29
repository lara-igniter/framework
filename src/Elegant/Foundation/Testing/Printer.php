<?php

namespace Elegant\Foundation\Testing;

use Elegant\Console\OutputStyle;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Runner\BaseTestRunner;
use PHPUnit\Util\TestDox\TestDoxPrinter;

/**
 * Laravel / Collision-style PHPUnit printer using Elegant\Console\OutputStyle.
 *
 *   PASS  Tests\Feature\HomePageTest
 *   ✓ home page responds successfully                                                              0.12s
 */
class Printer extends TestDoxPrinter
{
    /**
     * @var string
     */
    private $bufferClass = '';

    /**
     * @var bool
     */
    private $bufferFailed = false;

    /**
     * @var int
     */
    private $printedTestsInClass = 0;

    /**
     * @var int
     */
    private $lastMessageLines = 0;

    /**
     * @var bool
     */
    private static $compact = false;

    /**
     * @var int
     */
    private $compactProcessed = 0;

    /**
     * @var int
     */
    private $compactSymbolsPerLine = 76;

    /**
     * @var float
     */
    private $startedAt = 0.0;

    /**
     * @var array<int, array>
     */
    private $deferredFailures = [];

    /**
     * @param null|resource|string $out
     * @param int|string $numberOfColumns
     */
    public function __construct($out = null, bool $verbose = false, string $colors = self::COLOR_DEFAULT, bool $debug = false, $numberOfColumns = 80, bool $reverse = false)
    {
        parent::__construct($out, $verbose, $colors, $debug, $numberOfColumns, $reverse);

        // PHPUnit forces is_cli()=false; OutputStyle::init() then skips color detection.
        $colored = new \ReflectionProperty(OutputStyle::class, 'isColored');
        $colored->setAccessible(true);
        $colored->setValue(null, $this->colors || OutputStyle::hasColorSupport(STDOUT));

        if (getenv('ELEGANT_PRINTER_COMPACT') === 'true') {
            self::$compact = true;
        }

        $this->compactSymbolsPerLine = max(10, $this->terminalColumns() - 4);
        $this->startedAt = microtime(true);
    }

    /**
     * Enable or inspect compact output (ticks on one line).
     *
     * @param bool|null $value
     * @return bool
     */
    public static function compact(?bool $value = null): bool
    {
        if ($value !== null) {
            self::$compact = $value;
        }

        return self::$compact;
    }

    /**
     * @param Test $test
     * @return string
     */
    protected function formatTestName(Test $test): string
    {
        if ($test instanceof TestCase) {
            return $this->prettifier->prettifyTestCase($test);
        }

        return parent::formatTestName($test);
    }

    /**
     * Print the class badge as soon as the first test in that class starts.
     *
     * @param Test $test
     * @return void
     */
    public function startTest(Test $test): void
    {
        if ($test instanceof TestCase && ! self::$compact) {
            $class = get_class($test);

            if ($this->bufferClass !== $class) {
                if ($this->bufferClass !== '') {
                    $this->write(PHP_EOL);
                }

                $this->bufferClass = $class;
                $this->bufferFailed = false;
                $this->printedTestsInClass = 0;
                $this->lastMessageLines = 0;
                $this->writeClassHeader(true);
                $this->flushStdout();
            }
        }

        parent::startTest($test);
    }

    /**
     * @param array $prevResult
     * @param array $result
     * @return void
     */
    protected function writeTestResult(array $prevResult, array $result): void
    {
        if (self::$compact) {
            $this->writeCompactSymbol($result);

            if ($this->isFailureStatus($result['status'])) {
                $this->deferredFailures[] = $result;
            }

            $this->flushStdout();

            return;
        }

        $this->write($this->formatTestLine($result, $this->terminalColumns()) . PHP_EOL);
        $this->printedTestsInClass++;
        $this->lastMessageLines = 0;

        if (! empty($result['message'])) {
            $message = rtrim((string) $result['message']);
            $this->write(OutputStyle::color($message, 'light_gray') . PHP_EOL);
            $this->lastMessageLines = substr_count($message, "\n") + 1;
        }

        if ($result['status'] !== BaseTestRunner::STATUS_PASSED && ! $this->bufferFailed) {
            $this->bufferFailed = true;
            $this->rewriteClassHeaderAsFailed();
        }

        $this->flushStdout();
    }

    /**
     * Print a single compact-mode status icon, wrapping at the terminal width.
     *
     * @param array $result
     * @return void
     */
    private function writeCompactSymbol(array $result): void
    {
        if ($this->compactProcessed % $this->compactSymbolsPerLine === 0) {
            $this->write(PHP_EOL . '  ');
        }

        $this->write($this->statusSymbol($result['status']));
        $this->compactProcessed++;
    }

    /**
     * @param int $status
     * @return string
     */
    private function statusSymbol(int $status): string
    {
        if ($status === BaseTestRunner::STATUS_PASSED) {
            return OutputStyle::color('✓', 'green');
        }

        if (in_array($status, [
            BaseTestRunner::STATUS_FAILURE,
            BaseTestRunner::STATUS_ERROR,
        ], true)) {
            return OutputStyle::color('⨯', 'red');
        }

        if ($status === BaseTestRunner::STATUS_SKIPPED) {
            return OutputStyle::color('↩', 'cyan');
        }

        return OutputStyle::color('•', 'yellow');
    }

    /**
     * @param TestResult $result
     * @return void
     */
    public function printResult(TestResult $result): void
    {
        $this->write(PHP_EOL);

        if (self::$compact && $this->deferredFailures !== []) {
            $this->writeDeferredFailures();
            $this->write(PHP_EOL);
        }

        $this->writeRecap($result);
        $this->flushStdout();
    }

    /**
     * Print compact-mode failures in the same layout as the verbose printer.
     *
     * @return void
     */
    private function writeDeferredFailures(): void
    {
        $currentClass = '';

        foreach ($this->deferredFailures as $result) {
            $class = (string) $result['className'];

            if ($class !== $currentClass) {
                $currentClass = $class;
                $this->bufferClass = $class;
                $this->write(PHP_EOL);
                $this->writeClassHeader(false);
            }

            $this->write($this->formatTestLine($result, $this->terminalColumns()) . PHP_EOL);

            if (! empty($result['message'])) {
                $this->write(OutputStyle::color(rtrim((string) $result['message']), 'light_gray') . PHP_EOL);
            }
        }
    }

    /**
     * Laravel / Collision recap, always printed.
     *
     * @param TestResult $result
     * @return void
     */
    private function writeRecap(TestResult $result): void
    {
        $failed = $result->failureCount() + $result->errorCount();
        $warnings = $result->warningCount();
        $skipped = $result->skippedCount();
        $incomplete = $result->notImplementedCount();
        $risky = $result->riskyCount();
        $total = count($result);
        $passed = $total - $failed - $warnings - $skipped - $incomplete - $risky;

        if ($passed < 0) {
            $passed = 0;
        }

        $parts = [];

        if ($failed > 0) {
            $parts[] = OutputStyle::color($failed . ' failed', 'light_red');
        }

        if ($warnings > 0) {
            $parts[] = OutputStyle::color($warnings . ($warnings === 1 ? ' warning' : ' warnings'), 'yellow');
        }

        if ($skipped > 0) {
            $parts[] = OutputStyle::color($skipped . ' skipped', 'cyan');
        }

        if ($incomplete > 0) {
            $parts[] = OutputStyle::color($incomplete . ' incomplete', 'yellow');
        }

        if ($risky > 0) {
            $parts[] = OutputStyle::color($risky . ' risky', 'yellow');
        }

        if ($passed > 0 || $parts === []) {
            $parts[] = OutputStyle::color($passed . ' passed', 'light_green');
        }

        $assertions = $this->numAssertions;
        $duration = number_format(microtime(true) - $this->startedAt, 2, '.', '');
        $separator = OutputStyle::color(', ', 'light_gray');

        $this->write(
            '  '
            . OutputStyle::color('Tests:', 'light_gray')
            . '    '
            . implode($separator, $parts)
            . OutputStyle::color(
                ' (' . $assertions . ' assertion' . ($assertions === 1 ? '' : 's') . ')',
                'light_gray'
            )
            . PHP_EOL
        );

        $this->write(
            '  '
            . OutputStyle::color('Duration:', 'light_gray')
            . ' '
            . $duration
            . 's'
            . PHP_EOL
        );
    }

    /**
     * @param int $status
     * @return bool
     */
    private function isFailureStatus(int $status): bool
    {
        return in_array($status, [
            BaseTestRunner::STATUS_FAILURE,
            BaseTestRunner::STATUS_ERROR,
        ], true);
    }

    /**
     * @param bool $passed
     * @return void
     */
    private function writeClassHeader(bool $passed): void
    {
        $badge = $passed ? ' PASS ' : ' FAIL ';
        $badgeFg = $passed ? 'dark_gray' : 'white';
        $badgeBg = $passed ? 'green' : 'red';

        $this->write(
            '  '
            . OutputStyle::color($badge, $badgeFg, $badgeBg)
            . ' '
            . OutputStyle::color($this->bufferClass, 'white')
            . PHP_EOL
        );
    }

    /**
     * Collision-style: turn the live PASS badge into FAIL when the first test fails.
     *
     * @return void
     */
    private function rewriteClassHeaderAsFailed(): void
    {
        $up = $this->printedTestsInClass + $this->lastMessageLines;

        if ($up < 1) {
            return;
        }

        $this->write(sprintf("\033[%dA\r", $up));
        $this->write("\033[2K");
        $this->writeClassHeader(false);

        $down = $up - 1;
        if ($down > 0) {
            $this->write(sprintf("\033[%dB", $down));
        }
    }

    /**
     * @return void
     */
    private function flushStdout(): void
    {
        if (defined('STDOUT') && is_resource(STDOUT)) {
            fflush(STDOUT);
        }

        if (function_exists('flush')) {
            flush();
        }
    }

    /**
     * @param array $result
     * @param int $columns
     * @return string
     */
    private function formatTestLine(array $result, int $columns): string
    {
        $symbol = $this->statusSymbol($result['status']);

        $name = (string) $result['testMethod'];
        $time = sprintf('%.2fs', $result['time']);

        $prefixLen = 4;
        $available = max(10, $columns - $prefixLen - strlen($time));
        $nameDisplay = $this->truncate($name, $available);
        $pad = max(1, $available - $this->visibleLength($nameDisplay));

        return '  '
            . $symbol
            . ' '
            . OutputStyle::color($nameDisplay, 'light_gray')
            . str_repeat(' ', $pad)
            . OutputStyle::color($time, 'light_gray');
    }

    /**
     * @return int
     */
    private function terminalColumns(): int
    {
        $columns = (int) (getenv('COLUMNS') ?: 0);

        if ($columns < 40) {
            $columns = 80;
        }

        return $columns;
    }

    /**
     * @param string $text
     * @param int $max
     * @return string
     */
    private function truncate(string $text, int $max): string
    {
        if ($this->visibleLength($text) <= $max) {
            return $text;
        }

        if ($max <= 3) {
            return substr($text, 0, $max);
        }

        return substr($text, 0, $max - 3) . '...';
    }

    /**
     * @param string $text
     * @return int
     */
    private function visibleLength(string $text): int
    {
        return strlen(preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text);
    }
}
