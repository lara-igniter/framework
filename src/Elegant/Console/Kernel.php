<?php

namespace Elegant\Console;

use Elegant\Console\Input\ArgvInput;
use Elegant\Console\Output\ConsoleOutput;
use Elegant\Contracts\Console\Kernel as KernelContract;
use Elegant\Foundation\Application;
use Elegant\Support\Facades\Route;
use RuntimeException;

class Kernel implements KernelContract
{
    /**
     * The application instance.
     *
     * @var \Elegant\Foundation\Application|null
     */
    protected ?Application $app;

    /**
     * The framework's Command-based CLI command names.
     *
     * @var string[]
     */
    protected array $frameworkCommands = [
        'queue:work',
    ];

    /**
     * The application's Command-based CLI command names.
     *
     * @var string[]
     */
    protected array $commands = [];

    /**
     * Auto-discovered commands keyed by route path.
     *
     * @var array<string, class-string>
     */
    protected static array $discovered = [];

    /**
     * Create a new console kernel instance.
     *
     * @param \Elegant\Foundation\Application|null $app
     */
    public function __construct(?Application $app = null)
    {
        $this->app = $app;
    }

    /**
     * Handle an incoming CLI command.
     *
     * @param ArgvInput $input
     * @param ConsoleOutput $output
     * @return int
     */
    public function handle(ArgvInput $input, ConsoleOutput $output): int
    {
        try {
            $this->commands();

            // ── Built-in global options ──────────────────────────────────────
            // These are handled before bootstrapping the full CI application so
            // that no unnecessary overhead is incurred.

            if ($input->hasParameterOption('-v') || $input->hasParameterOption('--version')) {
                if (!function_exists('is_cli')) {
                    function is_cli(): bool
                    {
                        return PHP_SAPI === 'cli' || defined('STDIN');
                    }
                }

                defined('ENVIRONMENT') || define('ENVIRONMENT', 'production');

                OutputStyle::write(
                    OutputStyle::color("  ", 'white') . OutputStyle::color(" INFO ", 'white', 'blue') . " Laraigniter Framework " . Application::VERSION,
                    'white'
                );
                OutputStyle::newLine();

                return 0;
            }

            // No positional command given (bare `php artisan`, or flags only
            // like `php artisan --help`) → show the command list.
            if ($input->getFirstArgument() === null) {
                $_SERVER['argv'] = [$_SERVER['argv'][0], 'list'];
            }

            $this->prepareArgv($input);
            $this->bootstrap();

            return 0;
        } catch (RuntimeException $e) {
            $output->error($e->getMessage());

            return 1;
        }
    }

    /**
     * Bootstrap the Laraigniter application.
     *
     * @return void
     *
     * @throws RuntimeException
     */
    public function bootstrap(): void
    {
        $httpKernel = $this->httpKernel();
        $httpKernel->bootstrap();

        $basePath = $this->resolveBasePath();
        $start = $httpKernel->startScriptPath();

        if (! is_file($start)) {
            throw new RuntimeException(sprintf('Application start script [%s] not found.', $start));
        }

        $previous = getcwd();
        chdir($basePath . DIRECTORY_SEPARATOR . 'public');
        require $start;

        if ($previous) {
            chdir($previous);
        }
    }

    /**
     * @return \Elegant\Foundation\Http\Kernel
     */
    protected function httpKernel()
    {
        if ($this->app) {
            return $this->app->make(\Elegant\Contracts\Http\Kernel::class);
        }

        $basePath = $this->resolveBasePath();

        return new \Elegant\Foundation\Http\Kernel(new Application($basePath), $basePath);
    }

    /**
     * @return string
     */
    protected function resolveBasePath(): string
    {
        if ($this->app) {
            return $this->app->basePath();
        }

        return getcwd() ?: dirname(__DIR__, 4);
    }

    /**
     * Perform any final actions after the command has run.
     *
     * @param ArgvInput $input
     * @param int $status
     * @return void
     */
    public function terminate(ArgvInput $input, int $status): void
    {
        //
    }

    /**
     * Register the application's console commands.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/../Cache/Console');
        $this->load(__DIR__ . '/../Database/Console');
        $this->load(__DIR__ . '/../Foundation/Console');
        $this->load(__DIR__ . '/../Queue/Console');
        $this->load(__DIR__ . '/../Session/Console');
    }

    /**
     * Register all of the commands in the given directory.
     *
     * @param string $path
     * @return void
     */
    protected function load(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            $class = $this->classFromFile($file->getPathname(), $content);

            if ($class === null) {
                continue;
            }

            $this->registerFromContent($class, $content);
        }
    }

    /**
     * Register a command class into the discovery registry.
     *
     * @param class-string $class
     * @return void
     */
    public static function registerCommand(string $class): void
    {
        $loader = null;

        foreach (spl_autoload_functions() as $fn) {
            if (is_array($fn) && $fn[0] instanceof \Composer\Autoload\ClassLoader) {
                $loader = $fn[0];
                break;
            }
        }

        if ($loader === null) {
            return;
        }

        $file = $loader->findFile($class);

        if ($file === false) {
            return;
        }

        $file = realpath($file);

        if ($file === false) {
            return;
        }

        $content = file_get_contents($file);

        if (preg_match('/^abstract\s+class\s+/m', $content)) {
            return;
        }

        $route = static::parseCommandRoute($content);

        if ($route === null) {
            return;
        }

        [, $routePath] = $route;

        static::$discovered[$routePath] = $class;
    }

    /**
     * Register Route::cli() entries for all auto-discovered commands.
     *
     * @return void
     */
    public static function registerDiscoveredRoutes(): void
    {
        foreach (static::$discovered as $routePath => $commandClass) {
            Route::cli($routePath, static function () use ($commandClass) {
                (new $commandClass())->execute();
            });
        }
    }

    /**
     * Resolve a discovered command class by its short (unqualified) name.
     *
     * @param string $shortName
     * @return class-string|null
     */
    public static function resolveByShortName(string $shortName): ?string
    {
        foreach (static::$discovered as $fqcn) {
            if (substr(strrchr($fqcn, '\\'), 1) === $shortName) {
                return $fqcn;
            }
        }

        return null;
    }

    /**
     * Get all auto-discovered [routePath => className] pairs.
     *
     * @return array<string, class-string>
     */
    public static function discovered(): array
    {
        return static::$discovered;
    }

    /**
     * Get all Command-based command names (framework + application).
     *
     * @return string[]
     */
    protected function getCommands(): array
    {
        return array_merge($this->frameworkCommands, $this->commands);
    }

    /**
     * Strip option flags from $_SERVER['argv'] for Command-based commands
     * so the router receives only the command name as the routing URL.
     *
     * @param ArgvInput $input
     * @return void
     */
    protected function prepareArgv(ArgvInput $input): void
    {
        $command = $input->getFirstArgument();

        if ($command === null) {
            return;
        }

        $raw = $_SERVER['argv'];
        $_SERVER['_laraigniter_command_argv'] = $raw;
        $result = [$raw[0], $raw[1]];
        $count = count($raw);

        for ($i = 2; $i < $count; $i++) {
            $arg = $raw[$i];

            if (strpos($arg, '-') !== 0) {
                $result[] = $arg;
                continue;
            }

            if (preg_match('/^-([a-zA-Z])$/', $arg) && isset($raw[$i + 1]) && strpos($raw[$i + 1], '-') !== 0) {
                $i++;
            }
        }

        $_SERVER['argv'] = array_values($result);

        global $argv;
        $argv = $_SERVER['argv'];
    }

    /**
     * Register a command from its source file content.
     *
     * @param class-string $class
     * @param string $content
     * @return void
     */
    private function registerFromContent(string $class, string $content): void
    {
        if (preg_match('/^abstract\s+class\s+/m', $content)) {
            return;
        }

        $route = static::parseCommandRoute($content);

        if ($route === null) {
            return;
        }

        [$commandName, $routePath] = $route;

        $this->commands[] = $commandName;
        static::$discovered[$routePath] = $class;
    }

    /**
     * Resolve the command name and CI route path from a PHP source file's content.
     *
     * @param string $content
     * @return array{0: string, 1: string}|null
     */
    private static function parseCommandRoute(string $content): ?array
    {
        if (preg_match('/protected\s+static\s+\$defaultName\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $content, $m)) {
            return [$m[1], $m[1]];
        }

        if (!preg_match('/\$signature\s*=\s*[\'"](.+?)[\'"]\s*;/s', $content, $m)) {
            return null;
        }

        [$commandName, $arguments] = Parser::parse(trim($m[1]));

        $routePath = $commandName;

        foreach ($arguments as $argument) {
            $routePath .= '/{' . $argument['name'] . ($argument['required'] ? '' : '?') . '}';
        }

        return [$commandName, $routePath];
    }

    /**
     * Extract the fully-qualified class name from a PHP source file.
     *
     * @param string $file
     * @param string|null $content
     * @return string|null
     */
    protected function classFromFile(string $file, ?string $content = null): ?string
    {
        $content = $content ?? file_get_contents($file);

        $namespace = null;
        $class = null;

        if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (preg_match('/^(?:abstract\s+)?class\s+(\w+)/m', $content, $matches)) {
            $class = $matches[1];
        }

        if ($namespace !== null && $class !== null) {
            return $namespace . '\\' . $class;
        }

        return null;
    }
}
