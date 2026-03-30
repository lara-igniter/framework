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
                                return (string) $item;
                            }, $m));
                        }

                        return (string) $m;
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
