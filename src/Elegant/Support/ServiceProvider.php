<?php

namespace Elegant\Support;

/**
 * Elegant ServiceProvider — Laraigniter adaptation of Laravel's ServiceProvider.
 *
 * Key differences from Illuminate\Support\ServiceProvider:
 *  • No $app constructor injection — uses the app() helper directly.
 *  • loadViewsFrom() works synchronously (view is already resolved during
 *    PostControllerConstructor), so no callAfterResolving() pattern is needed.
 *  • Adapted config access: app('config')->item() instead of $this->app->config[].
 *  • publishes() / pathsToPublish() mirror the Illuminate API for future
 *    vendor:publish artisan command support.
 */
abstract class ServiceProvider
{
    /**
     * The paths that should be published, keyed by provider class name.
     *
     * @var array<string, array<string, string>>
     */
    public static array $publishes = [];

    /**
     * The paths that should be published, keyed by group tag.
     *
     * @var array<string, array<string, string>>
     */
    public static array $publishGroups = [];

    // -------------------------------------------------------------------------
    // Views
    // -------------------------------------------------------------------------

    /**
     * Register a view file namespace, with app-level override support.
     *
     * Mirrors Laravel's ServiceProvider::loadViewsFrom() behaviour:
     *
     *   For each path in config('view.paths'):
     *       if {viewPath}/vendor/{namespace}/ exists  → addNamespace (override, checked FIRST)
     *   addNamespace(packagePath)                     → package default (fallback)
     *
     * Copying a view to resources/views/vendor/{namespace}/ is sufficient to override it.
     *
     * @param  string|array  $path       Absolute path to the package's view directory.
     * @param  string        $namespace  Blade namespace (e.g. 'pagination').
     * @return void
     */
    protected function loadViewsFrom($path, string $namespace): void
    {
        $view = app('view');

        // Check each configured view root for a published override directory.
        $viewPaths = (array) (app('config')->item('paths', 'view') ?? []);

        foreach ($viewPaths as $viewPath) {
            $overridePath = rtrim((string) $viewPath, '/\\') . '/vendor/' . $namespace;

            if (is_dir($overridePath)) {
                // addNamespace() merges: [existing…, $new], so the override path —
                // added BEFORE the package path below — stays at the front of the list.
                $view->addNamespace($namespace, $overridePath);
            }
        }

        // Register the package path as the fallback.
        $view->addNamespace($namespace, $path);
    }

    // -------------------------------------------------------------------------
    // Publishing
    // -------------------------------------------------------------------------

    /**
     * Register paths to be published by the vendor:publish command.
     *
     * @param  array<string, string>       $paths   [ source => destination ]
     * @param  string|string[]|null        $groups  Tag(s) for selective publishing.
     * @return void
     */
    protected function publishes(array $paths, $groups = null): void
    {
        $this->ensurePublishArrayInitialized($class = static::class);

        static::$publishes[$class] = array_merge(static::$publishes[$class], $paths);

        foreach ((array) $groups as $group) {
            $this->addPublishGroup($group, $paths);
        }
    }

    /**
     * Ensure the publish array for the provider is initialised.
     */
    protected function ensurePublishArrayInitialized(string $class): void
    {
        if (! array_key_exists($class, static::$publishes)) {
            static::$publishes[$class] = [];
        }
    }

    /**
     * Add a group / tag entry to the publish groups registry.
     */
    protected function addPublishGroup(string $group, array $paths): void
    {
        if (! array_key_exists($group, static::$publishGroups)) {
            static::$publishGroups[$group] = [];
        }

        static::$publishGroups[$group] = array_merge(static::$publishGroups[$group], $paths);
    }

    /**
     * Get all publishable paths, optionally filtered by provider and/or group.
     *
     * @param  string|null  $provider  Provider class name.
     * @param  string|null  $group     Tag name.
     * @return array<string, string>
     */
    public static function pathsToPublish(?string $provider = null, ?string $group = null): array
    {
        if (! is_null($paths = static::pathsForProviderOrGroup($provider, $group))) {
            return $paths;
        }

        return array_reduce(array_values(static::$publishes), 'array_merge', []);
    }

    /**
     * Resolve the publishable paths for a given provider and/or group.
     *
     * @return array<string, string>|null  Returns null when no filter is given.
     */
    protected static function pathsForProviderOrGroup(?string $provider, ?string $group): ?array
    {
        if ($provider && $group) {
            return static::pathsForProviderAndGroup($provider, $group);
        }

        if ($group && array_key_exists($group, static::$publishGroups)) {
            return static::$publishGroups[$group];
        }

        if ($provider && array_key_exists($provider, static::$publishes)) {
            return static::$publishes[$provider];
        }

        if ($group || $provider) {
            return [];
        }

        return null;
    }

    /**
     * Get the intersection of provider paths and group paths.
     *
     * @return array<string, string>
     */
    protected static function pathsForProviderAndGroup(string $provider, string $group): array
    {
        if (! empty(static::$publishes[$provider]) && ! empty(static::$publishGroups[$group])) {
            return array_intersect_key(static::$publishes[$provider], static::$publishGroups[$group]);
        }

        return [];
    }

    /**
     * Get all provider class names that have registered publishable paths.
     *
     * @return string[]
     */
    public static function publishableProviders(): array
    {
        return array_keys(static::$publishes);
    }

    /**
     * Get all group tags that have registered publishable paths.
     *
     * @return string[]
     */
    public static function publishableGroups(): array
    {
        return array_keys(static::$publishGroups);
    }
}

