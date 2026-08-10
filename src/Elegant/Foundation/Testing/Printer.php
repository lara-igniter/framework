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
     * @var array<int, array>
     */
    private $bufferTests = [];

    /**
     * @var bool
     */
    private $bufferFailed = false;

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
     * @param array $prevResult
     * @param array $result
     * @return void
     */
    protected function writeTestResult(array $prevResult, array $result): void
    {
        if ($this->bufferClass !== '' && $prevResult['className'] !== $result['className']) {
            $this->flushClassBuffer();
        }

        if ($this->bufferClass !== $result['className']) {
            $this->bufferClass = $result['className'];
            $this->bufferTests = [];
            $this->bufferFailed = false;
        }

        $this->bufferTests[] = $result;

        if ($result['status'] !== BaseTestRunner::STATUS_PASSED) {
            $this->bufferFailed = true;
        }
    }

    /**
     * @param TestResult $result
     * @return void
     */
    public function printResult(TestResult $result): void
    {
        $this->flushClassBuffer();

        $this->write(PHP_EOL);
        $this->printFooter($result);
    }

    /**
     * @return void
     */
    private function flushClassBuffer(): void
    {
        if ($this->bufferClass === '' || $this->bufferTests === []) {
            return;
        }

        $passed = ! $this->bufferFailed;
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

        $columns = $this->terminalColumns();

        foreach ($this->bufferTests as $test) {
            $this->write($this->formatTestLine($test, $columns) . PHP_EOL);

            if (! empty($test['message'])) {
                $this->write(OutputStyle::color($test['message'], 'light_gray') . PHP_EOL);
            }
        }

        $this->write(PHP_EOL);

        $this->bufferClass = '';
        $this->bufferTests = [];
        $this->bufferFailed = false;
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
