<?php

namespace Elegant\Database\Console\Seeds;

use Elegant\Console\Command;
use Elegant\Database\Seeder;

class SeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'db:seed
                                    {class? : The class name of the root seeder}';

    /**
     * The default name (used for routing).
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'db:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Seed the database with records';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        if (config_item('env') === 'production' && !config_item('debug')) {
            $this->error('Cannot seed data at production environment!');
            return;
        }

        $class = $this->argument('class');

        if (!is_null($class)) {
            $fullClass = class_exists($class) ? $class : 'Database\\Seeders\\' . $class;
        } else {
            $fullClass = 'Database\\Seeders\\DatabaseSeeder';
        }

        (new Seeder)->call($fullClass);
    }
}
