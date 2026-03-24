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
            if ($provider) {
                OutputStyle::write("No publishable resources for provider [{$provider}].", 'yellow');
            } else {
                OutputStyle::write("No publishable resources for tag [{$tag}].", 'yellow');
            }
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

        OutputStyle::newLine();

        if ($published === 0 && $skipped > 0) {
            OutputStyle::write(
                'No new files published. ' .
                OutputStyle::color((string)$skipped, 'yellow') .
                ' file(s) already exist. Use ' .
                OutputStyle::color('--force', 'yellow') .
                ' to overwrite.',
                'light_gray'
            );
        } else {
            OutputStyle::write('Publishing complete.', 'green');
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
            OutputStyle::write('No publishable resources found.', 'yellow');
            return;
        }

        if (!empty($providers)) {
            OutputStyle::write('Publishable providers:', 'yellow');

            foreach ($providers as $providerClass) {
                OutputStyle::write('  ' . OutputStyle::color($providerClass, 'green'));
            }

            OutputStyle::newLine();
        }

        if (!empty($groups)) {
            OutputStyle::write('Publishable tags:', 'yellow');

            foreach ($groups as $group) {
                OutputStyle::write('  ' . OutputStyle::color($group, 'green'));
            }

            OutputStyle::newLine();
        }

        OutputStyle::write('Publish a provider:', 'yellow');
        OutputStyle::write('  vendor:publish --provider=<ProviderClass>', 'light_gray');
        OutputStyle::newLine();

        OutputStyle::write('Publish a tag:', 'yellow');
        OutputStyle::write('  vendor:publish --tag=<tag>', 'light_gray');
        OutputStyle::newLine();
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
            OutputStyle::error("Source [{$source}] not found.", 'light_gray', 'red');
            return;
        }

        if (File::exists($destination) && !$force) {
            $skipped++;
            OutputStyle::write(
                OutputStyle::color('Skipping', 'yellow') .
                ' [' . OutputStyle::color($destination, 'light_gray') . '] already exists.'
            );
            return;
        }

        File::makeDirectory(dirname($destination), 0755, true, true);
        File::copy($source, $destination);

        $published++;

        OutputStyle::write(
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
            OutputStyle::error("Source directory [{$source}] not found.", 'light_gray', 'red');
            return;
        }

        foreach (File::allFiles($source) as $file) {
            $destFile = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . $file->getRelativePathname();

            $this->publishFile($file->getPathname(), $destFile, $force, $published, $skipped);
        }
    }
}
