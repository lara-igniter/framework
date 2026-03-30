<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;
use Elegant\Console\OutputStyle;
use Elegant\Support\Facades\Route;

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
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        $routes = collect(Route::getRoutes())->map(function ($route, $uri) {
            if (empty(key($route))) {
                return 0;
            }

            return [
                'method' => key($route),
                'uri' => $uri,
                'name' => '',
                'action' => ltrim($route[key($route)], '\\'),
                'middleware' => '',
            ];
        })->reject(function ($item) {
            return !is_array($item);
        })->values()->toArray();

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
    }
}
