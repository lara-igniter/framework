<?php

namespace Elegant\Database\Model;

use BadMethodCallException;
use Elegant\Foundation\Exceptions\MassAssignmentException;
use Elegant\Pagination\Cursor;
use Elegant\Pagination\CursorPaginator;
use Elegant\Pagination\LengthAwarePaginator;
use Elegant\Support\Arr;
use Elegant\Support\Collection;
use Elegant\Support\Str;
use InvalidArgumentException;

abstract class Model extends \CI_Model
{
    use Concerns\HasAttributes,
        Concerns\HasEvents,
        Concerns\HasGlobalScopes,
        Concerns\HasHelpFunctions,
        Concerns\HasRelationships,
        Concerns\HasTimestamps,
        Concerns\HasTrashed,
        Concerns\GuardsAttributes;

    /**
     * Select the database connection from the group names defined inside the database.php configuration file or an array.
     *
     * @var string
     */
    protected string $connection;

    /**
     * This one will hold the database connection object
     *
     * @var \CI_DB
     */
    protected \CI_DB $database;


    /**
     * The number of models to return for pagination.
     *
     * @var int
     */
    public static int $perPage = 10;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected string $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * @var null|array
     */
    protected ?array $protected = null;

    private string $model_name;

    private $model_limit;

    private $count_rows;

    /**
     * The columns that should be returned.
     *
     * @var array|string|null
     */
    public $columns = '*';

    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static array $booted = [];

    /**
     * The array of trait initializers that will be called on each new instance.
     *
     * @var array
     */
    protected static array $traitInitializers = [];

    /**
     * The array of global scopes on the model.
     *
     * @var array
     */
    protected static array $globalScopes = [];

    /**
     * The persisted attributes captured immediately before the current update.
     *
     * @var array<string, mixed>
     */
    protected array $originalAttributes = [];

    /**
     * The raw database attributes captured immediately before the current update.
     *
     * @var array<string, mixed>
     */
    protected array $rawOriginalAttributes = [];

    /**
     * Removed global scopes.
     *
     * @var array<string>
     */
    protected array $removedScopes = [];

    /**
     * Indicates if an exception should be thrown when trying to access a missing attribute on a retrieved model.
     *
     * @var bool
     */
    protected static bool $modelsShouldPreventAccessingMissingAttributes = false;

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updated_at';

    public function __construct(?string $connection = null)
    {
        parent::__construct();

        $this->bootIfNotBooted($connection);

        $this->initializeTraits();

        $modelClass = get_class($this);
        $modelName = Str::of($modelClass)->afterLast('\\');
        $modelFolder = Str::of($modelClass)->afterLast('App\\Models\\')->before($modelName);

        $this->model_name = $modelName->lower()->toString();

        // CI's loader only resolves application models. An App\Models wrapper whose
        // immediate parent is a Composer package model is already resolved by PSR-4;
        // loading it again can collide with a container service of the same name.
        $parentModelClass = get_parent_class($this);
        $isComposerBackedModel = is_string($parentModelClass)
            && $parentModelClass !== Model::class
            && strpos($parentModelClass, 'App\\Models\\') !== 0;

        if (strpos($modelClass, 'App\\Models\\') === 0 && ! $isComposerBackedModel) {
            $this->load->model(!empty($modelFolder) ? $modelFolder->lower()->toString() . $this->model_name : $this->model_name);
        }

        $this->table = !isset($this->table) ? $modelName->plural()->lower()->toString() : $this->table;

        $this->model_limit = ci()->session->has_userdata(strtolower($this->model_name) . '_limit')
            ? ci()->session->userdata(strtolower($this->model_name) . '_limit')
            : get_class($this)::$perPage;

        $this->fill();
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted(?string $connection = null)
    {
        $this->setConnection($connection);

        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            static::booting();
            static::boot();
            static::booted();
        }
    }

    /**
     * Perform any actions required before the model boots.
     *
     * @return void
     */
    protected static function booting()
    {
        //
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        static::bootTraits();
    }

    /**
     * Boot all of the bootable traits on the model.
     *
     * @return void
     */
    protected static function bootTraits()
    {
        $class = static::class;

        $booted = [];

        static::$traitInitializers[$class] = [];

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (method_exists($class, $method) && !in_array($method, $booted)) {
                forward_static_call([$class, $method]);

                $booted[] = $method;
            }

            if (method_exists($class, $method = 'initialize' . class_basename($trait))) {
                static::$traitInitializers[$class][] = $method;

                static::$traitInitializers[$class] = array_unique(
                    static::$traitInitializers[$class]
                );
            }
        }
    }

    /**
     * Initialize any initializable traits on the model.
     *
     * @return void
     */
    protected function initializeTraits()
    {
        foreach (static::$traitInitializers[static::class] as $method) {
            $this->{$method}();
        }
    }

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted()
    {
        //
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     *
     * @return void
     */
    public static function clearBootedModels()
    {
        static::$booted = [];
    }

    /**
     * Indicate that models should prevent accessing missing attributes.
     *
     * @param bool $shouldBeStrict
     * @return void
     */
    public static function shouldBeStrict(bool $shouldBeStrict = true)
    {
        static::preventAccessingMissingAttributes($shouldBeStrict);
    }

    /**
     * Prevent accessing missing attributes on retrieved models.
     *
     * @param bool $value
     * @return void
     */
    public static function preventAccessingMissingAttributes(bool $value = true)
    {
        static::$modelsShouldPreventAccessingMissingAttributes = $value;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @return $this
     *
     * @throws \Elegant\Foundation\Exceptions\MassAssignmentException;
     */
    public function fill(): Model
    {
        $totallyGuarded = $this->totallyGuarded();

        $profilerStatus = $this->database->save_queries;

        $this->database->save_queries = false;

        if (is_null($this->database->subdriver) || $this->database->subdriver !== 'odbc' || $this->database->dbdriver !== 'odbc') {
            $dbTableFields = [];
        } else {
            $dbTableFields = $this->database->table_exists($this->getTable())
                ? $this->database->list_fields($this->getTable())
                : [];
        }

        $this->database->save_queries = $profilerStatus;

        foreach ($this->fillableFromArray($dbTableFields) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->fillable[$key] = $value;
            } elseif ($totallyGuarded) {
                if (static::$modelsShouldPreventAccessingMissingAttributes) {
                    throw new MassAssignmentException(sprintf(
                        'Add [%s] to fillable property to allow mass assignment on [%s] model.',
                        $key, get_class($this)
                    ));
                }
            }
        }

        return $this;
    }

    /**
     * Begin querying the model on a given connection.
     *
     * @param string $name
     * @return Model
     */
    public function on(string $name = 'default'): Model
    {
        $this->database->close();

        $this->load->database($name);

        $this->database = $this->db;

        $this->connection = $this->db->dbdriver;

        return $this;
    }

    /**
     * Insert new records into the database.
     *
     * @param array $values
     * @param int $batch
     * @return bool
     */
    public function insert(array $values, int $batch = 1000): bool
    {
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case,
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        return $this->database->insert_batch($this->table, $values, null, $batch);
    }

    /**
     * Save a new model and return the instance.
     *
     * @param array $attributes
     * @return Model|$this
     */
    public function create(array $attributes = []): Model
    {
        $attributes = $this->fillableData($attributes);

        $saved = $this->performInsert($attributes);

        $keyName = $this->getKeyName();
        $lookup = array_key_exists($keyName, $attributes) && $attributes[$keyName] !== null && $attributes[$keyName] !== ''
            ? $attributes[$keyName]
            : ($saved ?: $attributes);

        $this->database->reset_query();

        $created = $this->where($lookup)->first();

        if (!is_null($created)) {
            static::created($created);
        }

        return $this->where($lookup);
    }

    /**
     * Save the model in the database without raising any events.
     *
     * @param array $attributes
     * @return Model|$this
     */
    public function createQuietly(array $attributes = []): Model
    {
        return static::withoutEvents(function () use ($attributes) {
            return $this->create($attributes);
        });
    }

    protected function performInsert(array $attributes): int
    {
        if ($this->usesTimestamps()) {
            $attributes = array_merge($attributes, $this->updateTimestamps());
        }

        $attributes = static::creating($attributes);

        if (!$this->database->insert($this->table, $attributes)) {
            return 0;
        }

        return $this->database->insert_id();
    }

    /**
     * Detach models from the relationship.
     *
     * @param string $related
     * @param array|string $ids
     * @param array|string|int|null $foreignId
     * @param string|null $table
     * @param bool $softDelete
     * @return false|mixed|string|void
     */
    public function detach(string $related, $ids = [], $foreignId = null, ?string $table = null, bool $softDelete = false)
    {
        $ids = (array)$ids;

        if (is_null($foreignId)) {
            $foreignId = $this->first()->id;
        }

        $instance = $this->newRelatedInstance($related);

        if (is_null($table)) {
            $table = $this->joiningTable($related, $instance);
        }

        if (is_array($foreignId)) {
            $foreignPivotKey = $foreignId;
        } else {
            $foreignPivotKey = $this->getForeignKey();
        }

        $relatedPivotKey = $instance->getForeignKey();

        $columns = $this->db->list_fields($table);

        $softDeleteColumn = null;
        foreach ($columns as $column) {
            if (strpos($column, 'deleted_at') !== false) {
                $softDeleteColumn = $column;
                break;
            }
        }

        $this->database->reset_query();

        if (empty($ids)) {
            if ($softDelete && !is_null($softDeleteColumn)) {
                if (is_array($foreignId)) {
                    return $this->database->where($foreignPivotKey)->update($table, [
                        $softDeleteColumn => now()
                    ]);
                } else {
                    return $this->database->where($foreignPivotKey, $foreignId)->update($table, [
                        $softDeleteColumn => now()
                    ]);
                }
            }

            if (is_array($foreignId)) {
                return $this->database->where($foreignPivotKey)->delete($table);
            }

            return $this->database->delete($table, [
                $foreignPivotKey => (int)$foreignId,
            ]);
        }

        $returned = [];

        if ($softDelete && !is_null($softDeleteColumn)) {
            return $this->database->where($foreignPivotKey, $foreignId)->update($table, [
                $softDeleteColumn => now()
            ]);
        }


        foreach ($ids as $id) {
            $returned[] = $this->database->delete($table, is_array($foreignId) ? $foreignPivotKey + [
                    $relatedPivotKey => $id,
                ] : [
                $foreignPivotKey => (int)$foreignId,
                $relatedPivotKey => $id,
            ]);
        }

        return $returned;
    }

    /**
     * Attach a model to the parent.
     *
     * @param string $related
     * @param $ids
     * @param array|int|null $foreignId
     * @param string|null $table
     * @param bool $softDelete
     * @return void
     */
    public function attach(string $related, $ids, $foreignId = null, ?string $table = null, bool $softDelete = false)
    {
        $ids = (array)$ids;

        if (is_null($foreignId)) {
            $foreignId = $this->first()->id;
        }

        $instance = $this->newRelatedInstance($related);

        if (is_null($table)) {
            $table = $this->joiningTable($related, $instance);
        }

        if (is_array($foreignId)) {
            $foreignPivotKey = $foreignId;
        } else {
            $foreignPivotKey = $this->getForeignKey();
        }

        $relatedPivotKey = $instance->getForeignKey();

        $columns = $this->db->list_fields($table);

        $softDeleteColumn = null;
        foreach ($columns as $column) {
            if (strpos($column, 'deleted_at') !== false) {
                $softDeleteColumn = $column;
                break;
            }
        }

        if (!empty($ids)) {
            foreach ($ids as $id) {
                if ($softDelete && !is_null($softDeleteColumn)) {
                    $this->database->where(is_array($foreignId) ? $foreignPivotKey + [
                            $relatedPivotKey => $id,
                        ] : [
                        $foreignPivotKey => $foreignId,
                        $relatedPivotKey => $id,
                    ])->update($table, [
                        $softDeleteColumn => null,
                    ]);

                    continue;
                }

                $this->database->replace($table, is_array($foreignId) ? $foreignPivotKey + [
                        $relatedPivotKey => $id,
                    ] : [
                    $foreignPivotKey => $foreignId,
                    $relatedPivotKey => $id,
                ]);
            }
        }
    }

    /**
     * Update the model in the database.
     *
     * @param array $attributes
     * @param array|int $where
     *
     * @return Model|$this
     */
    public function update(array $attributes, $where = []): Model
    {
        $attributes = $this->fillableData($attributes);

        $this->captureOriginalAttributesForUpdate($attributes, $where);

        $this->performUpdate($attributes, $where);

        static::updated(
            $this->find(!empty($where) ? $this->where($where)->first()->id : $this->first()->id)
        );

        return !empty($where) ? $this->where($where) : $this;
    }

    /**
     * Get the persisted attributes captured before the active update operation.
     *
     * @return array<string, mixed>
     */
    public function getOriginalAttributes(): array
    {
        return $this->originalAttributes;
    }

    /**
     * Get the raw persisted attributes captured before the active update operation.
     *
     * This avoids serializing hydrated date objects when an observer needs values
     * exactly as they were stored in the database.
     *
     * @return array<string, mixed>
     */
    public function getRawOriginalAttributes(): array
    {
        return $this->rawOriginalAttributes;
    }

    /**
     * Fetch the affected row without consuming the active query-builder state.
     *
     * Automatic model events retain their existing hydrated original attributes.
     * A separate raw snapshot is available for consumers that must compare values
     * without serializing nested date objects.
     *
     * @param array<string, mixed> $attributes
     * @param array|int|string $where
     */
    protected function captureOriginalAttributesForUpdate(array $attributes, $where): void
    {
        $builderState = $this->captureBuilderState();

        try {
            $this->updateWhere($attributes, $where);
            $rawOriginal = $this->database->get($this->table, 1)->row_array();

            $this->rawOriginalAttributes = is_array($rawOriginal) ? $rawOriginal : [];

            $this->restoreBuilderState($builderState);
            $this->updateWhere($attributes, $where);
            $original = $this->first();

            $this->originalAttributes = is_object($original)
                ? $this->object_to_array($original)
                : [];
        } finally {
            $this->restoreBuilderState($builderState);
        }
    }

    /**
     * Update the model in the database without raising any events.
     *
     * @param array $attributes
     * @param array|int $where
     *
     * @return Model|$this
     */
    public function updateQuietly(array $attributes, $where = []): Model
    {
        return static::withoutEvents(function () use ($attributes, $where) {
            return $this->update($attributes, $where);
        });
    }

    /**
     * @param array $attributes
     * @param $where
     * @return int
     */
    protected function performUpdate(array $attributes, $where): int
    {
        if ($this->usesTimestamps()) {
            $attributes = array_merge($attributes, $this->updateTimestampUpdatedAt());
        }

        $attributes = static::updating($attributes);

        $this->updateWhere($attributes, $where);

        if (!$this->database->update($this->table, $attributes)) {
            return 0;
        }

        return $this->database->affected_rows();
    }

    /**
     * public function delete($where)
     * Deletes data from table.
     * @param $where primary_key(s) Can receive the primary key value or a list of primary keys as array()
     * @return int|array Returns affected rows or false on failure
     */
    public function delete($where = null)
    {
        if (!empty($this->deleting) || !empty($this->deleted) || ((isset($this->soft_deletes) && $this->soft_deletes === true))) {
            $to_update = array();
            if (isset($where)) {
                $this->where($where);
            }
            $query = $this->database->get($this->table);
            foreach ($query->result() as $row) {
                $to_update[] = array($this->primaryKey => $row->{$this->primaryKey});
            }

            if (!empty($this->deleting)) {
                foreach ($to_update as &$row) {
                    $row = static::deleting($row);
                }
            }
        }

        if (isset($where)) {
            $this->where($where);
        }

        $affected_rows = 0;
        $affected_row_ids = [];
        if (isset($this->soft_deletes) && $this->soft_deletes === true) {
            if (isset($to_update) && count($to_update) > 0) {
                foreach ($to_update as &$row) {
                    $row[$this->deleted_at_column] = date($this->getDateFormat());
                }
                $affected_rows = $this->database->update_batch($this->table, $to_update, $this->primaryKey);

                /**
                 * Add $affected_row_ids variable return when
                 * soft delete enable on delete multiple
                 */
                foreach ($to_update as $update) {
                    $affected_row_ids[] = $update['id'];
                }

                $to_update['affected_rows'] = $affected_rows;

                static::deleted($to_update);
            }

            /**
             * Fix issue #227
             * https://github.com/avenirer/CodeIgniter-MY_Model/issues/227
             */
            $this->database->reset_query();

            //return $affected_rows;
            return $affected_row_ids;
        } else {
            if ($this->database->delete($this->table)) {
                $affected_rows = $this->database->affected_rows();
                if (!empty($this->deleted)) {
                    $to_update['affected_rows'] = $affected_rows;
                    $to_update = static::deleted($to_update);
                    $affected_rows = $to_update;
                }

                /**
                 * Fix issue #227
                 * https://github.com/avenirer/CodeIgniter-MY_Model/issues/227
                 */
                $this->database->reset_query();

                return $affected_rows;
            }
        }
        return false;
    }

    /**
     * Delete the model in the database without raising any events.
     *
     * @param $where
     *
     * @return int|array
     */
    public function deleteQuietly($where = null)
    {
        return static::withoutEvents(function () use ($where) {
            return $this->delete($where);
        });
    }

    /**
     * public function where($field_or_array = NULL, $operator_or_value = NULL, $value = NULL, $with_or = FALSE, $with_not = FALSE, $custom_string = FALSE)
     * Sets a where method for the $this object
     * @param array|string|int|null $field_or_array - can receive a field name or an array with more wheres...
     * @param array|string|int|null $operator_or_value - can receive a database operator or, if it has a field, the value to equal with
     * @param null $value - a value if it received a field name and an operator
     * @param bool $with_or - if set to true will create a or_where query type pr a or_like query type, depending on the operator
     * @param bool $with_not - if set to true will also add "NOT" in the where
     * @param bool $custom_string - if set to true, will simply assume that $field_or_array is actually a string and pass it to the where query
     * @return $this
     */
    public function where($field_or_array = null, $operator_or_value = null, $value = null, bool $with_or = false, bool $with_not = false, bool $custom_string = false): Model
    {
        if (is_array($field_or_array)) {
            $multi = $this->is_multidimensional($field_or_array);
            if ($multi === true) {
                foreach ($field_or_array as $where) {
                    $field = $where[0];
                    $operator_or_value = $where[1] ?? null;
                    $value = $where[2] ?? null;
                    $with_or = isset($where[3]);
                    $with_not = isset($where[4]);
                    $this->where($field, $operator_or_value, $value, $with_or, $with_not);
                }

                return $this;
            }
        }

        if ($with_or === true) {
            $where_or = 'or_where';
        } else {
            $where_or = 'where';
        }

        if ($with_not === true) {
            $not = '_not';
        } else {
            $not = '';
        }

        if ($custom_string === true) {
            $this->database->{$where_or}($field_or_array, null, false);
        } elseif (is_numeric($field_or_array)) {
            $this->database->{$where_or}(array($this->table . '.' . $this->primaryKey => $field_or_array));
        } elseif (is_array($field_or_array) && !isset($operator_or_value)) {
            $this->database->where($field_or_array);
        } elseif (!isset($value) && isset($field_or_array) && isset($operator_or_value) && !is_array($operator_or_value)) {
            $this->database->{$where_or}(array($this->table . '.' . $field_or_array => $operator_or_value));
        } elseif (!isset($value) && isset($field_or_array) && isset($operator_or_value) && is_array($operator_or_value) && !is_array($field_or_array)) {
            $this->database->{$where_or . $not . '_in'}($this->table . '.' . $field_or_array, $operator_or_value);
        } elseif (isset($field_or_array) && isset($operator_or_value) && isset($value)) {
            if (strtolower($operator_or_value) == 'like') {
                if ($with_not === true) {
                    $like = 'not_like';
                } else {
                    $like = 'like';
                }
                if ($with_or === true) {
                    $like = 'or_' . $like;
                }

                // Strip single/smart quotes before passing to CI's like() to prevent SQL
                // syntax errors when the MariaDB server runs with NO_BACKSLASH_ESCAPES mode,
                // where real_escape_string() backslash-escaping is not honoured.
                $sanitizedValue = str_replace(
                    ["'", "\u{2018}", "\u{2019}", "\u{02B9}", "\u{02BC}"],
                    '',
                    (string) $value
                );
                $this->database->{$like}($field_or_array, $sanitizedValue, 'both', true);
            } else {
                $this->database->{$where_or}($field_or_array . ' ' . $operator_or_value, $value);
            }
        }

        return $this;
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param int $limit
     * @param int $offset
     * @return $this
     */
    public function limit(int $limit, int $offset = 0): Model
    {
        $this->database->limit($limit, $offset);

        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     *
     * @param int $value
     * @return $this
     */
    public function offset($value): Model
    {
        $value = max(0, (int)$value);

        $this->database->offset($value);

        return $this;
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param $group
     * @return $this
     */
    public function groupBy($group): Model
    {
        $this->database->group_by($group);

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array|null $columns
     * @return \Elegant\Support\Collection
     */
    public function get(?array $columns = null): Collection
    {
        static::retrieving();

        if ($this->columns) {
            $this->database->select($this->columns);
        }

        if (!empty($this->_requested)) {
            foreach ($this->_requested as $requested) {
                if (isset($requested['parameters']['join'])) {
                    $this->_get_joined($requested);
                } else {
                    $this->database->select($this->table . '.' . $this->relations[$requested['request']]['local_key']);
                }
            }
        }

        if (isset($columns)) {
            $this->where($columns);
        }

        if (isset($this->soft_deletes) && $this->soft_deletes === true) {
            $this->whereTrashed();
        }

        $this->limit(1);
        $query = $this->database->get($this->table);
        $this->resetTrashed();

        if ($query->num_rows() == 1) {
            $row = $query->row_array();
            $row = static::retrieved($row);
            $row = $this->_prep_after_read([$row], false);

            return static::newCollection($row);
        } else {
            return static::newCollection();
        }
    }

    /**
     * Get the first record matching the attributes or instantiate it.
     *
     * @param array $attributes
     * @param array $values
     * @return mixed
     */
    public function firstOrNew(array $attributes = [], array $values = [])
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }

        return $this->newModelInstance(array_merge($attributes, $values));
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param array $attributes
     * @param array $values
     * @return mixed
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        if ($instance = $this->where($attributes)->first()) {

            $this->update(array_merge($attributes, $values), $instance->id);

            return $this->find($instance->id);
        }

        return $this->newModelInstance(array_merge($attributes, $values));
    }

    /**
     * Insert or update a record matching the attributes, and fill it with values.
     *
     * @param array $attributes
     * @param array $values
     * @return mixed
     */
    public function updateOrInsert(array $attributes, array $values = [])
    {
        if ($instance = $this->where($attributes)->first()) {
            if (!$this->usesTimestamps()) {
                Arr::forget($values, [
                    $this->getCreatedAtColumn(),
                    $this->getUpdatedAtColumn(),
                ]);
            }

            $this->update(array_merge($attributes, $values), $instance->id);

            return $this->find($instance->id);
        } else {
            $this->insert(array_merge($attributes, $values));
        }

        return $this->find($this->database->insert_id());
    }

    /**
     * Insert new records or update the existing ones.
     *
     * @param array $values
     * @param array|string $uniqueBy
     * @param array|null $update
     * @return int|void
     */
    public function upsert(array $values, $uniqueBy, ?array $update = null)
    {
        if (empty($values)) {
            return 0;
        } elseif ($update === []) {
            return (int)$this->newModelInstance($values)->id;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        if (is_null($update)) {
            $update = array_keys(reset($values));
        }

        $query = $this->compileUpsert($values, (array)$uniqueBy, $update);

        $this->db->query($query);
    }


    /**
     * Create a new instance of the model being queried.
     *
     * @param array $attributes
     * @return mixed
     */
    public function newModelInstance(array $attributes = [])
    {
        return $this->create($attributes)->first();
    }

    /**
     * Find a model by its primary key.
     *
     * @param int $id
     * @return mixed
     */
    public function find(int $id)
    {
        return $this->whereKey($id)->first();
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param $id
     * @return mixed
     *
     * @throws \Elegant\Database\Model\ModelNotFoundException
     */
    public function findOrFail($id)
    {
        if (!is_null($result = $this->find($id))) {
            return $result;
        }

        throw (new ModelNotFoundException)->setModel(
            get_class($this), $id
        );
    }

    /**
     * Execute the query and get the first result.
     *
     * @return mixed
     */
    public function first()
    {
        return $this->whereKey()->first();
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @return mixed
     *
     * @throws \Elegant\Database\Model\ModelNotFoundException
     */
    public function firstOrFail()
    {
        if (!is_null($model = $this->first())) {
            return $model;
        }

        throw (new ModelNotFoundException)->setModel(get_class($this));
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param array|object $models
     * @return \Elegant\Support\Collection
     */
    public static function newCollection($models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * Add a where clause on the primary key to the query.
     *
     * @param int|null $id
     * @return mixed
     */
    public function whereKey(?int $id = null)
    {
        if (is_null($id)) {
            return $this->get();
        }

        return $this->get([$this->getKeyName() => $id]);
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists(): bool
    {
        if ($results = $this->get()->first()) {
            return (bool)$results;
        }

        return false;
    }

    /**
     * @return \Elegant\Support\Collection
     */
    public function get_all(): Collection
    {
        static::retrieving();

        if (isset($this->soft_deletes) && $this->soft_deletes === true) {
            $this->whereTrashed();
        }

        if (isset($this->columns)) {
            $this->database->select($this->columns);
        }

        if (!empty($this->_requested)) {
            foreach ($this->_requested as $requested) {
                if (isset($requested['parameters']['join'])) {
                    $this->_get_joined($requested);
                } else {
                    $this->database->select($this->table . '.' . $this->relations[$requested['request']]['local_key']);
                }
            }
        }

        $query = $this->database->get($this->table);

        $this->resetTrashed();

        if ($query->num_rows() > 0) {
            $data = $query->result_array();

            foreach ($data as $key => $row) {
                $data[$key] = static::retrieved($row);
            }

            $data = $this->_prep_after_read($data);

            return static::newCollection($data);
        } else {
            return static::newCollection();
        }
    }

    /**
     * Get all of the models from the database.
     *
     * @return \Elegant\Support\Collection
     */
    public function all(): Collection
    {
        return $this->get_all();
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param null $where
     * @return int
     */
    public function count_rows($where = null): int
    {
        static::retrieving();

        if (isset($where)) {
            $this->where($where);
        }
        if (isset($this->soft_deletes) && $this->soft_deletes === true) {
            $this->whereTrashed();
        }
        $this->database->from($this->table);
        $number_rows = $this->database->count_all_results();
        $this->resetTrashed();
        return $number_rows;
    }

    public function whereDate($column, $operator, $value = null, $or = false): Model
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->whereRaw('DATE(' . $column . ') ' . $operator . " '" . $value . "'", $or);

        return $this;
    }

    public function orWhereDate($column, $operator, $value = null): Model
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->whereRaw('DATE(' . $column . ') ' . $operator . " '" . $value . "'", true);

        return $this;
    }

    public function whereYear($column, $operator, $value = null, $or = false): Model
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->whereRaw('YEAR(' . $column . ') ' . $operator . " '" . $value . "'", $or);

        return $this;
    }

    public function orWhereYear($column, $operator, $value = null): Model
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->whereYear($column, $operator, $value, true);

        return $this;
    }

    public function whereNull(string $column, bool $or = false): Model
    {
        $this->where($column . ' IS NULL', NULL, FALSE, $or, FALSE, TRUE);

        return $this;
    }

    public function orWhereNull(string $column): Model
    {
        $this->whereNull($column, true);

        return $this;
    }

    public function whereNotNull(string $column): Model
    {
        $this->where($column . ' IS NOT NULL', NULL, FALSE, FALSE, FALSE, TRUE);

        return $this;
    }

    public function whereRaw(string $sql, $boolean = false): Model
    {
        $this->where($sql, NULL, FALSE, $boolean, FALSE, TRUE);

        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): Model
    {
        $type = $not ? 'not_in' : 'in';

        if ($boolean === 'and') {
            $this->database->{'where_' . $type}($column, $values);
        }

        if ($boolean === 'or') {
            $this->database->{$boolean . '_where_' . $type}($column, $values);
        }

        return $this;
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function orWhereIn(string $column, array $values): Model
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @return $this
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): Model
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not in" clause to the query.
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function orWhereNotIn(string $column, array $values): Model
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    public function like(string $column, string $search): Model
    {
        $search = (string) $search;

        // Callers may still pass '%term%'; normalize so where(..., 'like', ...)
        // can apply CI escaping + surrounding wildcards without doubling them.
        if (strlen($search) >= 2 && $search[0] === '%' && substr($search, -1) === '%') {
            $search = substr($search, 1, -1);
        }

        return $this->where($column, 'like', $search);
    }

    public function orWhereRaw(string $sql): Model
    {
        $this->whereRaw($sql, true);

        return $this;
    }

    public function whereBetween(string $column, array $values, $boolean = false): Model
    {
        $values = array_slice(Arr::flatten($values), 0, 2);

        $this->whereRaw("$column BETWEEN '$values[0]' AND '$values[1]'", $boolean);

        return $this;
    }

    public function orWhereBetween(string $column, array $values): Model
    {
        $this->whereBetween($column, $values, true);

        return $this;
    }

    /**
     * Add a "where JSON contains" clause to the query.
     *
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereJsonContains(string $column, $value, string $boolean = 'and', bool $not = false): Model
    {
        if (strpos($column, '->') !== false) {
            [$field, $path] = explode('->', $column, 2);
            $jsonPath = '$.' . $path;
        } else {
            $field = $column;
            $jsonPath = '$';
        }

        $jsonValue = json_encode($value);

        $operator = $not ? 'NOT JSON_CONTAINS' : 'JSON_CONTAINS';

        $or = !($boolean === 'and');

        return $this->whereRaw("{$operator}(`{$field}`, '{$jsonValue}', '{$jsonPath}')", $or);
    }

    /**
     * Add an "or where JSON contains" clause to the query.
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function orWhereJsonContains(string $column, $value): Model
    {
        return $this->whereJsonContains($column, $value, 'or');
    }

    /**
     * Add a "where JSON not contains" clause to the query.
     *
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function whereJsonDoesntContain(string $column, $value, string $boolean = 'and'): Model
    {
        return $this->whereJsonContains($column, $value, $boolean, true);
    }

    /**
     * Add an "or where JSON not contains" clause to the query.
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function orWhereJsonDoesntContain(string $column, $value): Model
    {
        return $this->whereJsonDoesntContain($column, $value, 'or');
    }

    public function query(): Model
    {
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): Model
    {
        $this->where($column, $operator, $value, true);

        return $this;
    }

    public function group(callable $callback, string $type = 'and'): Model
    {
        $this->group_start('', Str::upper($type));

        $callback($this);

        $this->group_end();

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param string $column
     * @param string $direction
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function orderBy(string $column, string $direction = 'DESC'): Model
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC', 'RANDOM'], true)) {
            throw new InvalidArgumentException('Order direction must be "ASC" or "DESC" or "RANDOM".');
        }

        $this->order_by($column, $direction);

        return $this;
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param string $column
     * @return $this
     */
    public function latest(string $column = 'created_at'): Model
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param string $column
     * @return $this
     */
    public function oldest(string $column = 'created_at'): Model
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Begin querying a model with eager loading.
     *
     * @param string|array $request
     * @param array $arguments
     * @return $this
     */
    public function with($request, array $arguments = []): Model
    {
        if (is_array($request)) {
            foreach ($request as $item) {
                $this->with($item);
            }

            return $this;
        }

        $this->_set_relationships();

        if (array_key_exists($request, $this->relations)) {
            $this->_requested[$request] = array('request' => $request);
            $parameters = array();

            if (isset($arguments)) {
                foreach ($arguments as $argument) {
                    if (is_array($argument)) {
                        foreach ($argument as $k => $v) {
                            $parameters[$k] = $v;
                        }
                    } else {
                        $requested_operations = explode('|', $argument);
                        foreach ($requested_operations as $operation) {
                            $elements = explode(':', $operation, 2);
                            if (sizeof($elements) == 2) {
                                $parameters[$elements[0]] = $elements[1];
                            } else {
                                show_error('MY_Model: Parameters for with_*() method must be of the form: "...->with_*(\'where:...|fields:...\')"');
                            }
                        }
                    }
                }
            }
            $this->_requested[$request]['parameters'] = $parameters;
        }

        return $this;
    }

    /**
     * Resets the connection to the default used for all the model
     *
     * @return $this
     */
    public function resetConnection(): Model
    {
        $this->database->close();

        return $this->setConnection();
    }

    /**
     * Set the columns to be selected.
     *
     * @param array|string|null $columns
     * @return $this
     */
    public function select($columns = null): Model
    {
        if (isset($columns)) {
            if ($columns === '*count*') {
                $this->columns = '';

                $this->database->select('COUNT(*) AS counted_rows', false);
            } else {
                $this->columns = [];

                $columns = (!is_array($columns)) ? explode(',', $columns) : $columns;

                if (!empty($columns)) {
                    foreach ($columns as &$column) {
                        $exploded = explode('.', $column);

                        if (sizeof($exploded) < 2) {
                            $column = $this->table . '.' . $column;
                        }
                    }
                }

                $this->columns = $columns;
            }
        } else {
            $this->columns = null;
        }

        return $this;
    }

    /**
     * Determine if the model has a given scope.
     *
     * @param string $scope
     * @return bool
     */
    public function hasNamedScope(string $scope): bool
    {
        return method_exists($this, 'scope' . ucfirst($scope));
    }

    /**
     * Apply the given named scope if possible.
     *
     * The model instance ($this) is always passed as the first argument so
     * scopes can be written in the same Laravel-style convention:
     *
     *   public function scopeActive(MY_Model $query): Model
     *   {
     *       return $query->where('active', 1);
     *   }
     *
     * If the scope returns nothing (void) $this is returned to keep the
     * fluent chain intact.
     *
     * @param string $scope
     * @param array $parameters
     * @return static
     */
    public function callNamedScope(string $scope, array $parameters = [])
    {
        $result = $this->{'scope' . ucfirst($scope)}($this, ...$parameters);

        return $result ?? $this;
    }

    /**
     * Sets the connection to database
     *
     * @param string|null $name
     * @return $this
     */
    public function setConnection(?string $name = null): Model
    {
        if (!is_null($name)) {
            $this->database = $this->load->database($name, true);
        } else {
            $this->load->database();
            $this->database = $this->db;
        }

        $this->connection = $this->database->dbdriver;

        $this->setTable($this->getTable());

        return $this;
    }

    /**
     * Paginate the query results, returning a Laravel-style LengthAwarePaginator.
     *
     * Usage in a Blade view:  {{ $results->links() }}
     *
     * @param int $perPage Items per page (default 10)
     * @param array $columns Columns to select (default all)
     * @param string $pageName GET parameter that carries the page number
     * @param int|null $page Override the current page (resolved from GET when null)
     * @return \Elegant\Pagination\LengthAwarePaginator
     */
    public function paginate(int $perPage = 10, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $this->load->helper('url');

        // Resolve current page from the GET parameter or the explicit argument.
        $page = $page ?? max(1, (int)($this->input->get($pageName) ?: 1));

        // Override columns when a specific selection is requested.
        if ($columns !== ['*']) {
            $this->columns = implode(', ', $columns);
        }

        // Count total rows while preserving every pending query-builder condition.
        $builderState = $this->captureBuilderState();
        $total = $this->withoutTrashed()->count_rows();
        $this->restoreBuilderState($builderState);

        // Fire the "retrieving" model event, apply any pending where clauses,
        // then run the actual paginated SELECT.
        static::retrieving();
        $this->where();
        $this->limit($perPage, ($page - 1) * $perPage);
        $items = collect($this->get_all() ?: []);

        // Build the base URL (current URL without the query string).
        $path = rtrim(strtok(current_url(), '?'), '/') ?: '/';

        return (new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]))->withQueryString();
    }

    /**
     * Paginate the query results using a cursor (keyset pagination).
     *
     * More efficient than offset pagination for large datasets.
     * Usage in a Blade view:  {!! $results->links('pagination::cursor') !!}
     *
     * @param int $perPage Items per page (default 10)
     * @param string $column The column used as the cursor key (default: primary key)
     * @param string $cursorName GET parameter that carries the encoded cursor
     * @param array $columns Columns to select (default all)
     * @return \Elegant\Pagination\CursorPaginator
     */
    public function cursorPaginate(int $perPage = 10, string $column = '', string $cursorName = 'cursor', array $columns = ['*']): CursorPaginator
    {
        $this->load->helper('url');

        // Default cursor column to the model's primary key.
        if ($column === '') {
            $column = $this->primaryKey;
        }

        // Resolve the current cursor from the GET parameter.
        $cursor = Cursor::fromEncoded($this->input->get($cursorName) ?? null);

        // Override columns when a specific selection is requested.
        if ($columns !== ['*']) {
            $this->columns = implode(', ', $columns);
        }

        // Apply cursor WHERE clause and ordering.
        if (!is_null($cursor)) {
            $cursorValue = $cursor->parameter($column);

            if ($cursor->pointsToNextItems()) {
                // Moving forward: fetch items after cursor value.
                $this->db->where($this->table . '.' . $column . ' >', $cursorValue);
                $this->db->order_by($this->table . '.' . $column, 'ASC');
            } else {
                // Moving backward: fetch items before cursor value (reverse order, re-reversed by CursorPaginator).
                $this->db->where($this->table . '.' . $column . ' <', $cursorValue);
                $this->db->order_by($this->table . '.' . $column, 'DESC');
            }
        } else {
            $this->db->order_by($this->table . '.' . $column, 'ASC');
        }

        // Fetch one extra item to detect whether more pages exist.
        static::retrieving();
        $this->where();
        $this->limit($perPage + 1);
        $items = collect($this->get_all() ?: []);

        // Build the base URL (current URL without the query string).
        $path = rtrim(strtok(current_url(), '?'), '/') ?: '/';

        return (new CursorPaginator($items, $perPage, $cursor, [
            'path' => $path,
            'cursorName' => $cursorName,
            'parameters' => [$column],
        ]))->withQueryString();
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @return int
     */
    public function count()
    {
        return $this->{$this->model_name}->withoutTrashed()->count_rows();
    }

    public function countAll()
    {
        return $this->{$this->model_name}->as_object()->withTrashed()->count_rows();
    }

    public function countTrash()
    {
        return $this->{$this->model_name}->as_object()->onlyTrashed()->count_rows();
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Take from database items we need.
     * @param int $number
     * @return $this
     */
    public function take(int $number): Model
    {
        $this->limit($number);

        return $this;
    }

    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->database->trans_start();
    }

    /**
     * End a new database transaction.
     *
     * @return void
     */
    public function endTransaction()
    {
        $this->database->trans_complete();
    }

    /**
     * Commit a database transaction.
     *
     * @return void
     */
    public function commit()
    {
        $this->database->trans_commit();
    }

    /**
     * Rollback a database transaction.
     *
     * @return void
     */
    public function rollBack()
    {
        $this->database->trans_rollback();
    }

    /**
     * Status of database transaction.
     *
     * @return bool
     */
    public function transStatus(): bool
    {
        return $this->database->trans_status();
    }

    /**
     * Random Order database items.
     *
     * @param string $column
     * @return $this
     */
    public function inRandomOrder(string $column = 'id'): Model
    {
        return $this->orderBy($column, 'random');
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param string $column
     * @return mixed
     */
    public function min(string $column)
    {
        $this->whereTrashed();
        $this->database->select_min($column, 'aggregate');

        $row = $this->database->get($this->table)->row();

        return isset($row) ? $row->aggregate : null;
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param string $column
     * @return mixed
     */
    public function max(string $column)
    {
        $this->whereTrashed();
        $this->database->select_max($column, 'aggregate');

        $row = $this->database->get($this->table)->row();

        return isset($row) ? $row->aggregate : null;
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param string $column
     * @return float
     */
    public function sum(string $column): float
    {
        $this->whereTrashed();
        $this->database->select_sum($column, 'aggregate');

        $row = $this->database->get($this->table)->row();

        return isset($row) ? (float) $row->aggregate : 0.0;
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param string $column
     * @return mixed
     */
    public function avg(string $column)
    {
        $this->whereTrashed();
        $this->database->select_avg($column, 'aggregate');

        $row = $this->database->get($this->table)->row();

        return isset($row) ? $row->aggregate : null;
    }

    /**
     * Alias for the "avg" method.
     *
     * @param string $column
     * @return mixed
     */
    public function average(string $column)
    {
        return $this->avg($column);
    }

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)) . '_' . $this->getKeyName();
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table ?? Str::of(get_class($this))->afterLast('\\')->pluralStudly()->snake();
    }

    /**
     * Set the table associated with the model.
     *
     * @param string $table
     * @return void
     */
    public function setTable(string $table)
    {
        $this->table = $table;
    }

    /**
     * Handle dynamic method calls on the model.
     *
     * Supports:
     *  - where_{column}(...)  → where clause shortcut
     *  - with_{relation}(...) → eager-load shortcut
     *  - Named scopes via scope{Name}() convention
     *  - Passthrough to the underlying database driver
     *
     * @param string $method
     * @param array $parameters
     * @return static
     */
    public function __call($method, $parameters)
    {
        if (substr($method, 0, 6) == 'where_') {
            $column = substr($method, 6);
            $this->where($column, $parameters);
            return $this;
        }

        if (($method != 'withTrashed') && (substr($method, 0, 5) == 'with_')) {
            $relation = substr($method, 5);
            $this->with($relation, $parameters);
            return $this;
        }

        if ($this->hasNamedScope($method)) {
            return $this->callNamedScope($method, $parameters);
        }

        if (method_exists($this->database, $method)) {
            call_user_func_array(array($this->database, $method), $parameters);
            return $this;
        }

        $parent_class = get_parent_class($this);

        if ($parent_class !== false && !method_exists($parent_class, $method) && !method_exists($this, $method)) {
            throw new BadMethodCallException(sprintf(
                'Call to undefined method %s() at model %s::class', $method, static::class
            ));
        }
    }
}

if (! class_exists('MY_Model', false)) {
    class_alias(Model::class, 'MY_Model');
}

if (! class_exists('App\\Core\\MY_Model', false)) {
    class_alias(Model::class, 'App\\Core\\MY_Model');
}
