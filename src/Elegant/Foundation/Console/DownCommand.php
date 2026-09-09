<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;
use Elegant\Support\Facades\File;
use Elegant\Support\Str;

class DownCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'down
                                    {--redirect= : The path that users should be redirected to}
                                    {--retry= : The number of seconds after which the request may be retried}
                                    {--refresh= : The number of seconds after which the browser may refresh}
                                    {--secret= : The secret phrase that may be used to bypass maintenance mode}
                                    {--with-secret : Generate a random secret phrase for bypassing maintenance mode}
                                    {--status=503 : The status code that should be used when returning the maintenance mode response}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'down';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Put the application into maintenance / demo mode';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        if (is_file(storage_path('framework/down'))) {
            $this->warn('Application is already down.');
            return;
        }

        $secret = $this->option('secret');

        if ($this->option('with-secret')) {
            $secret = Str::uuid()->toString();
        }

        File::put(storage_path('framework/down'), json_encode([
            'except' => [],
            'redirect' => $this->option('redirect'),
            'retry' => $this->option('retry'),
            'refresh' => $this->option('refresh'),
            'secret' => $secret,
            'status' => (int)($this->option('status') ?: 503),
            'template' => $this->prerenderTemplate(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $stub = base_path('stubs/maintenance-mode.stub');

        if (!file_exists($stub)) {
            $stub = __DIR__ . '/stubs/maintenance-mode.stub';
        }

        File::put(storage_path('framework/maintenance.php'), File::get($stub));

        $this->warn('Application is now in maintenance mode.');
        $this->newLine();

        if (!is_null($secret)) {
            $this->info('You may bypass maintenance mode via [' . config_item('base_url') . '?secret=' . $secret . ']');
        }
    }

    /**
     * Prerender the 503 view so maintenance.php can echo it before Composer loads.
     *
     * @return string
     */
    protected function prerenderTemplate(): string
    {
        try {
            return app('view')->make('errors.503', [
                'heading' => '503 Service Unavailable',
                'message' => 'Service Unavailable',
            ])->render();
        } catch (\Throwable $e) {
            return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>503 Service Unavailable</title></head><body><h1>503 Service Unavailable</h1><p>Service Unavailable</p></body></html>';
        }
    }
}

