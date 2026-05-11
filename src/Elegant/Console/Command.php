<?php

namespace Elegant\Console;

use CI_Controller;

class Command extends CI_Controller
{
    use Concerns\CallsCommands,
        Concerns\HasParameters,
        Concerns\InteractsWithIO;

    /**
     * The console command argument definitions.
     *
     * @var array
     */
    protected array $arguments = [];

    /**
     * The console command option definitions.
     *
     * @var array
     */
    protected array $options = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature;

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = '';

    public function __construct()
    {
        if (isset($this->signature)) {
            $this->configureUsingFluentDefinition();
        } else {
            parent::__construct();
        }
    }

    /**
     * Configure the console command using a fluent definition.
     *
     * @return void
     */
    protected function configureUsingFluentDefinition(): void
    {
        [$name, $arguments, $options] = Parser::parse($this->signature);

        $this->arguments = $arguments;
        $this->options = $options;

        $this->specifyParameters();

        $this->initializeCi();
    }

    /**
     * Boot CI properties for this command without re-running the full autoloader.
     *
     * For top-level commands (no active Command singleton yet) we run the
     * standard CI boot chain: core classes + $this->load->initialize().
     *
     * For nested commands (called via $this->call() while another Command is
     * already the CI singleton) we skip initialize() entirely and simply copy
     * every already-loaded library/driver from the outer command.  Re-running
     * _ci_autoloader() inside a nested constructor crashes certain drivers
     * (e.g. CI_Cache_file calls get_instance() before the new singleton is
     * fully set up).
     *
     * @return void
     */
    protected function initializeCi(): void
    {
        try {
            $ref = new \ReflectionProperty(\CI_Controller::class, 'instance');
            $ref->setAccessible(true);
            $existingCI = $ref->getValue(null);
            // Register this command as the active CI singleton.
            $ref->setValue(null, $this);
        } catch (\ReflectionException $e) {
            // Reflection unavailable – fall back to the standard CI boot chain.
            parent::__construct();
            return;
        }

        if ($existingCI === null) {
            // No CI singleton exists yet – standard boot chain.
            parent::__construct();
            return;
        }

        // Copy core class references (config, router, input, output …) from
        // the existing CI singleton.
        //
        // We intentionally do NOT call load_class($class) here because that
        // function defaults to the 'libraries/' directory.  Any class that was
        // loaded via load_class() with a different directory (e.g. 'core') but
        // whose name also maps to a driver/library (e.g. 'Cache') would cause
        // "Unable to locate the specified class: Cache.php".
        foreach (is_loaded() as $var => $class) {
            if (isset($existingCI->$var)) {
                $this->$var = $existingCI->$var;
            }
        }

        // Grab the shared Loader instance (safe: 'Loader' is always in
        // load_class()'s internal $_classes cache from the CI bootstrap).
        $this->load =& load_class('Loader', 'core');

        if ($existingCI instanceof Command) {
            // ── Nested command ──────────────────────────────────────────────
            // The outer command already ran initialize(); copy its loaded
            // libraries (db, cache, session …) instead of re-autoloading.
            // Re-running _ci_autoloader() in a nested constructor crashes
            // drivers like CI_Cache_file which call get_instance() before
            // the new singleton is fully set up.
            foreach (get_object_vars($existingCI) as $key => $value) {
                $this->$key = $existingCI->$key;
            }
        } else {
            // ── Top-level command ────────────────────────────────────────────
            // Copy dynamic properties (view, mailer, gate …) that service
            // providers set via app($key, $value) and are not in is_loaded().
            foreach (get_object_vars($existingCI) as $key => $value) {
                if (!isset($this->$key)) {
                    $this->$key = $value;
                }
            }

            $this->load->initialize();
        }
    }

    /**
     * CI router entry-point — registered by Kernel::registerDiscoveredRoutes().
     *
     * @return void
     */
    final public function execute(): void
    {
        if ($this->option('h') || $this->option('help')) {
            $this->printHelp();
            exit(0);
        }

        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        $statusCode = $this->$method();

        exit(is_numeric($statusCode) ? (int) $statusCode : 0);
    }

    /**
     * Print command help: Description, Usage, Arguments, Options.
     *
     * @return void
     */
    public function printHelp(): void
    {
        [$name, $arguments, $options] = Parser::parse($this->signature);

        $maxLen = 24;

        if ($this->description !== '') {
            $this->warn('Description:');
            $this->line('  ' . $this->description, 'light_gray');
            $this->newLine();
        }

        $usage = '  ' . $name;
        foreach ($arguments as $arg) {
            $usage .= $arg['required'] ? ' <' . $arg['name'] . '>' : ' [' . $arg['name'] . ']';
        }
        if (!empty($options)) {
            $usage .= ' [options]';
        }

        $this->warn('Usage:');
        $this->line($usage, 'light_gray');
        $this->newLine();

        if (!empty($arguments)) {
            $this->warn('Arguments:');
            foreach ($arguments as $arg) {
                $title = '  ' . $arg['name'];
                $desc = $arg['description'] ?? '';
                $this->line(
                    substr($title . str_repeat(' ', $maxLen + 3), 0, $maxLen + 3)
                    . OutputStyle::wrap(OutputStyle::color($desc, 'light_gray'), 120, $maxLen + 3),
                    'green'
                );
            }
            $this->newLine();
        }

        if (!empty($options)) {
            $this->warn('Options:');
            foreach ($options as $opt) {
                $short = !empty($opt['shortcut']) ? '-' . $opt['shortcut'] . ', ' : '    ';
                $title = '  ' . $short . '--' . $opt['name'];
                $desc = $opt['description'] ?? '';
                $this->line(
                    substr($title . str_repeat(' ', $maxLen + 3), 0, $maxLen + 3)
                    . OutputStyle::wrap(OutputStyle::color($desc, 'light_gray'), 120, $maxLen + 3),
                    'green'
                );
            }
            $this->newLine();
        }
    }
}
