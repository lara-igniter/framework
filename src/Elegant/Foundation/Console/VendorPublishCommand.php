<?php

namespace Elegant\Foundation\Console;

use Elegant\Console\Command;
use Elegant\Console\OutputStyle;
use Elegant\Support\Facades\File;
use Elegant\Support\ServiceProvider;

class VendorPublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'vendor:publish
                            {--provider= : The service provider that has assets you want to publish}
                            {--tag= : One specific tag of assets you want to publish}
                            {--force : Overwrite any existing files}';

    /**
     * The name of the console command.
     *
     * @var string|null
     */
    protected static ?string $defaultName = 'vendor:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Publish any publishable assets from vendor packages';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $provider = $this->option('provider') ?: null;
        $tag = $this->option('tag') ?: null;
        $force = (bool)$this->option('force');

        if (is_null($provider) && is_null($tag)) {
            $this->showList();
            return;
        }

        $paths = ServiceProvider::pathsToPublish($provider, $tag);

        if (empty($paths)) {
            $this->warn($provider
                ? "No publishable resources for provider [{$provider}]."
                : "No publishable resources for tag [{$tag}]."
            );
            return;
        }

        $published = 0;
        $skipped = 0;

        foreach ($paths as $source => $destination) {
            if (is_dir($source)) {
                $this->publishDirectory($source, $destination, $force, $published, $skipped);
            } else {
                $this->publishFile($source, $destination, $force, $published, $skipped);
            }
        }

        $this->newLine();

        if ($published === 0 && $skipped > 0) {
            $this->line(
                'No new files published. ' .
                OutputStyle::color((string)$skipped, 'yellow') .
                ' file(s) already exist. Use ' .
                OutputStyle::color('--force', 'yellow') .
                ' to overwrite.'
            );
        } else {
            $this->info('Publishing complete.');
        }
    }

    /**
     * Display the list of publishable providers and tags.
     *
     * @return void
     */
    protected function showList(): void
    {
        $providers = ServiceProvider::publishableProviders();
        $groups = ServiceProvider::publishableGroups();

        if (empty($providers) && empty($groups)) {
            $this->warn('No publishable resources found.');
            $this->line('Run ' . OutputStyle::color('vendor:publish -h', 'green') . ' for usage.');
            return;
        }

        if (!empty($providers)) {
            $this->warn('Publishable providers:');

            foreach ($providers as $providerClass) {
                $this->line('  ' . OutputStyle::color($providerClass, 'green'));
            }

            $this->newLine();
        }

        if (!empty($groups)) {
            $this->warn('Publishable tags:');

            foreach ($groups as $group) {
                $this->line('  ' . OutputStyle::color($group, 'green'));
            }

            $this->newLine();
        }

        $this->line('Run ' . OutputStyle::color('vendor:publish -h', 'green') . ' for usage.');
        $this->newLine();
    }

    /**
     * Publish a single file from source to destination.
     *
     * @param string $source
     * @param string $destination
     * @param bool $force
     * @param int $published
     * @param int $skipped
     * @return void
     */
    protected function publishFile(
        string $source,
        string $destination,
        bool   $force,
        int    &$published,
        int    &$skipped
    ): void
    {
        if (!File::exists($source)) {
            $this->error("Source [{$source}] not found.");
            return;
        }

        if (File::exists($destination) && !$force) {
            $skipped++;
            $this->line(
                OutputStyle::color('Skipping', 'yellow') .
                ' [' . OutputStyle::color($destination, 'light_gray') . '] already exists.'
            );
            return;
        }

        File::makeDirectory(dirname($destination), 0755, true, true);
        File::copy($source, $destination);

        $published++;

        $this->line(
            'Copying [' . OutputStyle::color($source, 'light_gray') . ']' .
            ' to [' . OutputStyle::color($destination, 'light_gray') . ']  ' .
            OutputStyle::color('DONE', 'green')
        );
    }

    /**
     * Recursively publish all files inside a source directory.
     *
     * @param string $source
     * @param string $destination
     * @param bool $force
     * @param int $published
     * @param int $skipped
     * @return void
     */
    protected function publishDirectory(
        string $source,
        string $destination,
        bool   $force,
        int    &$published,
        int    &$skipped
    ): void
    {
        if (!is_dir($source)) {
            $this->error("Source directory [{$source}] not found.");
            return;
        }

        foreach (File::allFiles($source) as $file) {
            $destFile = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . $file->getRelativePathname();

            $this->publishFile($file->getPathname(), $destFile, $force, $published, $skipped);
        }
    }
}
