<?php

namespace Elegant\Database\Model\Concerns;

use Elegant\Database\Model\Model;
use Elegant\Support\Arr;
use Elegant\Support\Str;

trait HasRelationships
{
    private array $relations = [];

    public array $hasOne = [];

    public array $hasMany = [];

    public array $hasManyPivot = [];

    public array $morphOne = [];

    public array $morphMany = [];

    public array $morphToMany = [];

    /**
     * The many-to-many relationship methods.
     *
     * @var string[]
     */
    public static array $manyMethods = [
        'hasMany', 'hasManyPivot',
    ];

    private array $_requested = [];

    /**
     * Define a one-to-one relationship.
     *
     * @param string $related
     * @param string|null $foreignKey
     * @param string|null $localKey
     * @return \Elegant\Database\Model\Model
     */
    public function hasOne(string $related, string $foreignKey = null, string $localKey = null): Model
    {
        $relation = $this->guessBelongsToRelation();

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        $this->hasOne = [
            $relation => [
                $related,
                $localKey,
                $foreignKey,
            ],
        ];

        $this->with($relation);

        return $this;
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param string $related
     * @param string|null $foreignKey
     * @param string|null $localKey
     * @return \Elegant\Database\Model\Model
     */
    public function hasMany(string $related, string $foreignKey = null, string $localKey = null): Model
    {
        $relation = $this->guessBelongsToRelation();

        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $instance->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        $this->hasMany = [
            $relation => [
                'foreign_model' => $related,
                'foreign_table' => $instance->getTable(),
                'foreign_key' => $localKey,
                'local_key' => $foreignKey
            ]
        ];

        $this->with($relation);

        return $this;
    }

    /**
     * Define a many-to-many pivot relationship.
     *
     * @param string $related
     * @param string|null $table
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @param string|null $parentKey
     * @param string|null $relatedKey
     * @param string|null $relation
     * @return \Elegant\Database\Model\Model
     */
    public function hasManyPivot(string $related, string $table = null, string $foreignPivotKey = null,
                                  string $relatedPivotKey = null, string $parentKey = null, string $relatedKey = null,
                                  string $relation = null): Model
    {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($related, $instance);
        }

        $this->hasManyPivot = [
            $relation => [
                'foreign_model' => $related,
                'pivot_table' => $table,
                'local_key' => $parentKey ?: $this->getKeyName(),
                'pivot_local_key' => $foreignPivotKey,
                'pivot_foreign_key' => $relatedPivotKey,
                'foreign_key' => $relatedKey ?: $instance->getKeyName(),
                'get_relate' => true
            ]
        ];

        $this->with($relation);

        return $this;
    }

    /**
     * Guess the "belongs to" relationship name.
     *
     * @return string
     */
    protected function guessBelongsToRelation(): string
    {
        [$one, $two, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller['function'];
    }

    /**
     * Get the relationship name of the belongsToMany relationship.
     *
     * @return string|null
     */
    public function guessBelongsToManyRelation(): ?string
    {
        $caller = Arr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function ($trace) {
            return ! in_array(
                $trace['function'],
                array_merge(static::$manyMethods, ['guessBelongsToManyRelation'])
            );
        });

        return ! is_null($caller) ? $caller['function'] : null;
    }

    /**
     * Get the joining table name for a many-to-many relation.
     *
     * @param string $related
     * @param \Elegant\Database\Model\Model|null $instance
     * @return string
     */
    public function joiningTable(string $related, Model $instance = null): string
    {
        $segments = [
            $instance ? $instance->joiningTableSegment()
                : Str::snake(class_basename($related)),
            $this->joiningTableSegment(),
        ];

        // Now that we have the model names in an array we can just sort them and
        // use the implode function to join them together with an underscores,
        // which is typically used by convention within the database system.
        sort($segments);

        // Add plural in the second table
        // TODO: Remove and make pivot table (e.g role_user)
        $segments[1] = Str::plural($segments[1]);

        return strtolower(implode('_', $segments));
    }

    /**
     * Create a new model instance for a related model.
     *
     * @param string $class
     * @return mixed
     */
    protected function newRelatedInstance(string $class)
    {
        return tap(new $class, function ($instance) {
            return $instance;
        });
    }

    /**
     * Get this model's half of the intermediate table name for belongsToMany relationships.
     *
     * @return string
     */
    public function joiningTableSegment(): string
    {
        return Str::snake(class_basename($this));
    }

    public function _get_joined($requested)
    {
        $this->database->join($this->relations[$requested['request']]['foreign_table'], $this->table . '.' . $this->relations[$requested['request']]['local_key'] . ' = ' . $this->relations[$requested['request']]['foreign_table'] . '.' . $this->relations[$requested['request']]['foreign_key'], 'left');
        $the_select = '';
        if (!empty($requested['parameters'])) {
            if (array_key_exists('fields', $requested['parameters'])) {
                $fields = explode(',', $requested['parameters']['fields']);
                $sub_select = array();
                foreach ($fields as $field) {
                    $sub_select[] = ((strpos($field, '.') === false) ? '`' . $this->relations[$requested['request']]['foreign_table'] . '`.`' . trim($field) . '`' : trim($field)) . ' AS ' . $requested['request'] . '_' . trim($field);
                }
                $the_select = implode(',', $sub_select);
            } else {
                $the_select = $this->relations[$requested['request']]['foreign_table'] . '.*';
            }
        }
        $this->database->select($the_select);
        unset($this->_requested[$requested['request']]);
    }

    /**
     * Get the polymorphic relationship columns.
     *
     * @param string $name
     * @param string|null $type
     * @param string|null $id
     * @return array
     */
    protected function getMorphs(string $name, ?string $type, ?string $id): array
    {
        return [$type ?: $name . '_type', $id ?: $name . '_id'];
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
            $the_where = null;
            $relation = $this->relations[$request['request']];
            $this->load->model($relation['foreign_model'], $relation['foreign_model_name']);
            $foreign_key = $relation['foreign_key'];
            $local_key = $relation['local_key'];
            $foreign_table = $relation['foreign_table'];
            $type = $relation['relation'];
            $relation_key = $relation['relation_key'];
            if ($type == 'hasManyPivot' || $type == 'morphToMany') {
                if ($type == 'morphToMany') {
                    [$model_type, $model_id] = $this->getMorphs($relation['foreign_column'], null, null);
                }

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

                    // Remove above line because sub query where escape '' from value and execute sql query error
                    // $sub_results = isset($the_where) ? $sub_results->where($request['parameters'][$the_where], null, null, false, false, true) : $sub_results;
                    $sub_results = isset($the_where) ? $sub_results->where($request['parameters'][$the_where]) : $sub_results;

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

                if ($type == 'morphOne' || $type == 'morphMany') {
                    [$model_type, $model_id] = $this->getMorphs($relation['foreign_column'], null, null);
                    $sub_results = $sub_results->where($model_id, $local_key_values)->where($model_type, strtolower(get_class($this)))->get_all();
                } else {
                    $sub_results = $sub_results->where($foreign_key, $local_key_values)->get_all();
                }
            } else {
                if ($type == 'morphToMany') {
                    $this->database->join($pivot_table, $foreign_table . '.' . $foreign_key . ' = ' . $pivot_table . '.' . $relation['foreign_model'] . '_' . $relation['foreign_key'], 'left');
                    $this->database->join($this->table, $pivot_table . '.' . $pivot_local_key . ' = ' . $this->table . '.' . $local_key, 'left');
                    $this->database->select($pivot_table . '.' . $relation['foreign_model'] . '_' . $relation['foreign_key'] . ' AS id');
                } else {
                    $this->database->join($pivot_table, $foreign_table . '.' . $foreign_key . ' = ' . $pivot_table . '.' . $pivot_foreign_key, 'left');
                    $this->database->join($this->table, $pivot_table . '.' . $pivot_local_key . ' = ' . $this->table . '.' . $local_key, 'left');
                    $this->database->select($foreign_table . '.' . $foreign_key);
                }
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

                        $this->database->where($request['parameters'][$the_where]);
                    }
                }
                $this->database->where_in($pivot_table . '.' . $pivot_local_key, $local_key_values);

                if ($type == 'morphToMany') {
                    $this->database->where($pivot_table . '.' . $model_type, get_class($this));
                }

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
                            $subs[$the_local_key][$the_foreign_key] = $this->{$relation['foreign_model']}->where($foreign_key, $result[$foreign_key])->get()->first();
                        } else {
                            $subs[$the_local_key][$the_foreign_key] = $result;
                        }
                    } else {
                        if ($type == 'hasOne' || $type == 'morphOne') {
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
                $data[$key][$relation_key] = [];
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
     * private function _set_relationships()
     *
     * Called by the public method with() it will set the relationships between the current model and other models
     */
    private function _set_relationships()
    {
        if (empty($this->relations)) {
            $options = array('hasOne', 'hasMany', 'hasManyPivot', 'morphOne', 'morphMany', 'morphToMany');
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
                                if ($option == 'hasManyPivot' || $option == 'morphToMany') {
                                    $pivot_table = $relation['pivot_table'];
                                    $pivot_local_key = (array_key_exists('pivot_local_key', $relation)) ? $relation['pivot_local_key'] : $this->table . '_' . $this->primaryKey;
                                    $pivot_foreign_key = (array_key_exists('pivot_foreign_key', $relation)) ? $relation['pivot_foreign_key'] : $foreign_table . '_' . $foreign_key;
                                    $get_relate = (array_key_exists('get_relate', $relation) && ($relation['get_relate'] === true)) ? true : false;
                                }
                                if ($option == 'hasOne' && isset($relation['join']) && $relation['join'] === true) {
                                    $single_query = true;
                                }
                                if ($option == 'morphOne' || $option == 'morphMany' || $option == 'morphToMany') {
                                    $foreign_column = $relation['foreign_column'];
                                }
                            } else {
                                $foreign_model = $relation[0];
                                $model = $this->_parse_model_dir($foreign_model);
                                $foreign_model = $model['model_dir'] . $model['foreign_model'];
                                $foreign_model_name = $model['foreign_model_name'];

                                $this->load->model($foreign_model);
                                $foreign_table = $this->{$foreign_model}->table;
                                if ($option == 'morphOne') {
                                    $foreign_column = $relation[1];
                                    [$type, $id] = $this->getMorphs($foreign_column, null, null);
                                    $foreign_key = $id;
                                    $local_key = $relation['local_key'] ?? 'id';
                                } else {
                                    $foreign_key = $relation[1];
                                    $local_key = $relation[2];
                                }

                                if ($option == 'hasManyPivot' || $option == 'morphToMany') {
                                    $pivot_local_key = $this->table . '_' . $this->primaryKey;
                                    $pivot_foreign_key = $foreign_table . '_' . $foreign_key;
                                    $get_relate = (isset($relation[3]) && ($relation[3] === true)) ? true : false;
                                }
                            }
                        }

                        if (($option == 'hasManyPivot' || $option == 'morphToMany') && !isset($pivot_table)) {
                            $tables = array($this->table, $foreign_table);
                            sort($tables);
                            $pivot_table = $tables[0] . '_' . $tables[1];
                        }

                        $this->relations[$key] = array(
                            'relation' => $option,
                            'relation_key' => $key,
                            'foreign_model' => strtolower($foreign_model),
                            'foreign_model_name' => strtolower($foreign_model_name),
                            'foreign_column' => $foreign_column ?? '',
                            'foreign_table' => $foreign_table,
                            'foreign_key' => $foreign_key,
                            'local_key' => $local_key
                        );

                        if ($option == 'hasManyPivot' || $option == 'morphToMany') {
                            $this->relations[$key]['pivot_table'] = $pivot_table;
                            $this->relations[$key]['pivot_local_key'] = $pivot_local_key;
                            $this->relations[$key]['pivot_foreign_key'] = $pivot_foreign_key;
                            $this->relations[$key]['get_relate'] = $get_relate;
                        }
                        if ($single_query === true) {
                            $this->relations[$key]['joined'] = true;
                        }
                    }
                }
            }
        }
    }

    /**
     * private function _parse_model_dir($foreign_model)
     *
     * Parse model and model folder
     * @param $foreign_model
     * @return $data
     */
    private function _parse_model_dir($foreign_model): array
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
     * Verifies if an array is associative or not
     * @param array $array
     * @return bool
     */
    protected function is_assoc(array $array): bool
    {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
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
}
