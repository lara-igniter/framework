<?php

namespace Elegant\Console;

use Elegant\Console\Input\ArgvInput;
use Elegant\Console\Output\ConsoleOutput;
use Elegant\Contracts\Console\Kernel as KernelContract;
use Elegant\Support\Facades\Route;
use RuntimeException;

class Kernel implements KernelContract
{
    /**
     * The path to the application's front controller.
     *
     * @var string
     */
    protected string $entryPoint = 'public/index.php';

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
        if (!file_exists($this->entryPoint)) {
            throw new RuntimeException(
                sprintf('Application entry point [%s] not found.', $this->entryPoint)
            );
        }

        require $this->entryPoint;
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
        $this->load(__DIR__ . '/../Foundation/Console');
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
     * Register a command class into the discovery registry by resolving its
     * source file via the Composer ClassLoader.
     *
     * Intended for use by service providers during the pre_system hook.
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
            Route::cli($routePath, [$commandClass, 'execute']);
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

        if ($command === null || !in_array($command, $this->getCommands(), true)) {
            return;
        }

        $_SERVER['argv'] = array_values(array_filter(
            $_SERVER['argv'],
            static function (string $arg, int $i): bool {
                return $i <= 1 || strpos($arg, '-') !== 0;
            },
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * Parse a command signature from file content and add the command to
     * the discovery registry. Shared by load().
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
     * Checks $defaultName first (fast path); falls back to parsing $signature.
     *
     * @param string $content
     * @return array{0: string, 1: string}|null  [$commandName, $routePath] or null
     */
    private static function parseCommandRoute(string $content): ?array
    {
        // Fast path: $defaultName is set — no signature parsing needed.
        if (preg_match('/protected\s+static\s+\$defaultName\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $content, $m)) {
            return [$m[1], $m[1]];
        }

        // Parse $signature from source without loading the class (CI_Controller
        // is unavailable before bootstrap()).
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
