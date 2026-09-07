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
        if ($test instanceof TestCase) {
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
     * @param TestResult $result
     * @return void
     */
    public function printResult(TestResult $result): void
    {
        $this->write(PHP_EOL);
        $this->printFooter($result);
        $this->flushStdout();
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
        $status = $result['status'];
        $passed = $status === BaseTestRunner::STATUS_PASSED;
        $failed = in_array($status, [
            BaseTestRunner::STATUS_FAILURE,
            BaseTestRunner::STATUS_ERROR,
        ], true);

        if ($passed) {
            $symbol = OutputStyle::color('✓', 'green');
        } elseif ($failed) {
            $symbol = OutputStyle::color('⨯', 'red');
        } elseif ($status === BaseTestRunner::STATUS_SKIPPED) {
            $symbol = OutputStyle::color('↩', 'cyan');
        } else {
            $symbol = OutputStyle::color('•', 'yellow');
        }

        $name = (string) $result['testMethod'];
        $time = sprintf('%.2fs', $result['time']);

        // "  ✓ name … time" — pad so duration sits toward the right edge.
        $prefixLen = 4; // two spaces + symbol + space (symbol counts as 1 col)
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
