<?php

namespace Elegant\Database\Model\Concerns;

trait HasEvents
{
    /**
     * User exposed retrieving observable event.
     *
     * @var array
     */
    protected array $retrieving = [];

    /**
     * User exposed retrieved observable event.
     *
     * @var array
     */
    protected array $retrieved = [];

    /**
     * User exposed creating observable event.
     *
     * @var array
     */
    protected array $creating = [];

    /**
     * User exposed created observable event.
     *
     * @var array
     */
    protected array $created = [];

    /**
     * User exposed updating observable event.
     *
     * @var array
     */
    protected array $updating = [];

    /**
     * User exposed updated observable event.
     *
     * @var array
     */
    protected array $updated = [];

    /**
     * User exposed deleting observable event.
     *
     * @var array
     */
    protected array $deleting = [];

    /**
     * User exposed deleted observable event.
     *
     * @var array
     */
    protected array $deleted = [];

    /**
     * User exposed force-deleting observable event.
     *
     * @var array
     */
    protected array $forceDeleting = [];

    /**
     * User exposed force-deleted observable event.
     *
     * @var array
     */
    protected array $forceDeleted = [];

    /**
     * User exposed restoring observable event.
     *
     * @var array
     */
    protected array $restoring = [];

    /**
     * User exposed restored observable event.
     *
     * @var array
     */
    protected array $restored = [];

    /**
     * @var array
     */
    protected array $callbackParameters = [];

    /**
     * Events to be temporarily disabled.
     *
     * @var array
     */
    protected static array $mutedEvents = [];

    /**
     * Events that are disabled for this model instance.
     *
     * @var array
     */
    protected array $events = [];

    /**
     * Fire the given event for the model.
     *
     * @param string $event
     * @param array|int $data
     * @param bool $last
     * @return array|mixed
     */
    protected function fireModelEvent(string $event, $data = [], bool $last = true)
    {
        if (empty($this->events) && !empty(static::$mutedEvents)) {
            $this->events = static::$mutedEvents;
            static::$mutedEvents = [];
        }

        if (in_array('*', $this->events, true) || in_array($event, $this->events, true)) {
            return $data;
        }

        if (isset($this->$event) && is_array($this->$event)) {
            foreach ($this->$event as $method) {
                if (strpos($method, '(')) {
                    preg_match('/([a-zA-Z0-9\_\-]+)(\(([a-zA-Z0-9\_\-\., ]+)\))?/', $method, $matches);
                    $method = $matches[1];
                    $this->callbackParameters = explode(',', $matches[3]);
                }

                $data = call_user_func_array([$this, $method], [$data, $last]);
            }
        }

        return $data;
    }

    /**
     * Register a retrieving model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */
    public function retrieving($data = [])
    {
        return static::fireModelEvent('retrieving', $data);
    }

    /**
     * Register a retrieved model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */
    public function retrieved($data = [])
    {
        return static::fireModelEvent('retrieved', $data);
    }

    /**
     * Register a creating model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */

    public function creating($data = [])
    {
        return static::fireModelEvent('creating', $data);
    }

    /**
     * Register a created model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */
    public function created($data = [])
    {
        return static::fireModelEvent('created', $data);
    }

    /**
     * Register a updating model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */

    public function updating($data = [])
    {
        return static::fireModelEvent('updating', $data);
    }

    /**
     * Register a updated model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */
    public function updated($data = [])
    {
        return static::fireModelEvent('updated', $data);
    }

    /**
     * Register a deleting model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */

    public function deleting($data = [])
    {
        return static::fireModelEvent('deleting', $data);
    }

    /**
     * Register a deleted model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */
    public function deleted($data = [])
    {
        return static::fireModelEvent('deleted', $data);
    }

    /**
     * Register a force-deleting model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */
    public function forceDeleting($data = [])
    {
        return static::fireModelEvent('forceDeleting', $data);
    }

    /**
     * Register a force-deleted model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */
    public function forceDeleted($data = [])
    {
        return static::fireModelEvent('forceDeleted', $data);
    }

    /**
     * Register a restoring model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */

    public function restoring($data = [])
    {
        return static::fireModelEvent('restoring', $data);
    }

    /**
     * Register a restored model observer.
     *
     * @param array|int $data
     * @return array|int|mixed
     */
    public function restored($data = [])
    {
        return static::fireModelEvent('restored', $data);
    }

    /**
     * Execute a callback without firing any model events for any model type.
     *
     * @param callable $callback
     * @return mixed
     */
    public static function withoutEvents(callable $callback)
    {
        static::$mutedEvents = ['*'];

        try {
            return $callback();
        } finally {
            static::$mutedEvents = [];
        }
    }
}
