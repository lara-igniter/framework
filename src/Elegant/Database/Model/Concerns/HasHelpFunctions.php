<?php

namespace Elegant\Database\Model\Concerns;

use Elegant\Support\Arr;
use InvalidArgumentException;
use stdClass;

trait HasHelpFunctions
{
    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    public array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
    ];

    /**
     * Passing where condition to a database query when call update.
     *
     * @param $attributes
     * @param $where
     * @return void
     */
    protected function updateWhere($attributes, $where)
    {
        if (!empty($where)) {
            if (is_array($where)) {
                $this->where($where);
            } elseif (is_numeric($where)) {
                $this->database->where($this->primaryKey, $where);
            } else {
                $column = (is_object($attributes)) ? $attributes->{$where} : $attributes[$where];
                $this->database->where($where, $column);
            }
        }
    }

    public function _prep_after_read($data, $multi = true)
    {
        // let's join the subqueries...
        $data = $this->join_temporary_results($data);

        $this->database->reset_query();
        $this->_requested = array();

        // Reset the HasScopes guard so the next query on this instance
        // applies global scopes again from scratch.
        $this->globalScopesApplied = false;

        $data = $this->array_to_object($data);

        if (isset($this->columns)) {
            $this->columns = '*';
        }

        return $data;
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
            return $this->database->escape($value);
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
     * Capture all relevant CI query-builder properties so they can be
     * restored after count_all_results() resets them.
     * Uses Closure::bind to reach the protected qb_* members.
     *
     * @return array
     */
    private function captureBuilderState(): array
    {
        $keys = [
            'qb_select', 'qb_join', 'qb_where', 'qb_like',
            'qb_groupby', 'qb_having', 'qb_orderby',
            'qb_aliased_tables', 'qb_no_escape', 'qb_distinct',
        ];

        $db = $this->database;

        return \Closure::bind(function () use ($keys) {
            $state = [];
            foreach ($keys as $key) {
                if (property_exists($this, $key)) {
                    $state[$key] = $this->{$key};
                }
            }
            return $state;
        }, $db, get_class($db))();
    }

    /**
     * Restore the CI query-builder state saved by captureBuilderState().
     * Uses Closure::bind to reach the protected qb_* members.
     *
     * @param array $state
     * @return void
     */
    private function restoreBuilderState(array $state): void
    {
        $db = $this->database;

        \Closure::bind(function () use ($state) {
            foreach ($state as $key => $value) {
                $this->{$key} = $value;
            }
        }, $db, get_class($db))();
    }

    /**
     * Prepare the value and operator for a where clause.
     *
     * @param string $value
     * @param string $operator
     * @param bool $useDefault
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function prepareValueAndOperator($value, string $operator, bool $useDefault = false): array
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function invalidOperatorAndValue(string $operator, $value): bool
    {
        return is_null($value) && in_array($operator, $this->operators) &&
            !in_array($operator, ['=', '<>', '!=']);
    }
}
