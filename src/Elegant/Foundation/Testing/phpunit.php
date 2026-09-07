<?php

/**
 * Elegant PHPUnit bootstrap helpers (Composer autoload files).
 *
 * Loaded with vendor/autoload.php — no per-project tests/bootstrap.php needed.
 * Must be the first package autoload.files entry so error_reporting is set
 * before helpers.php is parsed (PHP 8.4 implicit-nullable deprecations).
 *
 * Must live in the package's autoload.files (not only autoload-dev): Composer
 * never loads dependency autoload-dev into consuming apps.
 */

if (defined('ELEGANT_PHPUNIT_BOOTSTRAPPED')) {
    return;
}

$runningPhpunit = defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__');

foreach ($_SERVER['argv'] ?? [] as $arg) {
    $normalized = str_replace('\\', '/', (string) $arg);

    if (stripos($normalized, 'phpunit') !== false) {
        $runningPhpunit = true;
        break;
    }
}

// Isolated children often have neither "phpunit" in argv nor the parent env.
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

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        return true;
    }

    return false;
});

if (! function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/*
 * Artisan subprocesses (migrate, etc.) may inherit APP_ENV=testing. Keep a
 * real CLI is_cli() so console commands still boot as CLI.
 */
$isArtisan = false;

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (stripos(str_replace('\\', '/', (string) $arg), 'artisan') !== false) {
        $isArtisan = true;
        break;
    }
}

if ($isArtisan) {
    return;
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
