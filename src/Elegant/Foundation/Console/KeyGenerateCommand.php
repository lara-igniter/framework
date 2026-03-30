<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;

class KeyGenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'key:generate
                                    {--show : Display the key instead of modifying files}
                                    {--force : Force the operation to run when in production}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'key:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Set the application key';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        app('load')->library('encryption');

        app('encryption')->initialize([
            'cipher' => config_item('cipher'),
            'mode' => config_item('cipher_mode'),
        ]);

        $key = base64_encode(app('encryption')->create_key(32));

        if (config_item('env') === 'production' && !$this->option('force')) {
            $this->alert('Application In Production!');
            if (!$this->confirm('Do you really wish to run this command?')) {
                $this->warn('Command Canceled!');
                return;
            }
        }

        $escaped = preg_quote('=' . config_item('encryption_key'), '/');

        file_put_contents(
            base_path('.env'),
            preg_replace("/^APP_KEY{$escaped}/m", 'APP_KEY=' . $key, file_get_contents(base_path('.env')))
        );

        $this->newLine();

        if ($this->option('show')) {
            $this->warn($key);
        } else {
            $this->info('Application key set successfully.');
        }
    }
}
