<?php

namespace Elegant\Database\Model\Concerns;

use Closure;
use Elegant\Database\Model\Scope;
use InvalidArgumentException;

trait HasGlobalScopes
{
    /**
     * All global scopes registered on this model class.
     * @var bool
     */
    protected bool $globalScopesApplied = false;

    protected function initializeHasGlobalScopes(): void
    {
        if (!in_array('applyGlobalScopes', $this->retrieving, true)) {
            $this->retrieving[] = 'applyGlobalScopes';
        }
    }

    /**
     * Register a new global scope on the model.
     *
     * Accepts any of the following forms:
     *
     *   static::addGlobalScope(new SomeScope());
     *   static::addGlobalScope('name', new SomeScope());
     *   static::addGlobalScope('name', function (Model $model) { … });
     *   static::addGlobalScope(function (Model $model) { … });
     *   static::addGlobalScope(SomeScope::class);
     *
     * @param Scope|Closure|string $scope
     * @param Scope|Closure|null $implementation
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public static function addGlobalScope($scope, $implementation = null): void
    {
        if (is_string($scope) && ($implementation instanceof Closure || $implementation instanceof Scope)) {
            static::$globalScopes[static::class][$scope] = $implementation;
            return;
        }

        if ($scope instanceof Closure) {
            static::$globalScopes[static::class][spl_object_hash($scope)] = $scope;
            return;
        }

        if ($scope instanceof Scope) {
            static::$globalScopes[static::class][get_class($scope)] = $scope;
            return;
        }

        if (is_string($scope) && class_exists($scope) && is_subclass_of($scope, Scope::class)) {
            static::$globalScopes[static::class][$scope] = new $scope();
            return;
        }

        throw new InvalidArgumentException(
            'Global scope must be an instance of Closure or ' . Scope::class . ', or a class name implementing it.'
        );
    }

    /**
     * Remove a previously registered global scope from the model.
     *
     * @param Scope|string $scope
     * @return void
     */
    public static function removeGlobalScope($scope): void
    {
        $identifier = is_string($scope) ? $scope : get_class($scope);
        unset(static::$globalScopes[static::class][$identifier]);
    }

    /**
     * Determine whether a global scope is registered on this model.
     *
     * @param Scope|string $scope
     * @return bool
     */
    public static function hasGlobalScope($scope): bool
    {
        return !is_null(static::getGlobalScope($scope));
    }

    /**
     * Get a specific registered global scope, or null if not found.
     *
     * @param Scope|string $scope
     * @return Scope|Closure|null
     */
    public static function getGlobalScope($scope)
    {
        $identifier = is_string($scope) ? $scope : get_class($scope);
        return static::$globalScopes[static::class][$identifier] ?? null;
    }

    /**
     * Get all global scopes registered on this model class.
     *
     * @return array<string, Scope|Closure>
     */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    /**
     * Exclude one global scope for the current query.
     *
     * @param Scope|string $scope
     * @return self
     */
    public function withoutGlobalScope($scope): self
    {
        $this->removedScopes[] = is_string($scope) ? $scope : get_class($scope);
        return $this;
    }

    /**
     * Exclude multiple (or all) global scopes for the current query.
     *
     * @param array<Scope|string>|null $scopes null = remove all registered scopes
     * @return self
     */
    public function withoutGlobalScopes(array $scopes = null): self
    {
        if (is_null($scopes)) {
            $this->removedScopes = array_keys(static::getGlobalScopes());
        } else {
            foreach ($scopes as $scope) {
                $this->removedScopes[] = is_string($scope) ? $scope : get_class($scope);
            }
        }
        return $this;
    }


    /**
     * Apply all registered global scopes to the current database query builder.
     *
     * This method is registered as a retrieving event callback by
     * initializeHasScopes() so it runs automatically before every query.
     * It is a standard event callback: receives $data, returns $data unchanged,
     * and applies constraints as side-effects on $this->database.
     *
     * The $globalScopesApplied guard prevents double-application when both
     * paginate() and the get_all() it calls internally fire the retrieving event.
     * The flag is reset by _prep_after_read() after the query completes.
     *
     * Scope types supported:
     *   • Scope instance → calls static ScopeClass::apply($this->database)
     *   • Closure → calls $closure($this) (model acts as query builder)
     *
     * @param mixed $data Data forwarded by the retrieving event (usually [])
     * @param bool $last Event chain flag forwarded by fireModelEvent()
     * @return mixed        Returns $data unchanged (passthrough)
     */
    public function applyGlobalScopes($data = [], bool $last = true)
    {
        // Guard: if scopes were already applied for this query execution, skip.
        // _prep_after_read() resets this flag once the query result is processed.
        if ($this->globalScopesApplied) {
            return $data;
        }

        $this->globalScopesApplied = true;

        foreach (static::getGlobalScopes() as $identifier => $scope) {
            if (in_array($identifier, $this->removedScopes, true)) {
                continue;
            }

            if ($scope instanceof Closure) {
                // Closure receives the model instance (which is the query builder)
                $scope($this);
            } elseif ($scope instanceof Scope) {
                // Scope object: call its static apply() with the raw CI_DB driver.
                // PHP allows calling static methods via an object instance.
                $scope::apply($this->database);
            }
        }

        // Reset per-query exclusions after applying so they don't bleed into
        // the next query on this instance.
        $this->removedScopes = [];

        return $data;
    }
}

