<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;
use Elegant\Console\OutputStyle;
use Elegant\Routing\RouteBuilder;

class RouteListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'route:list';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'route:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'List all registered routes';

    /**
     * HTTP method → display color mapping.
     *
     * @var array<string, string>
     */
    protected static array $methodColors = [
        'GET' => 'green',
        'POST' => 'yellow',
        'PUT' => 'cyan',
        'PATCH' => 'light_cyan',
        'DELETE' => 'red',
        'HEAD' => 'light_gray',
        'OPTIONS' => 'light_gray',
    ];

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \ReflectionException
     */
    public function handle(): void
    {
        $this->loadWebRoutes();

        $seen = [];
        $routes = [];

        foreach (RouteBuilder::$compiled['paths'] as $routeObjects) {
            foreach ($routeObjects as $route) {
                if ($route->isCli) {
                    continue;
                }

                $key = $route->getFullPath() . '|' . implode(',', $route->getMethods());

                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $methodStr = implode(OutputStyle::color('|', 'dark_gray'), array_map(
                    fn(string $m) => OutputStyle::color($m, static::$methodColors[$m] ?? 'light_gray'),
                    $route->getMethods()
                ));

                $action = $route->getAction();
                if (is_callable($action) && !is_string($action)) {
                    $action = OutputStyle::color('Closure', 'light_purple');
                }

                $middlewareStr = implode(', ', array_map(
                    function ($m) {
                        if (is_object($m)) {
                            return (new \ReflectionClass($m))->getShortName();
                        }

                        if (is_array($m)) {
                            return implode('|', array_map(function ($item) {
                                if (is_object($item)) {
                                    return (new \ReflectionClass($item))->getShortName();
                                }
                                return (string)$item;
                            }, $m));
                        }

                        return (string)$m;
                    },
                    $route->getMiddleware()
                ));

                $routes[] = [
                    'method' => $methodStr,
                    'uri' => $route->getFullPath(),
                    'name' => $route->getName() ?? '',
                    'action' => $action,
                    'middleware' => $middlewareStr,
                ];
            }
        }

        if (empty($routes)) {
            $this->line('  ' . OutputStyle::color('No web/api routes registered.', 'yellow'));
            return;
        }

        // Terminal width (default 120 if detection fails)
        $termWidth = OutputStyle::getWidth(120);

        // Overhead: 6 border chars (│×6) + 10 padding spaces (2 per col × 5) = 16
        $available = max(60, $termWidth - 16);

        // Column keys and their header label lengths
        $colKeys = ['method', 'uri', 'name', 'action', 'middleware'];
        $hdrWidths = ['method' => 6, 'uri' => 3, 'name' => 4, 'action' => 6, 'middleware' => 10];

        // Natural widths = max(header width, longest data value)
        $naturalWidths = $hdrWidths;
        foreach ($routes as $row) {
            foreach ($colKeys as $key) {
                $len = OutputStyle::strlen((string)($row[$key] ?? ''));
                if ($len > $naturalWidths[$key]) {
                    $naturalWidths[$key] = $len;
                }
            }
        }

        if (array_sum($naturalWidths) > $available) {
            // Keep method and middleware capped; distribute the rest to uri/name/action
            $naturalWidths['method'] = min($naturalWidths['method'], 20);
            $naturalWidths['middleware'] = min($naturalWidths['middleware'], 30);

            $fixed = $naturalWidths['method'] + $naturalWidths['middleware'];
            $flex = max(40, $available - $fixed);

            // URI 40% · Name 20% · Action 40%
            $naturalWidths['uri'] = max(15, (int)floor($flex * 0.40));
            $naturalWidths['name'] = max(10, (int)floor($flex * 0.20));
            $naturalWidths['action'] = max(15, $flex - $naturalWidths['uri'] - $naturalWidths['name']);

            // Truncate plain-text columns that exceed their allotted width
            foreach ($routes as &$row) {
                foreach (['uri', 'name', 'middleware'] as $key) {
                    $row[$key] = OutputStyle::truncate((string)$row[$key], $naturalWidths[$key]);
                }
                // Only truncate action when it is a plain string (no ANSI codes)
                $actionVal = (string)$row['action'];
                if (!str_contains($actionVal, "\033[")) {
                    $row['action'] = OutputStyle::truncate($actionVal, $naturalWidths['action']);
                }
            }
            unset($row);
        }
        // ─────────────────────────────────────────────────────────────────

        $this->newLine();

        $this->table(
            [
                OutputStyle::color('Method', 'green'),
                OutputStyle::color('URI', 'green'),
                OutputStyle::color('Name', 'green'),
                OutputStyle::color('Action', 'green'),
                OutputStyle::color('Middleware', 'green'),
            ],
            $routes
        );

        $this->line('  ' . OutputStyle::color(count($routes) . ' route(s) total', 'dark_gray'));
        $this->newLine();
    }

    /**
     * Load and compile web and API routes, while handling any exceptions gracefully.
     *
     * @return void
     */
    private function loadWebRoutes(): void
    {
        RouteBuilder::$inspecting = true;

        try {
            if (file_exists(base_path('routes/web.php'))) {
                require_once base_path('routes/web.php');
            }

            if (file_exists(base_path('routes/api.php'))) {
                RouteBuilder::group('/api', function () {
                    require_once base_path('routes/api.php');
                });
            }

            RouteBuilder::compileAll();
        } catch (\Throwable $e) {
            $this->line('  ' . OutputStyle::color('Warning: ' . $e->getMessage(), 'yellow'));
        } finally {
            RouteBuilder::$inspecting = false;
        }
    }
}
