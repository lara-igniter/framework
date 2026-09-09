<?php

namespace Elegant\Contracts\Foundation;

interface Application
{
    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version(): string;

    /**
     * Get the base path of the Laraigniter installation.
     *
     * @param string $path
     * @return string
     */
    public function basePath(string $path = ''): string;

    /**
     * Register a shared binding in the container.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     * @return void
     */
    public function singleton($abstract, $concrete = null): void;

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @return mixed
     */
    public function make($abstract);
}
