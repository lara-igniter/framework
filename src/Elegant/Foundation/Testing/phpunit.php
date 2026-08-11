<?php

/**
 * Elegant PHPUnit bootstrap helpers (Composer autoload files).
 *
 * Loaded with vendor/autoload.php — no per-project tests/bootstrap.php needed.
 * Only activates when the current process is PHPUnit.
 *
 * Must live in the package's autoload.files (not only autoload-dev): Composer
 * never loads dependency autoload-dev into consuming apps.
 */

if (defined('ELEGANT_PHPUNIT_BOOTSTRAPPED')) {
    return;
}

$runningPhpunit = false;

foreach ($_SERVER['argv'] ?? [] as $arg) {
    $normalized = str_replace('\\', '/', (string) $arg);

    if (stripos($normalized, 'phpunit') !== false) {
        $runningPhpunit = true;
        break;
    }
}

// PHPUnit process-isolation children may not keep "phpunit" in argv; phpunit.xml sets these.
if (! $runningPhpunit) {
    $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? ''));
    $ciEnv = getenv('CI_ENV') ?: ($_ENV['CI_ENV'] ?? ($_SERVER['CI_ENV'] ?? ''));
    if ($appEnv === 'testing' || $ciEnv === 'testing') {
        $runningPhpunit = true;
    }
}

if (! $runningPhpunit) {
    return;
}

define('ELEGANT_PHPUNIT_BOOTSTRAPPED', true);

if (! defined('ENVIRONMENT')) {
    define('ENVIRONMENT', getenv('APP_ENV') ?: 'testing');
}

// Absorb PHPUnit progress output so CI session_start()/ini_set() do not hit "headers already sent".
if (ob_get_level() === 0) {
    ob_start();
}

if (! function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/*
 * is_cli() must be false so Laraigniter loads web routes under PHPUnit.
 * OutputStyle::init() however needs a real CLI detection pass first for colors.
 */
$GLOBALS['ELEGANT_PHPUNIT_CLI_BOOTSTRAP'] = true;

if (! function_exists('is_cli')) {
    function is_cli()
    {
        if (! empty($GLOBALS['ELEGANT_PHPUNIT_CLI_BOOTSTRAP'])) {
            return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
        }

        return false;
    }
}

class_exists(\Elegant\Console\OutputStyle::class);

$GLOBALS['ELEGANT_PHPUNIT_CLI_BOOTSTRAP'] = false;
