<?php

namespace Elegant\Database\Model;

use Elegant\Database\Model\Concerns\GuardsAttributes;
use Elegant\Database\Model\Concerns\HasAttributes;
use Elegant\Database\Model\Concerns\HasTimestamps;
use Elegant\Database\Model\Concerns\HidesAttributes;
use Elegant\Support\Arr;
use Elegant\Support\Collection;
use Elegant\Support\Str;

abstract class Model extends \CI_Model
{

    use HasAttributes,
        HasTimestamps,
        HidesAttributes,
        GuardsAttributes;

    /**
     * Select the database connection from the group names defined inside the database.php configuration file or an array.
     *
     * @var string
     */
    protected string $connection;

    /**
     * This one will hold the database connection object
     *
     * @var \CI_DB_mysqli_driver
     */
    protected \CI_DB_mysqli_driver $database;


    public static string $limit = '10';

    /** @var string
     * Sets table name
     */
    protected string $table;

    /**
     * Sets PRIMARY KEY
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * @var null|array
     * Sets protected fields.
     * If value is set as null, the $protected will be set as an array with the primary key as single element.
     * If value is set as an array, there won't be any changes done to it (if set as empty array, the primary key won't be inserted here).
     */
    protected $protected = null;

    private $model_name;

    private $model_limit;

    private $count_rows;

    /** relationships variables */
    private $_relationships = array();
    public $has_one = array();
    public $has_many = array();
    public $has_many_pivot = array();
    public $morph_one = array();
    public $morph_many = array();
    public $separate_subqueries = true;
    private $_requested = array();
    /** end relationships variables */


    /*pagination*/
    public $start_page;
    public $end_page;
    public $next_page;
    public $previous_page;
    public $current_page;
    public $path;
    public $previous_page_url;
    public $next_page_url;
    public $all_pages;
    public $pagination_delimiters;
    public $pagination_arrows;
    public $pagination_all_delimiters;
    public $pagination_all_arrows;
    public int $on_each_side = 2;


    /**
     * The various callbacks available to the model. Each are
     * simple lists of method names (methods will be run on $this).
     */
    protected $before_create = array();
    protected $after_create = array();
    protected $before_update = array();
    protected $after_update = array();
    protected $before_get = array();
    protected $after_get = array();
    protected $before_get_all = [];
    protected $before_delete = array();
    protected $after_delete = array();
    protected $before_soft_delete = array();
    protected $after_soft_delete = array();

    protected $callback_parameters = array();

    protected string $return_as = 'object';
    private $_trashed = 'with';

    private $_select = '*';

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

    public function __construct()
    {
        parent::__construct();

        $this->bootIfNotBooted();

        $this->setConnection();

        $this->setTimestamps();

        $this->setTable($this->getTable());

        $this->pagination_delimiters = (isset($this->pagination_delimiters)) ? $this->pagination_delimiters : ['<li class="page-item">', '</li>'];
        $this->pagination_arrows = (isset($this->pagination_arrows)) ? $this->pagination_arrows : ['<i class="simple-icon-arrow-left"></i>', '<i class="simple-icon-arrow-right"></i>'];
        $this->pagination_all_delimiters = (isset($this->pagination_all_delimiters)) ? $this->pagination_all_delimiters : ['<li class="page-item">', '</li>'];
        $this->pagination_all_arrows = (isset($this->pagination_all_arrows)) ? $this->pagination_all_arrows : ['<i class="simple-icon-control-start"></i>', '<i class="simple-icon-control-end"></i>'];

        $this->model_name = strtolower(get_class($this));

        $this->table = !isset($this->table) ? strtolower(plural(get_class($this))) : $this->table;

        $this->model_limit = ci()->session->has_userdata(strtolower($this->model_name) . '_limit')
            ? ci()->session->userdata(strtolower($this->model_name) . '_limit')
            : $this->model_name::$limit;

        $this->initializeTraits();

        $this->fill();
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        if (! isset(static::$booted[static::class])) {
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
            $method = 'boot'.class_basename($trait);

            if (method_exists($class, $method) && ! in_array($method, $booted)) {
                forward_static_call([$class, $method]);

                $booted[] = $method;
            }

            if (method_exists($class, $method = 'initialize'.class_basename($trait))) {
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
     * @param  bool  $shouldBeStrict
     * @return void
     */
    public static function shouldBeStrict(bool $shouldBeStrict = true)
    {
        static::preventAccessingMissingAttributes($shouldBeStrict);
    }

    /**
     * Prevent accessing missing attributes on retrieved models.
     *
     * @param  bool  $value
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
     * @throws \App\Exceptions\MassAssignmentException;
     */
    public function fill(): MY_Model
    {
//        if (is_null($this->fillable)) {
//            $table_fields = $this->database->list_fields($this->table);
//            foreach ($table_fields as $field) {
//                if (is_array($this->protected) && !in_array($field, $this->protected)) {
//                    $this->fillable[] = $field;
//                } elseif (is_null($this->protected) && ($field !== $this->primaryKey)) {
//                    $this->fillable[] = $field;
//                }
//            }
//        }
//        if (is_null($this->protected)) {
//            $this->protected = array($this->primaryKey);
//        }
//
//        return $this;



        $totallyGuarded = $this->totallyGuarded();

        $dbTableFields = $this->database->list_fields($this->getTable());

        foreach ($this->fillableFromArray($dbTableFields) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->fillable[$key] = $value;
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException(sprintf(
                    'Add [%s] to fillable property to allow mass assignment on [%s] model.',
                    $key, get_class($this)
                ));
            }
        }

        if (empty($this->hidden)) {
            $this->hidden = [$this->primaryKey];
        }

        return $this;
    }

    /**
     * Begin querying the model on a given connection.
     *
     * @param  string  $name
     * @return MY_Model
     */
    public function on(string $name = 'default'): MY_Model
    {
        $this->database->close();

        $this->load->database($name);

        $this->database = $this->db;

        $this->connection = $this->db->dbdriver;

        return $this;
    }

    /**
     * @param $data
     * @return array
     */
    public function _prep_before_write($data): array
    {
        // Let's make sure we receive an array...
        $data_as_array = (is_object($data)) ? (array)$data : $data;

        $new_data = array();
        $multi = $this->is_multidimensional($data);
        if ($multi === false) {
            foreach ($data_as_array as $field => $value) {
                if (in_array($field, $this->fillable)) {
                    $new_data[$field] = $value;
                } else {
                    if(static::$modelsShouldPreventAccessingMissingAttributes) {
                        throw new MassAssignmentException(sprintf(
                            'Add fillable property [%s] to allow mass assignment on [%s] model.',
                            $field, get_class($this)
                        ));
                    }
                }
            }
        } else {
            foreach ($data_as_array as $key => $row) {
                foreach ($row as $field => $value) {
                    if (in_array($field, $this->fillable)) {
                        $new_data[$key][$field] = $value;
                    } else {
                        throw new MassAssignmentException(sprintf(
                            'Add fillable property [%s] to allow mass assignment on [%s] model.',
                            $field, get_class($this)
                        ));
                    }
                }
            }
        }
        return $new_data;
    }

    public function _prep_after_read($data, $multi = true)
    {
        // let's join the subqueries...
        $data = $this->join_temporary_results($data);

        $this->database->reset_query();
        $this->_requested = array();

        if ($this->return_as == 'object') {
            $data = $this->array_to_object($data);
        }

        if (isset($this->_select)) {
            $this->_select = '*';
        }
        return $data;
    }

    /**
     * public function insert($data)
     * Inserts data into table. Can receive an array or a multidimensional array depending on what kind of insert we're talking about.
     * @param $data
     * @return int|array Returns id/ids of inserted rows
     */
    public function insert($data = null)
    {
        if(!isset($data)) {
            return false;
        }
        $data = $this->_prep_before_write($data);

        //now let's see if the array is a multidimensional one (multiple rows insert)
        $multi = $this->is_multidimensional($data);

        // if the array is not a multidimensional one...
        if ($multi === false) {
            if ($this->usesTimestamps()) {
                $data[$this->{$this->getCreatedAtColumn() . '_column'}] = $this->freshTimestampString();
                $data[$this->{$this->getUpdatedAtColumn() . '_column'}] = $this->freshTimestampString();
            }

            $data = $this->trigger('before_create', $data);
            if ($this->database->insert($this->table, $data)) {
                $id = $this->database->insert_id();
                $return = $this->trigger('after_create', $id);
//                return $return;

                return $this->find($return);
            }

            return false;
        } // else...
        else {
            $return = array();
            foreach ($data as $row) {
                if ($this->usesTimestamps()) {
                    $row[$this->{$this->getCreatedAtColumn() . '_column'}] = $this->freshTimestampString();
                    $row[$this->{$this->getUpdatedAtColumn() . '_column'}] = $this->freshTimestampString();
                }
                $row = $this->trigger('before_create', $row);
                if ($this->database->insert($this->table, $row)) {
                    $return[] = $this->database->insert_id();
                }
            }

            $after_create = array();
            foreach ($return as $id) {
                $after_create[] = $this->trigger('after_create', $id);
            }
            return $after_create;

//            return $this->find($returned);
        }

        return false;
    }

    /**
     * @param array $attributes
     * @return array|false|int|mixed
     */
    public function create(array $attributes = [])
    {
        if (is_array($attributes[0])) {
            $ids = [];

            foreach ($attributes as $attribute) {
                $returned = $this->insert($attribute);
                $ids[] = $returned->id;
            }

            return $ids;
        } else {
            $returned = $this->insert($attributes);

            return $returned->id;
        }
    }

    /**
     * Insert new records or update the existing ones.
     *
     * @param array $values
     * @param array|string $uniqueBy
     * @param array|null $update
     * @return int|void
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if (empty($values)) {
            return 0;
        } elseif ($update === []) {
            return (int)$this->insert($values);
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

    protected function compileUpsert(array $values, array $uniqueBy, array $update)
    {
        $useUpsertAlias = false; //config('database.default.use_upsert_alias');

        $sql = $this->compileInsert($values);

        if ($useUpsertAlias) {
            $sql .= ' as laraigniter_upsert_alias';
        }

        $sql .= ' on duplicate key update ';

        $columns = collect($update)->map(function ($value, $key) use ($useUpsertAlias) {
            if (!is_numeric($key)) {
                return $this->wrap($key) . ' = ' . $this->parameter($value);
            }

            return $useUpsertAlias
                ? $this->wrap($value) . ' = ' . $this->wrap('laraigniter_upsert_alias') . '.' . $this->wrap($value)
                : $this->wrap($value) . ' = values(' . $this->wrap($value) . ')';
        })->implode(', ');

        return $sql . $columns;
    }

    protected function compileInsert(array $values)
    {
        if (empty($values)) {
            $values = [[]];
        }

        $table = $this->database->dbprefix($this->table);

        if (empty($values)) {
            return "insert into `{$table}` default values";
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));

        $parameters = collect($values)->map(function ($record) {
            return '(' . $this->parameterize($record) . ')';
        })->implode(', ');

        return "insert into `$table`($columns) values $parameters";
    }

    /**
     * Convert an array of column names into a delimited string.
     *
     * @param array $columns
     * @return string
     */
    protected function columnize(array $columns)
    {
        return collect($columns)->map(function ($column) {
            return '`' . $column . '`';
        })->implode(', ');
    }

    /**
     * Create query parameter place-holders for an array.
     *
     * @param array $values
     * @return string
     */
    protected function parameterize(array $values)
    {
        return collect($values)->map(function ($value) {
            if (is_null($value)) {
                return 'NULL';
            }

            if ($value == '') {
                return '\'' . '\'';
            }

            return '\'' . $value . '\'';
        })->implode(', ');
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     * @param string $value
     * @return string
     */
    protected function wrap($value)
    {
        return $this->wrapSegments(explode('.', $value));
    }

    /**
     * Wrap the given value segments.
     *
     * @param array $segments
     * @return string
     */
    protected function wrapSegments($segments)
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            return $key == 0 && count($segments) > 1
                ? $this->database->dbprefix($this->table)
                : $this->wrapValue($segment);
        })->implode('.');
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value !== '*') {
            return '`' . str_replace('"', '""', $value) . '`';
        }

        return $value;
    }

    /**
     * Encode the given value as JSON.
     *
     * @param mixed $value
     * @return string
     */
    protected function asJson($value): string
    {
        return json_encode($value);
    }

    /**
     * Decode the given JSON back into an array or object.
     *
     * @param string $value
     * @param bool $asObject
     * @return mixed
     */
    public function fromJson(string $value, bool $asObject = false)
    {
        return json_decode($value, !$asObject);
    }

    /*
     * public function is_multidimensional($array)
     * Verifies if an array is multidimensional or not;
     * @param array $array
     * @return bool return TRUE if the array is a multidimensional one
     */
    public function is_multidimensional($array)
    {
        if (is_array($array)) {
            foreach ($array as $element) {
                if (is_array($element)) {
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * public function update($data)
     * Updates data into table. Can receive an array or a multidimensional array depending on what kind of update we're talking about.
     * @param array $data
     * @param array|int $column_name_where
     * @param bool $escape should the values be escaped or not - defaults to true
     * @return string|array Returns id/ids of inserted rows
     */
    public function update($data = null, $column_name_where = null, $escape = true)
    {
        if (!isset($data)) {
            $this->database->reset_query();
            return false;
        }
        // Prepare the data...
        $data = $this->_prep_before_write($data);

        //now let's see if the array is a multidimensional one (multiple rows insert)
        $multi = $this->is_multidimensional($data);

        // if the array is not a multidimensional one...
        if ($multi === false) {
            if ($this->usesTimestamps()) {
                $data[$this->{$this->getUpdatedAtColumn() . '_column'}] = $this->freshTimestampString();
            }

            $data = $this->trigger('before_update', $data);

            if (isset($column_name_where)) {
                if (is_array($column_name_where)) {
                    $this->where($column_name_where);
                } elseif (is_numeric($column_name_where)) {
                    $this->database->where($this->primaryKey, $column_name_where);
                } else {
                    $column_value = (is_object($data)) ? $data->{$column_name_where} : $data[$column_name_where];
                    $this->database->where($column_name_where, $column_value);
                }
            }
            if ($escape) {
                if ($this->database->update($this->table, $data)) {
                    $affected = $this->database->affected_rows();
                    $return = $this->trigger('after_update', $affected);
//                    return $return;

                    return $this->find($column_name_where);
                }
            } else {
                if ($this->database->set($data, null, false)->update($this->table)) {
                    $affected = $this->database->affected_rows();
                    $return = $this->trigger('after_update', $affected);
//                    return $return;

                    return $this->find($column_name_where);
                }
            }

            return false;
        } // else...
        else {
            $rows = 0;
            foreach ($data as $row) {
                if ($this->usesTimestamps()) {
                    $row[$this->{$this->getUpdatedAtColumn() . '_column'}] = $this->freshTimestampString();
                }
                $row = $this->trigger('before_update', $row);
                if (is_array($column_name_where)) {
                    $this->database->where($column_name_where[0], $column_name_where[1]);
                } else {
                    $column_value = (is_object($row)) ? $row->{$column_name_where} : $row[$column_name_where];
                    $this->database->where($column_name_where, $column_value);
                }
                if ($escape) {
                    if ($this->database->update($this->table, $row)) {
                        $rows++;
                    }
                } else {
                    if ($this->database->set($row, null, false)->update($this->table)) {
                        $rows++;
                    }
                }
            }
            $affected = $rows;
            $return = $this->trigger('after_update', $affected);
//            return $return;

            return $this->find($column_name_where);
        }
        return false;
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

            $this->update($values, $instance->id);

            return $this->find($instance->id);
        } else {
            $instance = $this->insert($values);
        }

        return $this->find($instance);
    }

    /**
     * public function where($field_or_array = NULL, $operator_or_value = NULL, $value = NULL, $with_or = FALSE, $with_not = FALSE, $custom_string = FALSE)
     * Sets a where method for the $this object
     * @param null $field_or_array - can receive a field name or an array with more wheres...
     * @param null $operator_or_value - can receive a database operator or, if it has a field, the value to equal with
     * @param null $value - a value if it received a field name and an operator
     * @param bool $with_or - if set to true will create a or_where query type pr a or_like query type, depending on the operator
     * @param bool $with_not - if set to true will also add "NOT" in the where
     * @param bool $custom_string - if set to true, will simply assume that $field_or_array is actually a string and pass it to the where query
     * @return $this
     */
    public function where($field_or_array = null, $operator_or_value = null, $value = null, $with_or = false, $with_not = false, $custom_string = false)
    {
        if (is_array($field_or_array)) {
            $multi = $this->is_multidimensional($field_or_array);
            if ($multi === true) {
                foreach ($field_or_array as $where) {
                    $field = $where[0];
                    $operator_or_value = isset($where[1]) ? $where[1] : null;
                    $value = isset($where[2]) ? $where[2] : null;
                    $with_or = (isset($where[3])) ? true : false;
                    $with_not = (isset($where[4])) ? true : false;
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
            //echo $field_or_array;
            //exit;
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

                $this->database->{$like}($field_or_array, $value);
            } else {
                $this->database->{$where_or}($field_or_array . ' ' . $operator_or_value, $value);
            }
        }
        return $this;
    }

    /**
     * public function limit($limit, $offset = 0)
     * Sets a rows limit to the query
     * @param $limit
     * @param int $offset
     * @return $this
     */
    public function limit($limit, $offset = 0)
    {
        $this->database->limit($limit, $offset);
        return $this;
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param $group
     * @return $this
     */
    public function groupBy($group): MY_Model
    {
        $this->database->group_by($group);

        return $this;
    }

    /**
     * public function delete($where)
     * Deletes data from table.
     * @param $where primary_key(s) Can receive the primary key value or a list of primary keys as array()
     * @return int|array Returns affected rows or false on failure
     */
    public function delete($where = null)
    {
        if (!empty($this->before_delete) || !empty($this->before_soft_delete) || !empty($this->after_delete) || !empty($this->after_soft_delete) || ((isset($this->soft_deletes) && $this->soft_deletes === true))) {
            $to_update = array();
            if (isset($where)) {
                $this->where($where);
            }
            $query = $this->database->get($this->table);
            foreach ($query->result() as $row) {
                $to_update[] = array($this->primaryKey => $row->{$this->primaryKey});
            }
            if (!empty($this->before_soft_delete)) {
                foreach ($to_update as &$row) {
                    $row = $this->trigger('before_soft_delete', $row);
                }
            }
            if (!empty($this->before_delete)) {
                foreach ($to_update as &$row) {
                    $row = $this->trigger('before_delete', $row);
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
                    //$row = $this->trigger('before_soft_delete',$row);
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

                $this->trigger('after_soft_delete', $to_update);
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
                if (!empty($this->after_delete)) {
                    $to_update['affected_rows'] = $affected_rows;
                    $to_update = $this->trigger('after_delete', $to_update);
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
     * public function trashed($where = NULL)
     * Verifies if a record (row) is soft_deleted or not
     * @param null $where
     * @return bool
     */
    public function trashed($where = null)
    {
        $this->only_trashed();
        if (isset($where)) {
            $this->where($where);
        }
        $this->limit(1);
        $query = $this->database->get($this->table);
        if ($query->num_rows() == 1) {
            return true;
        }
        return false;
    }

    public function _get_joined($requested)
    {
        $this->database->join($this->_relationships[$requested['request']]['foreign_table'], $this->table . '.' . $this->_relationships[$requested['request']]['local_key'] . ' = ' . $this->_relationships[$requested['request']]['foreign_table'] . '.' . $this->_relationships[$requested['request']]['foreign_key']);
        $the_select = '';
        if (!empty($requested['parameters'])) {
            if (array_key_exists('fields', $requested['parameters'])) {
                $fields = explode(',', $requested['parameters']['fields']);
                $sub_select = array();
                foreach ($fields as $field) {
                    $sub_select[] = ((strpos($field, '.') === false) ? '`' . $this->_relationships[$requested['request']]['foreign_table'] . '`.`' . trim($field) . '`' : trim($field)) . ' AS ' . $requested['request'] . '_' . trim($field);
                }
                $the_select = implode(',', $sub_select);
            } else {
                $the_select = $this->_relationships[$requested['request']]['foreign_table'] . '.*';
            }
        }
        $this->database->select($the_select);
        unset($this->_requested[$requested['request']]);
    }


    /**
     * public function get()
     * Retrieves one row from table.
     * @param null $where
     * @return mixed
     */
    public function get($where = null)
    {
        $this->trigger('before_get');

        if ($this->_select) {
            $this->database->select($this->_select);
        }

        if (!empty($this->_requested)) {
            foreach ($this->_requested as $requested) {
                if (isset($requested['parameters']['join'])) {
                    $this->_get_joined($requested);
                } else {
                    $this->database->select($this->table . '.' . $this->_relationships[$requested['request']]['local_key']);
                }
            }
        }
        if (isset($where)) {
            $this->where($where);
        }
        if (isset($this->soft_deletes) && $this->soft_deletes === true) {
            $this->_where_trashed();
        }
        $this->limit(1);
        $query = $this->database->get($this->table);
        $this->_reset_trashed();
        if ($query->num_rows() == 1) {
            $row = $query->row_array();
            $row = $this->trigger('after_get', $row);
            $row = $this->_prep_after_read(array($row), false);
            return is_array($row) ? $row[0] : $row->{0};
        } else {
            return false;
        }
    }

    public function find($id)
    {
        $data = collect();

        $data->push($this->get([$this->primaryKey => $id]));

        return $data->first();
    }

    public function first()
    {
        $data = collect();

        $data->push($this->{$this->model_name}->get());

        return $data->first();
    }

    public function firstOrFail()
    {
        $data = collect();

        $item = $this->{$this->model_name}->get();

        if (!$item) {
            if (request()->acceptsJson()) {
                \App\libraries\Response::make([], \App\libraries\Response::HTTP_NOT_FOUND);
            }

            trigger_404();
        }

        $data->push($item);

        return $data->first();
    }

    public function findOrFail($id)
    {
        $data = collect();

        $item = $this->{$this->model_name}->get(array($this->primaryKey => $id));

        if (!$item) {
            if (request()->acceptsJson()) {
                \App\libraries\Response::make([], \App\libraries\Response::HTTP_NOT_FOUND);
            }

            trigger_404();
        }

        $data->push($item);

        return $data->first();
    }

    /**
     * public function get_all()
     * Retrieves rows from table.
     * @param null $where
     * @return mixed
     */
    public function get_all($where = null)
    {
//            $this->trigger('before_get');
        $this->trigger('before_get_all');
        if (isset($where)) {
            $this->where($where);
        }
        if (isset($this->soft_deletes) && $this->soft_deletes === true) {
            $this->_where_trashed();
        }
        if (isset($this->_select)) {
            $this->database->select($this->_select);
        }
        if (!empty($this->_requested)) {
            foreach ($this->_requested as $requested) {
                if (isset($requested['parameters']['join'])) {
                    $this->_get_joined($requested);
                } else {
                    $this->database->select($this->table . '.' . $this->_relationships[$requested['request']]['local_key']);
                }
            }
        }
        $query = $this->database->get($this->table);
        $this->_reset_trashed();
        if ($query->num_rows() > 0) {
            $data = $query->result_array();

            /**
             * Issues for observer oer row in get_all
             *
             * Trigger after_get for each row #238
             * Change after_get observer to be per row in get_all #146
             */
            // $data = $this->trigger('after_get', $data);
            foreach ($data as $key => $row) {
                $data[$key] = $this->trigger('after_get', $row);
            }

            return $this->_prep_after_read($data);
        } else {
            return false;
        }
    }

    /**
     * @param string $trash
     * @return Collection
     */
    public function all(string $trash = 'without'): Collection
    {
        if ($trash == 'with') {
            return collect($this->{$this->model_name}->with_trashed()->get_all());
        } elseif ($trash == 'only') {
            return collect($this->{$this->model_name}->only_trashed()->get_all());
        } else {
            return collect($this->{$this->model_name}->without_trashed()->get_all());
        }
    }

    /**
     * public function count_rows()
     * Retrieves number of rows from table.
     * @param null $where
     * @return integer
     */
    public function count_rows($where = null)
    {
        $this->trigger('before_get_all');

        if (isset($where)) {
            $this->where($where);
        }
        if (isset($this->soft_deletes) && $this->soft_deletes === true) {
            $this->_where_trashed();
        }
        $this->database->from($this->table);
        $number_rows = $this->database->count_all_results();
        $this->_reset_trashed();
        return $number_rows;
    }

    public function whereNull($key): MY_Model
    {
        $this->where($key . ' IS NULL', NULL, FALSE, FALSE, FALSE, TRUE);

        return $this;
    }

    public function whereNotNull($key): MY_Model
    {
        $this->where($key . ' IS NOT NULL', NULL, FALSE, FALSE, FALSE, TRUE);

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
    public function orderBy(string $column, string $direction = 'DESC'): MY_Model
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Order direction must be "ASC" or "DESC".');
        }

        $this->{$this->model_name}->order_by($column, $direction);

        return $this;
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param string $column
     * @return $this
     */
    public function latest(string $column = 'created_at'): MY_Model
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param string $column
     * @return $this
     */
    public function oldest(string $column = 'created_at'): MY_Model
    {
        return $this->orderBy($column, 'asc');
    }

    /** RELATIONSHIPS */

    /**
     * public function with($requests)
     * allows the user to retrieve records from other interconnected tables depending on the relations defined before the constructor
     * @param string $request
     * @param array $arguments
     * @return $this
     */
    public function with($request, array $arguments = []): MY_Model
    {
        if (is_array($request)) {
            foreach ($request as $item) {
                $this->with($item);
            }

            return $this;
        }

        $this->_set_relationships();
        if (array_key_exists($request, $this->_relationships)) {
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


        /*
        if($separate_subqueries === FALSE)
        {
            $this->separate_subqueries = FALSE;
            foreach($this->_requested as $request)
            {
                if($this->_relationships[$request]['relation'] == 'has_one') $this->_has_one($request);
            }
        }
        else
        {
            $this->after_get[] = 'join_temporary_results';
        }
        */
        return $this;
    }

    /**
     * protected function join_temporary_results($data)
     * Joins the subquery results to the main $data
     * @param $data
     * @return mixed
     */
    protected function join_temporary_results($data)
    {
        foreach ($this->_requested as $requested_key => $request) {
            $order_by = array();
            $order_inside_array = array();
            $pivot_table = null;
            $relation = $this->_relationships[$request['request']];
            $this->load->model($relation['foreign_model'], $relation['foreign_model_name']);
            $foreign_key = $relation['foreign_key'];
            $local_key = $relation['local_key'];
            $foreign_table = $relation['foreign_table'];
            $type = $relation['relation'];
            $relation_key = $relation['relation_key'];
            if ($type == 'has_many_pivot') {
                $pivot_table = $relation['pivot_table'];
                $pivot_local_key = $relation['pivot_local_key'];
                $pivot_foreign_key = $relation['pivot_foreign_key'];
                $get_relate = $relation['get_relate'];
            }

            if (array_key_exists('order_inside', $request['parameters'])) {
                //$order_inside = $request['parameters']['order_inside'];
                $elements = explode(',', $request['parameters']['order_inside']);
                foreach ($elements as $element) {
                    $order = explode(' ', $element);
                    if (sizeof($order) == 2) {
                        $order_inside_array[] = array(trim($order[0]), trim($order[1]));
                    } else {
                        $order_inside_array[] = array(trim($order[0]), 'desc');
                    }
                }
            }


            $local_key_values = array();
            foreach ($data as $key => $element) {
                if (isset($element[$local_key]) and !empty($element[$local_key])) {
                    $id = $element[$local_key];
                    $local_key_values[$key] = $id;
                }
            }
            if (!$local_key_values) {
                $data[$key][$relation_key] = null;
                continue;
            }
            if (!isset($pivot_table)) {
                $sub_results = $this->{$relation['foreign_model_name']};
                $select = array();
                $select[] = '`' . $foreign_table . '`.`' . $foreign_key . '`';
                if (!empty($request['parameters'])) {
                    if (array_key_exists('fields', $request['parameters'])) {
                        if ($request['parameters']['fields'] == '*count*') {
                            $the_select = '*count*';
                            $sub_results = (isset($the_select)) ? $sub_results->fields($the_select) : $sub_results;
                            $sub_results = $sub_results->fields($foreign_key);
                        } else {
                            $fields = explode(',', $request['parameters']['fields']);
                            foreach ($fields as $field) {
                                $select[] = (strpos($field, '.') === false) ? '`' . $foreign_table . '`.`' . trim($field) . '`' : trim($field);
                            }
                            $the_select = implode(',', $select);
                            $sub_results = (isset($the_select)) ? $sub_results->fields($the_select) : $sub_results;
                        }
                    }
                    if (array_key_exists('fields', $request['parameters']) && ($request['parameters']['fields'] == '*count*')) {
                        $sub_results->group_by('`' . $foreign_table . '`.`' . $foreign_key . '`');
                    }
                    if (array_key_exists('where', $request['parameters']) || array_key_exists('non_exclusive_where', $request['parameters'])) {
                        $the_where = array_key_exists('where', $request['parameters']) ? 'where' : 'non_exclusive_where';
                    }
                    $sub_results = isset($the_where) ? $sub_results->where($request['parameters'][$the_where], null, null, false, false, true) : $sub_results;

                    if (isset($order_inside_array)) {
                        foreach ($order_inside_array as $order_by_inside) {
                            $sub_results = $sub_results->order_by($order_by_inside[0], $order_by_inside[1]);
                        }
                    }

                    //Add nested relation
                    if (array_key_exists('with', $request['parameters'])) {
                        // Do we have many nested relation
                        if (is_array($request['parameters']['with']) && isset($request['parameters']['with'][0]) && is_array($request['parameters']['with'][0])) {
                            foreach ($request['parameters']['with'] as $with) {
                                $with_relation = array_shift($with);
                                $sub_results->with($with_relation, array($with));
                            }
                        } else { // single nested relation
                            $with_relation = array_shift($request['parameters']['with']);
                            $sub_results->with($with_relation, array($request['parameters']['with']));
                        }
                    }
                }

                if ($type == 'morph_one' || $type == 'morph_many') {
                    [$model_type, $model_id] = $this->getMorphs($relation['foreign_column'], null, null);
                    $sub_results = $sub_results->where($model_id, $local_key_values)->where($model_type, 'models/' . strtolower(get_class($this)))->get_all();
                } else {
                    $sub_results = $sub_results->where($foreign_key, $local_key_values)->get_all();
                }
            } else {
                $this->database->join($pivot_table, $foreign_table . '.' . $foreign_key . ' = ' . $pivot_table . '.' . $pivot_foreign_key, 'left');
                $this->database->join($this->table, $pivot_table . '.' . $pivot_local_key . ' = ' . $this->table . '.' . $local_key, 'left');
                $this->database->select($foreign_table . '.' . $foreign_key);
                $this->database->select($pivot_table . '.' . $pivot_local_key);
                if (!empty($request['parameters'])) {
                    if (array_key_exists('fields', $request['parameters'])) {
                        if ($request['parameters']['fields'] == '*count*') {
                            $this->database->select('COUNT(`' . $foreign_table . '`.`' . $foreign_key . '`) as counted_rows, `' . $foreign_table . '`.`' . $foreign_key . '`', false);
                        } else {
                            $fields = explode(',', $request['parameters']['fields']);
                            $select = array();
                            foreach ($fields as $field) {
                                $select[] = (strpos($field, '.') === false) ? '`' . $foreign_table . '`.`' . trim($field) . '`' : trim($field);
                            }
                            $the_select = implode(',', $select);
                            $this->database->select($the_select);
                        }
                    }

                    if (array_key_exists('where', $request['parameters']) || array_key_exists('non_exclusive_where', $request['parameters'])) {
                        $the_where = array_key_exists('where', $request['parameters']) ? 'where' : 'non_exclusive_where';

                        $this->database->where($request['parameters'][$the_where], null, null, false, false, true);
                    }
                }
                $this->database->where_in($pivot_table . '.' . $pivot_local_key, $local_key_values);

                if (!empty($order_inside_array)) {
                    $order_inside_str = '';
                    foreach ($order_inside_array as $order_by_inside) {
                        $order_inside_str .= (strpos($order_by_inside[0], '.') === false) ? '`' . $foreign_table . '`.`' . $order_by_inside[0] . ' ' . $order_by_inside[1] : $order_by_inside[0] . ' ' . $order_by_inside[1];
                        $order_inside_str .= ',';
                    }
                    $order_inside_str = rtrim($order_inside_str, ",");
                    $this->database->order_by($order_inside_str);
                }
                $sub_results = $this->database->get($foreign_table)->result_array();
                $this->database->reset_query();
            }

            if (isset($sub_results) && !empty($sub_results)) {
                $subs = array();

                foreach ($sub_results as $result) {
                    $result_array = (array)$result;
                    $the_foreign_key = $result_array[$foreign_key];
                    if (isset($pivot_table)) {
                        $the_local_key = $result_array[$pivot_local_key];
                        if (isset($get_relate) and $get_relate === true) {
                            $subs[$the_local_key][$the_foreign_key] = $this->{$relation['foreign_model']}->where($foreign_key, $result[$foreign_key])->get();
                        } else {
                            $subs[$the_local_key][$the_foreign_key] = $result;
                        }
                    } else {
                        if ($type == 'has_one' || $type == 'morph_one') {
                            $subs[$the_foreign_key] = $result;
                        } else {
                            $subs[$the_foreign_key][] = $result;
                        }
                    }
                }
                $sub_results = $subs;

                foreach ($local_key_values as $key => $value) {
                    if (array_key_exists($value, $sub_results)) {
                        $data[$key][$relation_key] = $sub_results[$value];
                    } else {
                        if (array_key_exists('where', $request['parameters'])) {
                            unset($data[$key]);
                        }
                    }
                }
            } else {
                $data[$key][$relation_key] = null;
            }
            if (array_key_exists('order_by', $request['parameters'])) {
                if (is_array($request['parameters']['order_by'])) {
                    $elements = $request['parameters']['order_by'];

                    $order_by[$relation_key] = [
                        trim(key($elements)),
                        trim($elements[key($elements)])
                    ];
                } else {
                    $elements = explode(',', $request['parameters']['order_by']);

                    if (sizeof($elements) == 2) {
                        $order_by[$relation_key] = array(trim($elements[0]), trim($elements[1]));
                    } else {
                        $order_by[$relation_key] = array(trim($elements[0]), 'desc');
                    }
                }
            }
            unset($this->_requested[$requested_key]);
        }
        if (!empty($order_by)) {
            foreach ($order_by as $field => $row) {
                list($key, $value) = $row;
                $data = $this->_build_sorter($data, $field, $key, $value);
            }
        }
        return $data;
    }


    /**
     * private function _has_one($request)
     *
     * returns a joining of two tables depending on the $request relationship established in the constructor
     * @param $request
     * @return $this
     */
    private function _has_one($request)
    {
        $relation = $this->_relationships[$request];
        $this->database->join($relation['foreign_table'], $relation['foreign_table'] . '.' . $relation['foreign_key'] . ' = ' . $this->table . '.' . $relation['local_key'], 'left');
        return true;
    }

    /**
     * private function _set_relationships()
     *
     * Called by the public method with() it will set the relationships between the current model and other models
     */
    private function _set_relationships()
    {
        if (empty($this->_relationships)) {
            $options = array('has_one', 'has_many', 'has_many_pivot', 'morph_one', 'morph_many');
            foreach ($options as $option) {
                if (isset($this->{$option}) && !empty($this->{$option})) {
                    foreach ($this->{$option} as $key => $relation) {
                        $single_query = false;
                        if (!is_array($relation)) {
                            $foreign_model = $relation;
                            $model = $this->_parse_model_dir($foreign_model);
                            $foreign_model = $model['foreign_model'];
                            //$model_dir = $model['model_dir'];
                            $foreign_model_name = $model['foreign_model_name'];

                            $this->load->model($foreign_model, $foreign_model_name);
                            $foreign_table = $this->{$foreign_model_name}->table;
                            $foreign_key = $this->{$foreign_model_name}->primaryKey;
                            $local_key = $this->primaryKey;
                            $pivot_local_key = $this->table . '_' . $local_key;
                            $pivot_foreign_key = $foreign_table . '_' . $foreign_key;
                            $get_relate = false;
                        } else {
                            if ($this->is_assoc($relation)) {
                                $foreign_model = $relation['foreign_model'];
                                $model = $this->_parse_model_dir($foreign_model);
                                $foreign_model = $model['model_dir'] . $model['foreign_model'];
                                $foreign_model_name = $model['foreign_model_name'];

                                if (array_key_exists('foreign_table', $relation)) {
                                    $foreign_table = $relation['foreign_table'];
                                } else {
                                    $this->load->model($foreign_model, $foreign_model_name);
                                    $foreign_table = $this->{$foreign_model_name}->table;
                                }

                                $foreign_key = $relation['foreign_key'] ?? '';
                                $local_key = $relation['local_key'] ?? 'id';
                                if ($option == 'has_many_pivot') {
                                    $pivot_table = $relation['pivot_table'];
                                    $pivot_local_key = (array_key_exists('pivot_local_key', $relation)) ? $relation['pivot_local_key'] : $this->table . '_' . $this->primaryKey;
                                    $pivot_foreign_key = (array_key_exists('pivot_foreign_key', $relation)) ? $relation['pivot_foreign_key'] : $foreign_table . '_' . $foreign_key;
                                    $get_relate = (array_key_exists('get_relate', $relation) && ($relation['get_relate'] === true)) ? true : false;
                                }
                                if ($option == 'has_one' && isset($relation['join']) && $relation['join'] === true) {
                                    $single_query = true;
                                }
                                if ($option == 'morph_many') {
                                    $foreign_column = $relation['foreign_column'];
                                }
                            } else {
                                $foreign_model = $relation[0];
                                $model = $this->_parse_model_dir($foreign_model);
                                $foreign_model = $model['model_dir'] . $model['foreign_model'];
                                $foreign_model_name = $model['foreign_model_name'];

                                $this->load->model($foreign_model);
                                $foreign_table = $this->{$foreign_model}->table;
                                if ($option == 'morph_one') {
                                    $foreign_column = $relation[1];
                                    [$type, $id] = $this->getMorphs($foreign_column, null, null);
                                    $foreign_key = $id;
                                    $local_key = $relation['local_key'] ?? 'id';
                                } else {
                                    $foreign_key = $relation[1];
                                    $local_key = $relation[2];
                                }

                                if ($option == 'has_many_pivot') {
                                    $pivot_local_key = $this->table . '_' . $this->primaryKey;
                                    $pivot_foreign_key = $foreign_table . '_' . $foreign_key;
                                    $get_relate = (isset($relation[3]) && ($relation[3] === true)) ? true : false;
                                }
                            }
                        }

                        if ($option == 'has_many_pivot' && !isset($pivot_table)) {
                            $tables = array($this->table, $foreign_table);
                            sort($tables);
                            $pivot_table = $tables[0] . '_' . $tables[1];
                        }

                        $this->_relationships[$key] = array(
                            'relation' => $option,
                            'relation_key' => $key,
                            'foreign_model' => strtolower($foreign_model),
                            'foreign_model_name' => strtolower($foreign_model_name),
                            'foreign_column' => $foreign_column ?? '',
                            'foreign_table' => $foreign_table,
                            'foreign_key' => $foreign_key,
                            'local_key' => $local_key
                        );

                        if ($option == 'has_many_pivot') {
                            $this->_relationships[$key]['pivot_table'] = $pivot_table;
                            $this->_relationships[$key]['pivot_local_key'] = $pivot_local_key;
                            $this->_relationships[$key]['pivot_foreign_key'] = $pivot_foreign_key;
                            $this->_relationships[$key]['get_relate'] = $get_relate;
                        }
                        if ($single_query === true) {
                            $this->_relationships[$key]['joined'] = true;
                        }
                    }
                }
            }
        }
    }

    /** END RELATIONSHIPS */

    /**
     * Get the polymorphic relationship columns.
     *
     * @param string $name
     * @param string $type
     * @param string $id
     * @return array
     */
    protected function getMorphs($name, $type, $id)
    {
        return [$type ?: $name . '_type', $id ?: $name . '_id'];
    }

    /**
     * Resets the connection to the default used for all the model
     *
     * @return $this
     */
    public function resetConnection(): MY_Model
    {
        $this->database->close();

        return $this->setConnection();
    }

    /**
     * Trigger an event and call its observers. Pass through the event name
     * (which looks for an instance variable $this->event_name), an array of
     * parameters to pass through and an optional 'last in interation' boolean
     */
    public function trigger($event, $data = array(), $last = true)
    {
        if (isset($this->$event) && is_array($this->$event)) {
            foreach ($this->$event as $method) {
                if (strpos($method, '(')) {
                    preg_match('/([a-zA-Z0-9\_\-]+)(\(([a-zA-Z0-9\_\-\., ]+)\))?/', $method, $matches);
                    $method = $matches[1];
                    $this->callback_parameters = explode(',', $matches[3]);
                }
                $data = call_user_func_array(array($this, $method), array($data, $last));
            }
        }
        return $data;
    }

    /**
     * private function _reset_trashed()
     * Sets $_trashed to default 'without'
     */
    private function _reset_trashed()
    {
        $this->_trashed = 'without';
        return $this;
    }

    /**
     * public function with_trashed()
     * Sets $_trashed to with
     */
    public function with_trashed()
    {
        $this->_trashed = 'with';
        return $this;
    }

    /**
     * public function without_trashed()
     * Sets $_trashed to without
     */
    public function without_trashed()
    {
        $this->_trashed = 'without';
        return $this;
    }

    /**
     * public function with_trashed()
     * Sets $_trashed to only
     */
    public function only_trashed()
    {
        $this->_trashed = 'only';
        return $this;
    }

    private function _where_trashed(): MY_Model
    {
        switch ($this->_trashed) {
            case 'only':
                $this->database->where($this->database->dbprefix($this->getTable()) . '.' . $this->deleted_at_column . ' IS NOT NULL', null, false);
                break;
            case 'without':
                $this->database->where($this->database->dbprefix($this->getTable()) . '.' . $this->deleted_at_column . ' IS NULL', null, false);
                break;
            case 'with':
                break;
        }
        //$this->_trashed = ''; issue #208...
        return $this;
    }

    /**
     * public function fields($fields)
     * does a select() of the $fields
     * @param $fields the fields needed
     * @return $this
     */
    public function fields($fields = null)
    {
        if (isset($fields)) {
            if ($fields == '*count*') {
                $this->_select = '';
                $this->database->select('COUNT(*) AS counted_rows', false);
            } else {
                $this->_select = array();
                $fields = (!is_array($fields)) ? explode(',', $fields) : $fields;
                if (!empty($fields)) {
                    foreach ($fields as &$field) {
                        $exploded = explode('.', $field);
                        if (sizeof($exploded) < 2) {
                            $field = $this->table . '.' . $field;
                        }
                    }
                }
                $this->_select = $fields;
            }
        } else {
            $this->_select = null;
        }
        return $this;
    }

    /**
     * public function order_by($criteria, $order = 'ASC'
     * A wrapper to $this->database->order_by()
     * @param $criteria
     * @param string $order
     * @return $this
     */
    public function order_by($criteria, $order = 'ASC')
    {
        if (is_array($criteria)) {
            foreach ($criteria as $key => $value) {
                $this->database->order_by($key, $value);
            }
        } else {
            $this->database->order_by($criteria, $order);
        }
        return $this;
    }

    /**
     * Return the next call as an array rather than an object
     */
    public function as_array()
    {
        $this->return_as = 'array';
        return $this;
    }

    /**
     * Return the next call as an object rather than an array
     */
    public function as_object()
    {
        $this->return_as = 'object';
        return $this;
    }

    /**
     * Sets columns for the created_at, updated_at and deleted_at timestamps
     *
     * @return void
     */
    protected function setTimestamps()
    {
        if ($this->timestamps) {
            $this->setCreatedAtColumn($this->getCreatedAtColumn());
            $this->setUpdatedAtColumn($this->getUpdatedAtColumn());
        }

        if(isset($this->soft_deletes) && $this->soft_deletes) {
            $this->setDeletedAtColumn($this->getDeletedAtColumn());
        }
    }

    /**
     * Sets the connection to database
     *
     * @param string|null $name
     * @return $this
     */
    public function setConnection(string $name = null): MY_Model
    {
        if (!is_null($name)) {
            $this->database = $this->load->database($name, true);
        } else {
            $this->load->database();
            $this->database = $this->db;
        }

        $this->connection = $this->db->dbdriver;

        return $this;
    }

    /*
     * HELPER FUNCTIONS
     */

    public function paginate($rows_per_page, $total_rows = null, $page_number = 1)
    {
        $this->load->helper('url');
        $segments = $this->uri->total_segments();
        $uri_array = $this->uri->segment_array();
        $page = $this->uri->segment($segments);

        if (is_numeric($page)) {
            $page_number = $page;
            $this->current_page = $page;
        } else {
            $uri_array[] = $page_number;
            ++$segments;
            $this->current_page = 1;
        }

        $next_page = $page_number + 1;
        $previous_page = $page_number - 1;

        if ($page_number == 1) {
            // $link_start = '<a href="javascript:;" class="page-link first">';
            // $link_end = '</a>';
            // $this->start_page = $this->pagination_all_delimiters[0].$link_start.$this->pagination_all_arrows[0].$link_end.$this->pagination_all_delimiters[1];

            $uri_array[$segments] = $previous_page;
            unset($uri_array[$segments]);
            $uri_string = implode('/', $uri_array);

            $this->path = base_url() . $uri_string;
        } else {
            $uri_array[$segments] = $previous_page;
            unset($uri_array[$segments]);
            $uri_string = implode('/', $uri_array);

            $this->start_page = $this->pagination_all_delimiters[0] . anchor($uri_string . query_string('', 'page'), $this->pagination_all_arrows[0], array('class' => 'page-link first')) . $this->pagination_all_delimiters[1];
            $this->path = base_url() . $uri_string;
        }

        if ($page_number == 1) {
            // $link_start = '<a href="javascript:;" class="page-link prev">';
            // $link_end = '</a>';
            // $this->previous_page = $this->pagination_delimiters[0].$link_start.$this->pagination_arrows[0].$link_end.$this->pagination_delimiters[1];

            $this->previous_page_url = null;
        } else {
            $uri_string = implode('/', $uri_array);
            if ($previous_page - 1) {
                $uri_string .= query_string(['page' => (int)$previous_page]);
            }
            $this->previous_page = $this->pagination_delimiters[0] . anchor($previous_page - 1 ? $uri_string : $uri_string . query_string('', 'page'), $this->pagination_arrows[0], array('class' => 'page-link prev')) . $this->pagination_delimiters[1];
            $this->previous_page_url = base_url() . ($previous_page - 1 ? $uri_string : $uri_string); //. query_string('', 'page');
        }

        unset($uri_array[$segments]);
        $uri_string = implode('/', $uri_array);
        $uri_string .= query_string(['page' => (int)$next_page]);
        if (isset($total_rows) && (ceil($total_rows / $rows_per_page) == $page_number)) {
            // $link_start = '<a href="javascript:;" class="page-link next">';
            // $link_end = '</a>';
            // $this->next_page = $this->pagination_delimiters[0].$link_start.$this->pagination_arrows[1].$link_end.$this->pagination_delimiters[1];
        } else {
            $this->next_page = $this->pagination_delimiters[0] . anchor($uri_string, $this->pagination_arrows[1], array('class' => 'page-link next')) . $this->pagination_delimiters[1];
            $this->next_page_url = base_url() . $uri_string;
        }

        if (isset($total_rows) && (ceil($total_rows / ($rows_per_page === 0 ? 1 : $rows_per_page)) == $page_number)) {
            // $link_start = '<a href="javascript:;" class="page-link last">';
            // $link_end = '</a>';
            // $this->end_page = $this->pagination_all_delimiters[0].$link_start.$this->pagination_all_arrows[1].$link_end.$this->pagination_all_delimiters[1];
        } else {
            $last = ceil($total_rows / ($rows_per_page === 0 ? 1 : $rows_per_page));
            unset($uri_array[$segments]);
            $uri_string = implode('/', $uri_array);
            $uri_string .= query_string(['page' => (int)$last]);
            $this->end_page = $this->pagination_all_delimiters[0]
                . anchor($uri_string, $this->pagination_all_arrows[1],
                    ['class' => 'page-link last'])
                . $this->pagination_all_delimiters[1];
        }

        $rows_per_page = (is_numeric($rows_per_page)) ? $rows_per_page : 10;

        if (!is_null($this->on_each_side)) {
            // to each side
            $start = $page_number
                - $this->on_each_side; // show 3 pagination links before current
            $end = $page_number
                + $this->on_each_side; // show 3 pagination links after current

            if ($start < 1) {
                $start = 1; // reset start to 1
                $end += 1;
            }

            $last = ceil($total_rows / ($rows_per_page === 0 ? 1 : $rows_per_page));

            if ($end >= $last) {
                $end = $last;
            } // reset end to last page
        }

        if (isset($total_rows)) {
            if ($total_rows > $rows_per_page) {
                $number_of_pages = ceil($total_rows / $rows_per_page);
                $links = $this->start_page;
                $links .= $this->previous_page;

                if (!is_null($this->on_each_side)) {
                    for ($i = $start; $i <= $end; $i++) {
                        unset($uri_array[$segments]);
                        $uri_string = implode('/', $uri_array);
                        if ($page_number == $i) {
                            $pagination_delimiter
                                = substr_replace($this->pagination_delimiters[0],
                                'active ', 11, 0);
                            $link_start
                                = '<a href="javascript:;" class="page-link">';
                            $link_end = '</a>';

                            $links .= $pagination_delimiter;
                            $links .= $link_start . $i . $link_end;
                            $links .= $this->pagination_delimiters[1];
                        } else {
                            $links .= $this->pagination_delimiters[0];
                            $links .= ($i == 1)
                                ? anchor($uri_string . query_string('', 'page'),
                                    $i, ['class' => 'page-link'])
                                : anchor($uri_string
                                    . query_string(['page' => $i]), $i,
                                    ['class' => 'page-link']);
                            $links .= $this->pagination_delimiters[1];
                        }
                    }
                } else {
                    for ($i = 1; $i <= $number_of_pages; $i++) {
                        unset($uri_array[$segments]);
                        $uri_string = implode('/', $uri_array);
                        if ($page_number == $i) {
                            $pagination_delimiter
                                = substr_replace($this->pagination_delimiters[0],
                                'active ', 11, 0);
                            $link_start
                                = '<a href="javascript:;" class="page-link">';
                            $link_end = '</a>';

                            $links .= $pagination_delimiter;
                            $links .= $link_start . $i . $link_end;
                            $links .= $this->pagination_delimiters[1];
                        } else {
                            $links .= $this->pagination_delimiters[0];
                            $links .= ($i == 1)
                                ? anchor($uri_string . query_string('', 'page'),
                                    $i, ['class' => 'page-link'])
                                : anchor($uri_string
                                    . query_string(['page' => $i]), $i,
                                    ['class' => 'page-link']);
                            $links .= $this->pagination_delimiters[1];
                        }
                    }
                }


                $links .= $this->next_page;
                $links .= $this->end_page;
                $this->all_pages = $links;
            } else {
                $this->all_pages = $this->pagination_delimiters[0] . $this->pagination_delimiters[1];
            }
        }

        $this->trigger('before_get_all');
//            $this->trigger('before_get');
        $this->where();
        $this->limit($rows_per_page, (($page_number - 1) * $rows_per_page));
        $data = $this->get_all();
        if ($data) {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Extend paginate method of MY_Model
     *
     * @param $pagination
     * @param array $options
     * @return \Elegant\Support\Collection
     */
    public function pagination($pagination, array $options = []): Collection
    {
        if ($this->input->get('page')) {
            $page = $this->input->get('page');
        } else {
            $page = 1;
        }

        $data = $this->{$this->model_name};

        $data = $this->optionQuery($data, $options);

        $paginationRows = $this->countPaginateRows($data, $options);

        $data = $paginationRows['data'];
        $count_rows = $paginationRows['rows'];

        $pagination = request()->has('limit') && $pagination >= $count_rows
            ? $count_rows : $pagination;

        $data = collect($data->paginate($pagination, $count_rows, $page));

        $firstPage = 1;
        $lastPage = max((int)ceil($count_rows / ($pagination === 0 ? 1 : $pagination)), 1);
        $data->firstItem = $count_rows ? (($page - 1) * $pagination) + 1 : 0;
        $data->lastItem = (($page - 1) * $pagination) > ($count_rows - $pagination)
            ? $count_rows
            : ((($page - 1) * $pagination) + $pagination);
        $data->pages = (int)ceil($count_rows / ($pagination === 0 ? 1 : $pagination));
        $data->total = $count_rows;
        $data->links = $pagination > $count_rows
            ? ''
            : $this->{$this->model_name}->all_pages;
        $data->path = $this->{$this->model_name}->path;
        $data->firstPage = $data->path . query_string(['page' => $firstPage]);
        $data->lastPage = $data->path . query_string(['page' => $lastPage]);
        $data->previous_page_url = $this->{$this->model_name}->previous_page_url;
        $data->next_page_url = $this->{$this->model_name}->next_page_url;
        $data->current_page = $this->{$this->model_name}->current_page;
        $data->from = $data->firstItem;
        $data->last_page = $lastPage;
        $data->per_page = $pagination;
        $data->to = $data->lastItem;

        return $data;
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @return int
     */
    public function count()
    {
        return $this->{$this->model_name}->without_trashed()->count_rows();
    }

    public function countAll()
    {
        return $this->{$this->model_name}->as_object()->with_trashed()->count_rows();
    }

    public function countTrash()
    {
        return $this->{$this->model_name}->as_object()->only_trashed()->count_rows();
    }

    public function firstItem(int $page = 1)
    {
        $total = $this->count_rows;

        return $total ? (($page - 1) * $this->model_limit) + 1 : 0;
    }

    public function lastItem(int $page = 1)
    {
        $total = $this->count_rows;

        if ((($page - 1) * $this->model_limit) > ($total - $this->model_limit)) {
            return $total;
        } else {
            return ((($page - 1) * $this->model_limit) + $this->model_limit);
        }
    }

    /**
     * Get count pages
     *
     * @return false|float
     */
    public function pages()
    {
        return (int)ceil($this->count_rows / $this->model_limit);
    }

    /**
     * Get last page of model data
     *
     * @return int
     */
    public function lastPage(): int
    {
        return max((int)ceil($this->count_rows / $this->model_limit), 1);
    }

    /**
     * Get first page of model data
     *
     * @return int
     */
    public function firstPage(): int
    {
        return 1;
    }

    /**
     * Generate links html for pagination
     *
     * @return string
     */
    public function links(): string
    {
        if ($this->model_limit > $this->count_rows) {
            return '';
        }

        return $this->{$this->model_name}->all_pages;
    }

    /**
     * Count pagination rows with where conditions
     *
     * @param $query
     * @param $options
     * @return array
     */
    private function countPaginateRows($query, $options): array
    {
        if (Arr::exists($options, 'trashed')) {
            if ($options['trashed']) {
                $count_rows = $query->count_rows();
            } else {
                $count_rows = $query->without_trashed()->count_rows();
            }
        } else {
            $count_rows = $query->without_trashed()->count_rows();
        }

        $data = $this->{$this->model_name};

        $data = $this->optionQuery($data, $options);

        return [
            'data' => $data,
            'rows' => $count_rows
        ];
    }

    /**
     * @param $query
     * @param $options
     * @return mixed
     */
    private function optionQuery($query, $options)
    {
        if (!empty($options)) {
            if (Arr::exists($options, 'where')) {
                Arr::map($options['where'], function ($value, $key) use ($query) {
                    $query->where([$key => $value]);
                });
            }

            if (Arr::exists($options, 'orderBy')) {
                Arr::map($options['orderBy'], function ($value, $key) use ($query) {
                    $query->orderBy($key, $value);
                });
            }
        }

        if (Arr::exists($options, 'trashed')) {
            if ($options['trashed']) {
                $query->with_trashed();
            } else {
                $query->without_trashed();
            }
        } else {
            $query->without_trashed();
        }

        return $query;
    }

    public function set_pagination_delimiters($delimiters)
    {
        if (is_array($delimiters) && sizeof($delimiters) == 2) {
            $this->pagination_delimiters = $delimiters;
        }
        return $this;
    }

    public function set_pagination_arrows($arrows)
    {
        if (is_array($arrows) && sizeof($arrows) == 2) {
            $this->pagination_arrows = $arrows;
        }
        return $this;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function __call($method, $arguments)
    {
        if (substr($method, 0, 6) == 'where_') {
            $column = substr($method, 6);
            $this->where($column, $arguments);
            return $this;
        }
        if (($method != 'with_trashed') && (substr($method, 0, 5) == 'with_')) {
            $relation = substr($method, 5);
            $this->with($relation, $arguments);
            return $this;
        }
        if (method_exists($this->database, $method)) {
            call_user_func_array(array($this->database, $method), $arguments);
            return $this;
        }
        $parent_class = get_parent_class($this);
        if ($parent_class !== false && !method_exists($parent_class, $method) && !method_exists($this, $method)) {
            $msg = 'The method "' . $method . '" does not exist in ' . get_class($this) . ' or MY_Model or CI_Model.';
            show_error($msg, EXIT_UNKNOWN_METHOD, 'Method Not Found');
        }
    }

    private function _build_sorter($data, $field, $order_by, $sort_by = 'DESC')
    {
        usort($data, function ($a, $b) use ($field, $order_by, $sort_by) {
            $array_a = isset($a[$field]) ? $this->object_to_array($a[$field]) : null;
            $array_b = isset($b[$field]) ? $this->object_to_array($b[$field]) : null;
            return strtoupper($sort_by) == "DESC" ?
                ((isset($array_a[$order_by]) && isset($array_b[$order_by])) ? ($array_a[$order_by] < $array_b[$order_by]) : (!isset($array_a) ? 1 : -1))
                : ((isset($array_a[$order_by]) && isset($array_b[$order_by])) ? ($array_a[$order_by] > $array_b[$order_by]) : (!isset($array_b) ? 1 : -1));
        });

        return $data;
    }

    public function object_to_array($object)
    {
        if (!is_object($object) && !is_array($object)) {
            return $object;
        }
        if (is_object($object)) {
            $object = get_object_vars($object);
        }
        return array_map(array($this, 'object_to_array'), $object);
    }

    public function array_to_object($array)
    {
        $obj = new stdClass();
        return $this->_array_to_object($array, $obj);
    }

    private function _array_to_object($array, &$obj)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $obj->$key = new stdClass();
                $this->_array_to_object($value, $obj->$key);
            } else {
                $obj->$key = $this->castAttribute($key, $value);
//                $obj->$key = $value;
            }
        }

        return $obj;
    }

    /**
     * Verifies if an array is associative or not
     * @param array $array
     * @return bool
     */
    protected function is_assoc(array $array)
    {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }

    /**
     * private function _parse_model_dir($foreign_model)
     *
     * Parse model and model folder
     * @param $foreign_model
     * @return $data
     */
    private function _parse_model_dir($foreign_model)
    {
        $data['foreign_model'] = $foreign_model;
        $data['model_dir'] = '';

        $full_model = explode('/', $data['foreign_model']);
        if ($full_model) {
            $data['foreign_model'] = end($full_model);
            $data['model_dir'] = str_replace($data['foreign_model'], null, implode('/', $full_model));
        }

        $foreign_model_name = str_replace('/', '_', $data['model_dir'] . $data['foreign_model']);

        $data['foreign_model_name'] = strtolower($foreign_model_name);

        return $data;
    }

    /**
     * Take from database items we need.
     * @param Integer $number
     * @return $this
     */
    public function take($number)
    {
        $this->limit($number);
        return $this;
    }

    /**
     * Random Order database items.
     * @return $this
     */
    protected function random(): MY_Model
    {
        $this->order_by('rand()');

        return $this;
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param $column
     * @return mixed
     *
     * TODO: Fix work with soft deleted
     */
    public function min($column)
    {
        $this->database->select_min($column, 'min_' . $column);

        return $this->database->get($this->table)->row('min_' . $column);
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param $column
     * @return mixed
     *
     * TODO: Fix work with soft deleted
     */
    public function max($column)
    {
        $this->database->select_max($column, 'max_' . $column);

        return $this->database->get($this->table)->row('max_' . $column);
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param string $column
     * @return mixed
     *
     * TODO: Fix work with soft deleted
     */
    public function sum($column)
    {
        $this->database->select_sum($column, 'sum_' . $column);

        return $this->database->get($this->table)->row('sum_' . $column);
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param $column
     * @return mixed
     *
     * TODO: Fix work with soft deleted
     */
    public function avg($column)
    {
        $this->database->select_avg($column, 'average_' . $column);

        return $this->database->get($this->table)->row('average_' . $column);
    }


    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table ?? Str::snake(Str::pluralStudly(get_class($this)));
    }

    /**
     * Set the table associated with the model.
     *
     * @param string $table
     * @return void
     */
    public function setTable(string $table)
    {
        $this->table =  $table;
    }
}