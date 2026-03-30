<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;
use Elegant\Console\OutputStyle;

class EnvironmentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'env';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'env';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Display the current framework environment';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        $label = 'Current application environment:';

        $pad = 33;

        $this->info(
            substr($label . str_repeat(' ', $pad), 0, $pad)
            . OutputStyle::wrap(OutputStyle::color(config_item('env'), 'yellow'), 120, $pad)
        );
    }
}
