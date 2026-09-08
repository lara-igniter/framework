<?php

namespace Elegant\Database\Schema;

use Elegant\Support\Arr;
use Elegant\Support\Str;
use Elegant\Support\Stringable;

abstract class Forge
{

    /**
     * Database object
     *
     * @var    object
     */
    protected $db;

    /**
     * Fields data
     *
     * @var    array
     */
    public $fields = [];

    /**
     * Last inserted field name
     *
     * @var    string
     */
    public string $last_field = '';

    /**
     * Keys data
     *
     * @var    array
     */
    public $keys = [];

    /**
     * Primary Keys data
     *
     * @var    array
     */
    public $primary_keys = [];

    /**
     * Foreign Keys data
     *
     * @var    array
     */
    public $foreign_keys = [];

    /**
     * Database character set
     *
     * @var    string
     */
    public $db_char_set = '';

    // --------------------------------------------------------------------

    /**
     * CREATE DATABASE statement
     *
     * @var    string
     */
    protected $_create_database = 'CREATE DATABASE %s';

    /**
     * DROP DATABASE statement
     *
     * @var    string
     */
    protected $_drop_database = 'DROP DATABASE %s';

    /**
     * CREATE TABLE statement
     *
     * @var    string
     */
    protected $_create_table = "%s %s (%s\n)";

    /**
     * CREATE TABLE IF statement
     *
     * @var    string
     */
    protected $_create_table_if = 'CREATE TABLE IF NOT EXISTS';

    /**
     * CREATE TABLE keys flag
     *
     * Whether table keys are created from within the
     * CREATE TABLE statement.
     *
     * @var    bool
     */
    protected $_create_table_keys = false;

    /**
     * DROP TABLE IF EXISTS statement
     *
     * @var    string
     */
    protected $_drop_table_if = 'DROP TABLE IF EXISTS';

    /**
     * RENAME TABLE statement
     *
     * @var    string
     */
    protected $_rename_table = 'ALTER TABLE %s RENAME TO %s;';

    /**
     * UNSIGNED support
     *
     * @var    bool|array
     */
    protected $_unsigned = true;

    /**
     * NULL value representatin in CREATE/ALTER TABLE statements
     *
     * @var    string
     */
    protected $_null = '';

    /**
     * DEFAULT value representation in CREATE/ALTER TABLE statements
     *
     * @var    string
     */
    protected $_default = ' DEFAULT ';

    /**
     * The default string length for migrations.
     *
     * @var int
     */
    public static int $defaultStringLength = 255;

    // --------------------------------------------------------------------

    /**
     * Class constructor
     *
     * @param object    &$db Database object
     * @return    void
     */
    public function __construct(&$db)
    {
        $this->db =& $db;
        log_message('debug', 'Database Forge Class Initialized');
    }

    // --------------------------------------------------------------------

    /**
     * Create database
     *
     * @param string $db_name
     * @return    bool
     */
    public function create_database($db_name)
    {
        if ($this->_create_database === false) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
        } elseif (!$this->db->query(sprintf($this->_create_database, $db_name, $this->db->char_set, $this->db->dbcollat))) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unable_to_drop') : false;
        }

        if (!empty($this->db->data_cache['db_names'])) {
            $this->db->data_cache['db_names'][] = $db_name;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Drop database
     *
     * @param string $db_name
     * @return    bool
     */
    public function drop_database($db_name)
    {
        if ($db_name === '') {
            show_error('A table name is required for that operation.');
            return false;
        } elseif ($this->_drop_database === false) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
        } elseif (!$this->db->query(sprintf($this->_drop_database, $db_name))) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unable_to_drop') : false;
        }

        if (!empty($this->db->data_cache['db_names'])) {
            $key = array_search(strtolower($db_name), array_map('strtolower', $this->db->data_cache['db_names']), true);
            if ($key !== false) {
                unset($this->db->data_cache['db_names'][$key]);
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Add Key
     *
     * @param string $key
     * @param bool $primary
     * @return    self
     */
    public function add_key($key = '', $primary = false)
    {
        if (empty($key)) {
            show_error('Key information is required for that operation.');
        }

        if ($primary === true && is_array($key)) {
            foreach ($key as $one) {
                $this->add_key($one, $primary);
            }

            return $this;
        }

        if ($primary === true) {
            $this->primary_keys[] = $key;
        } else {
            $this->keys[] = $key;
        }

        return $this;
    }

    /**
     * Add Primary Key
     *
     * @param array|string $columns
     * @return self
     */
    public function primary($columns): self
    {
        $columns = Arr::wrap($columns);

        $this->add_key($columns, true);

        return $this;
    }

    /**
     * @param $key
     * @param $primary
     * @return void
     */
    public function add_index($key = [], $primary = false)
    {
        if (is_string($key)) {
            $this->add_key($key, $primary);
        }

        if (is_array($key)) {
            foreach ($key as $one) {
                $this->add_key($one, $primary);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Add Foreign Key
     *
     * Accepts a string or an array.  A string would be the foreign key SQL syntax.
     * An array will have the following elements:
     *  field (string) - name of the field for the foreign key
     *  foreign_table (string) - name of the table being referenced
     *  foreign_field (string) - name of the field being referenced in the foreign table
     *  delete (string) - the reference option for delete [optional]
     *  update (string) - the reference option for update [optional]
     * Only 1 Foreign Key can be added per method call
     *
     * @param string|array $key The foreign key being added
     * @return    self
     */
    public function add_foreign_key($key = '')
    {
        if (empty($key)) {
            show_error('Key information is required for that operation.');
        }

        $this->foreign_keys[] = $key;

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Add Field
     *
     * @param array $field
     * @return    self
     */
    public function add_field($field = '')
    {
        if (empty($field)) {
            show_error('Field information is required.');
        }

        if (is_string($field)) {
            if ($field === 'id') {
                $this->add_field([
                    'id' => [
                        'type' => 'BIGINT',
                        'unsigned' => true,
                        'auto_increment' => true
                    ]
                ]);
                $this->add_key('id', true);
            } elseif (substr($field, -2) === 'id') {
                $this->add_field([
                    '' . $field . '' => [
                        'type' => 'BIGINT',
                        'unsigned' => true,
                        'auto_increment' => true
                    ]
                ]);
                $this->add_key('' . $field . '', true);
            } else {
                if (strpos($field, ' ') === false) {
                    show_error('Field information is required for that operation.');
                }

                $this->fields[] = $field;
            }
        }

        if (is_array($field)) {
            $this->fields = array_merge($this->fields, $field);

            $this->last_field = key($field);
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Create Table
     *
     * @param string $table Table name
     * @param bool $if_not_exists Whether to add IF NOT EXISTS condition
     * @param array $attributes Extra table attributes
     * @return    bool
     */
    public function create_table(string $table = '', bool $if_not_exists = true, array $attributes = [])
    {
        if ($table === '') {
            show_error('A table name is required for that operation.');
        } else {
            $table = $this->db->dbprefix . $table;
        }

        if (count($this->fields) === 0) {
            show_error('Field information is required.');
        }

        $sql = $this->_create_table($table, $if_not_exists, $attributes);

        if (is_bool($sql)) {
            $this->_reset();
            if ($sql === false) {
                return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
            }
        }

        if (($result = $this->db->query($sql)) !== false) {
            empty($this->db->data_cache['table_names']) or $this->db->data_cache['table_names'][] = $table;

            // Most databases don't support creating indexes from within the CREATE TABLE statement
            if (!empty($this->keys)) {
                for ($i = 0, $sqls = $this->_process_indexes($table), $c = count($sqls); $i < $c; $i++) {
                    $this->db->query($sqls[$i]);
                }
            }
        }

        $this->_reset();
        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * Create Table
     *
     * @param string $table Table name
     * @param bool $if_not_exists Whether to add 'IF NOT EXISTS' condition
     * @return    mixed
     */
    protected function _create_table(string $table, bool $if_not_exists, array $attributes)
    {
        if ($if_not_exists === true && $this->_create_table_if === false) {
            if ($this->db->table_exists($table)) {
                return true;
            } else {
                $if_not_exists = false;
            }
        }

        $sql = ($if_not_exists)
            ? sprintf($this->_create_table_if, $this->db->escape_identifiers($table))
            : 'CREATE TABLE';

        $columns = $this->_process_fields(true);
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            $columns[$i] = ($columns[$i]['_literal'] !== false)
                ? "\n\t" . $columns[$i]['_literal']
                : "\n\t" . $this->_process_column($columns[$i]);
        }

        $columns = implode(',', $columns)
            . $this->_process_primary_keys($table)
            . $this->_process_foreign_keys();

        // Are indexes created from within the CREATE TABLE statement? (e.g. in MySQL)
        if ($this->_create_table_keys === true) {
            $columns .= $this->_process_indexes($table);
        }

        // _create_table will usually have the following format: "%s %s (%s\n)"
        $sql = sprintf(
            $this->_create_table . '%s',
            $sql,
            $this->db->escape_identifiers($table),
            $columns,
            $this->_create_table_attributes($attributes)
        );

        return $sql;
    }

    protected function _create_table_attributes(array $attributes): string
    {
        if ($this->db->dbdriver === 'sqlite3') {
            return '';
        }

        $sql = '';

        foreach (array_keys($attributes) as $key) {
            if (is_string($key)) {
                $sql .= ' ' . strtoupper($key) . ' ' . $this->db->escape($attributes[$key]);
            }
        }

        return $sql;
    }

    // --------------------------------------------------------------------

    /**
     * Drop Table
     *
     * @param string $table_name Table name
     * @param bool $if_exists Whether to add an IF EXISTS condition
     * @return    bool
     */
    public function drop_table(string $table_name, bool $if_exists = true)
    {
        if ($table_name === '') {
            return ($this->db->db_debug) ? $this->db->display_error('db_table_name_required') : false;
        }

        $query = $this->_drop_table($this->db->dbprefix . $table_name, $if_exists);
        if ($query === false) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
        } elseif ($query === true) {
            return true;
        }

        $query = $this->db->query($query);

        // Update table list cache
        if ($query && !empty($this->db->data_cache['table_names'])) {
            $key = array_search(strtolower($this->db->dbprefix . $table_name), array_map('strtolower', $this->db->data_cache['table_names']), true);
            if ($key !== false) {
                unset($this->db->data_cache['table_names'][$key]);
            }
        }

        return $query;
    }

    // --------------------------------------------------------------------

    /**
     * Drop Table
     *
     * Generates a platform-specific DROP TABLE string
     *
     * @param string $table Table name
     * @param bool $if_exists Whether to add an IF EXISTS condition
     * @return    string
     */
    protected function _drop_table($table, $if_exists)
    {
        $sql = 'DROP TABLE';

        if ($if_exists) {
            if ($this->_drop_table_if === false) {
                if (!$this->db->table_exists($table)) {
                    return true;
                }
            } else {
                $sql = sprintf($this->_drop_table_if, $this->db->escape_identifiers($table));
            }
        }

        return $sql . ' ' . $this->db->escape_identifiers($table);
    }

    // --------------------------------------------------------------------

    /**
     * Rename Table
     *
     * @param string $table_name Old table name
     * @param string $new_table_name New table name
     * @return    bool
     */
    public function rename_table($table_name, $new_table_name)
    {
        if ($table_name === '' or $new_table_name === '') {
            show_error('A table name is required for that operation.');
            return false;
        } elseif ($this->_rename_table === false) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
        }

        $result = $this->db->query(
            sprintf(
                $this->_rename_table,
                $this->db->escape_identifiers($this->db->dbprefix . $table_name),
                $this->db->escape_identifiers($this->db->dbprefix . $new_table_name)
            )
        );

        if ($result && !empty($this->db->data_cache['table_names'])) {
            $key = array_search(strtolower($this->db->dbprefix . $table_name), array_map('strtolower', $this->db->data_cache['table_names']), true);
            if ($key !== false) {
                $this->db->data_cache['table_names'][$key] = $this->db->dbprefix . $new_table_name;
            }
        }

        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * Column Add
     *
     * @param string $table Table name
     * @param array $field Column definition
     * @param string $_after Column for AFTER clause (deprecated)
     * @return    bool
     * @todo    Remove deprecated $_after option in 3.1+
     */
    public function add_column($table = '', $field = [], $_after = null)
    {
        if ($table === '') {
            show_error('A table name is required for that operation.');
        }

        // Work-around for literal column definitions
        if (!is_array($field)) {
            $field = [$field];
        }

        foreach (array_keys($field) as $k) {
            // Backwards-compatibility work-around for MySQL/CUBRID AFTER clause (remove in 3.1+)
            if ($_after !== null && is_array($field[$k]) && !isset($field[$k]['after'])) {
                $field[$k]['after'] = $_after;
            }

            $this->add_field([$k => $field[$k]]);
        }

        $sqls = $this->_alter_table('ADD', $this->db->dbprefix . $table, $this->_process_fields_custom());
        $this->_reset();
        if ($sqls === false) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
        }

        for ($i = 0, $c = count($sqls); $i < $c; $i++) {
            if ($this->db->query($sqls[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     *
     * @param array $column
     * @return    self
     */
    public function id($column = 'id'): self
    {
        return $this->bigIncrements($column);
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table.
     *
     * @param string $column
     * @return self
     */
    public function increments(string $column): self
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table.
     *
     * @param string $column
     * @return self
     */
    public function integerIncrements(string $column): self
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Create a new auto-incrementing tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @return self
     */
    public function tinyIncrements(string $column): self
    {
        return $this->unsignedTinyInteger($column, true);
    }

    /**
     * Create a new auto-incrementing small integer (2-byte) column on the table.
     *
     * @param string $column
     * @return self
     */
    public function smallIncrements(string $column): self
    {
        return $this->unsignedSmallInteger($column, true);
    }

    /**
     * Create a new auto-incrementing medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @return self
     */
    public function mediumIncrements(string $column): self
    {
        return $this->unsignedMediumInteger($column, true);
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     *
     * @param string $column
     * @return self
     */
    public function bigIncrements(string $column): self
    {
        return $this->unsignedBigInteger($column, true);
    }

    /**
     * Create a new char column on the table.
     *
     * @param string $column
     * @param int|null $length
     * @return self
     */
    public function char(string $column, int $length = null): self
    {
        $length = $length ?: self::$defaultStringLength;

        $attributes = [
            'type' => 'CHAR',
            'constraint' => $length,
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new string column on the table.
     *
     * @param string $column
     * @param int|null $length
     * @return self
     */
    public function string(string $column, int $length = null): self
    {
        $length = $length ?: self::$defaultStringLength;

        $attributes = [
            'type' => 'VARCHAR',
            'constraint' => $length,
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new tiny text column on the table.
     *
     * @param string $column
     * @return self
     */
    public function tinyText(string $column): self
    {
        $attributes = [
            'type' => 'TINYTEXT',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new text column on the table.
     *
     * @param string $column
     * @return self
     */
    public function text(string $column): self
    {
        $attributes = [
            'type' => 'TEXT',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new medium text column on the table.
     *
     * @param string $column
     * @return self
     */
    public function mediumText(string $column): self
    {
        $attributes = [
            'type' => 'MEDIUMTEXT',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new long text column on the table.
     *
     * @param string $column
     * @return self
     */
    public function longText(string $column): self
    {
        $attributes = [
            'type' => 'LONGTEXT',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new integer (4-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return self
     */
    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        $attributes = [
            'type' => 'INT',
            'unsigned' => $unsigned,
            'auto_increment' => $autoIncrement,
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        if ($autoIncrement) {
            $this->add_key($column, true);
        }

        return $this;
    }

    /**
     * Create a new tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return self
     */
    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        $attributes = [
            'type' => 'TINYINT',
            'unsigned' => $unsigned,
            'auto_increment' => $autoIncrement,
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        if ($autoIncrement) {
            $this->add_key($column, true);
        }

        return $this;
    }

    /**
     * Create a new small integer (2-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return self
     */
    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        $attributes = [
            'type' => 'SMALLINT',
            'unsigned' => $unsigned,
            'auto_increment' => $autoIncrement,
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        if ($autoIncrement) {
            $this->add_key($column, true);
        }

        return $this;
    }

    /**
     * Create a new medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return self
     */
    public function mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        $attributes = [
            'type' => 'MEDIUMINT',
            'unsigned' => $unsigned,
            'auto_increment' => $autoIncrement,
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        if ($autoIncrement) {
            $this->add_key($column, true);
        }

        return $this;
    }

    /**
     * Create a new big integer (8-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return    self
     */
    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        $attributes = [
            'type' => 'BIGINT',
            'unsigned' => $unsigned,
            'auto_increment' => $autoIncrement,
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        if ($autoIncrement) {
            $this->primary($column);
        }

        return $this;
    }

    /**
     * Create a new unsigned integer (4-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return self
     */
    public function unsignedInteger(string $column, bool $autoIncrement = false): self
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return self
     */
    public function unsignedTinyInteger(string $column, bool $autoIncrement = false): self
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned small integer (2-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return self
     */
    public function unsignedSmallInteger(string $column, bool $autoIncrement = false): self
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return self
     */
    public function unsignedMediumInteger(string $column, bool $autoIncrement = false): self
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return self
     */
    public function unsignedBigInteger(string $column, bool $autoIncrement = false): self
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned foreign ID column on the table.
     *
     * @param string $column
     * @return self
     */
    public function foreignId(string $column): self
    {
        return $this->bigInteger($column)->unsigned();
    }

    /**
     * Create a new float column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @param bool $unsigned
     * @return self
     */
    public function float(string $column, int $total = 8, int $places = 2, bool $unsigned = false): self
    {
        $constraint = $total && $places ? '' . $total . ',' . $places . '' : '';

        $attributes = [
            'type' => 'FLOAT',
            'constraint' => $constraint,
            'unsigned' => $unsigned,
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new double column on the table.
     *
     * @param string $column
     * @param int|null $total
     * @param int|null $places
     * @param bool $unsigned
     * @return self
     */
    public function double(string $column, int $total = null, int $places = null, bool $unsigned = false): self
    {
        $constraint = $total && $places ? '' . $total . ',' . $places . '' : '';

        $attributes = [
            'type' => 'FLOAT',
            'constraint' => $constraint,
            'unsigned' => $unsigned,
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new decimal column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @param bool $unsigned
     * @return self
     */
    public function decimal(string $column, int $total = 8, int $places = 2, bool $unsigned = false): self
    {
        $constraint = $total && $places ? '' . $total . ',' . $places . '' : '';

        $attributes = [
            'type' => 'DECIMAL',
            'constraint' => $constraint,
            'unsigned' => $unsigned,
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new unsigned float column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return self
     */
    public function unsignedFloat(string $column, int $total = 8, int $places = 2): self
    {
        return $this->float($column, $total, $places, true);
    }

    /**
     * Create a new unsigned double column on the table.
     *
     * @param string $column
     * @param int|null $total
     * @param int|null $places
     * @return self
     */
    public function unsignedDouble(string $column, int $total = null, int $places = null): self
    {
        return $this->double($column, $total, $places, true);
    }

    /**
     * Create a new unsigned decimal column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return self
     */
    public function unsignedDecimal(string $column, int $total = 8, int $places = 2): self
    {
        return $this->decimal($column, $total, $places, true);
    }

    /**
     * Create a new boolean column on the table.
     *
     * @param string $column
     * @return self
     */
    public function boolean(string $column): self
    {
        $attributes = [
            'type' => 'TINYINT',
            'constraint' => '1',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new enum column on the table.
     *
     * @param string $column
     * @param \Elegant\Support\Collection|array $allowed
     * @return self
     */
    public function enum(string $column, $allowed): self
    {
        $allowed = Arr::join($this->extractAllowedValues(
            $allowed instanceof \Elegant\Support\Collection ? $allowed->values()->toArray() : $allowed
        ), ',');

        $attributes = [
            'type' => 'ENUM(' . $allowed . ')',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new enum column on the table.
     *
     * @param string $column
     * @param array $allowed
     * @return self
     */
    public function set(string $column, array $allowed): self
    {
        $allowed = Arr::join($this->extractAllowedValues($allowed), ',');

        $attributes = [
            'type' => 'SET(' . $allowed . ')',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new json column on the table.
     *
     * @param string $column
     * @return self
     */
    public function json(string $column): self
    {
        $attributes = [
            'type' => 'JSON',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new date column on the table.
     *
     * @param string $column
     * @return self
     */
    public function date(string $column): self
    {
        $attributes = [
            'type' => 'DATE',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new date-time column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return self
     */
    public function dateTime(string $column, int $precision = 0): self
    {
        $attributes = [
            'type' => $precision ? 'DATETIME(' . $precision . ')' : 'DATETIME',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new time column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return self
     */
    public function time(string $column, int $precision = 0): self
    {
        $attributes = [
            'type' => $precision ? 'TIME(' . $precision . ')' : 'TIME',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new timestamp column on the table.
     *
     * @param string $column
     * @param int $precision
     * @param bool $not_escape_string
     * @return self
     */
    public function timestamp(string $column, int $precision = 0, bool $not_escape_string = false): self
    {
        $attributes = [
            'type' => $precision ? 'TIMESTAMP(' . $precision . ')' : 'TIMESTAMP',
            'null' => false,
            'not_escape_string' => $not_escape_string,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Add nullable creation and update timestamps to the table.
     *
     * @param int $precision
     * @return void
     */
    public function timestamps(int $precision = 0)
    {
        $this->timestamp('created_at', $precision, true)->nullable()->useCurrent()->comment(trans('generic.column_created_at'));
        $this->timestamp('updated_at', $precision, true)->nullable()->useCurrentOnUpdate()->comment(trans('generic.column_updated_at'));
    }

    /**
     * Add a "deleted at" timestamp for the table.
     *
     * @param string $column
     * @param int $precision
     * @return void
     */
    public function softDeletes(string $column = 'deleted_at', int $precision = 0)
    {
        $this->timestamp($column, $precision)->nullable()->comment(trans('generic.column_deleted_at'));
        $this->add_index($column);
    }

    /**
     * Create a new year column on the table.
     *
     * @param string $column
     * @return self
     */
    public function year(string $column): self
    {
        $attributes = [
            'type' => 'YEAR',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new binary column on the table.
     *
     * @param string $column
     * @return self
     */
    public function binary(string $column): self
    {
        $attributes = [
            'type' => 'BLOB',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new medium binary column on the table.
     *
     * @param string $column
     * @return self
     */
    public function mediumBinary(string $column): self
    {
        $attributes = [
            'type' => 'MEDIUMBLOB',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new long binary column on the table.
     *
     * @param string $column
     * @return self
     */
    public function longBinary(string $column): self
    {
        $attributes = [
            'type' => 'LONGBLOB',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new uuid column on the table.
     *
     * @param string $column
     * @return self
     */
    public function uuid(string $column): self
    {
        return $this->char($column, 36);
    }

    /**
     * Create a new IP address column on the table.
     *
     * @param string $column
     * @return self
     */
    public function ipAddress(string $column): self
    {
        return $this->string($column, 45);
    }

    /**
     * Create a new MAC address column on the table.
     *
     * @param string $column
     * @return self
     */
    public function macAddress(string $column): self
    {
        return $this->string($column, 17);
    }

    /**
     * Create a new geometry column on the table.
     *
     * @param string $column
     * @return self
     */
    public function geometry(string $column): self
    {
        $attributes = [
            'type' => 'GEOMETRY',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new geometry column on the table.
     *
     * @param string $column
     * @return self
     */
    public function point(string $column): self
    {
        $attributes = [
            'type' => 'POINT',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new linestring column on the table.
     *
     * @param string $column
     * @return self
     */
    public function lineString(string $column): self
    {
        $attributes = [
            'type' => 'LINESTRING',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Create a new polygon column on the table.
     *
     * @param string $column
     * @return self
     */
    public function polygon(string $column): self
    {
        $attributes = [
            'type' => 'POLYGON',
            'null' => false,
        ];

        $this->add_field([$column => $attributes]);

        return $this;
    }

    /**
     * Add the proper columns for a polymorphic table.
     *
     * @param string $name
     * @param bool $index
     * @return void
     */
    public function morphs(string $name, bool $index = true)
    {
        $this->numericMorphs($name, $index);
    }

    /**
     * Add nullable columns for a polymorphic table.
     *
     * @param string $name
     * @param bool $index
     * @return void
     */
    public function nullableMorphs(string $name, bool $index = true)
    {
        $this->nullableNumericMorphs($name, $index);
    }

    /**
     * Add the proper columns for a polymorphic table using numeric IDs (incremental).
     *
     * @param string $name
     * @param bool $index
     * @return void
     */
    public function numericMorphs(string $name, bool $index)
    {
        $this->string("{$name}_type");

        $this->unsignedBigInteger("{$name}_id");

        if($index) {
            $this->add_index(["{$name}_type", "{$name}_id"]);
        }
    }

    /**
     * Add nullable columns for a polymorphic table using numeric IDs (incremental).
     *
     * @param string $name
     * @param bool $index
     * @return void
     */
    public function nullableNumericMorphs(string $name, bool $index)
    {
        $this->string("{$name}_type")->nullable();

        $this->unsignedBigInteger("{$name}_id")->nullable();

        if($index) {
            $this->add_index(["{$name}_type", "{$name}_id"]);
        }
    }

    /**
     * Adds the `remember_token` column to the table.
     *
     * @return void
     */
    public function rememberToken()
    {
        $this->string('remember_token', 100)->nullable();
    }


    /**
     * Specify a "default" value for the column
     *
     * @param $value
     * @return self
     */
    public function default($value): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'default' => $value || $value == 0 ? ($this->db->dbdriver === 'sqlite3' ? null : $value) : null
        ]);

        return $this;
    }

    /**
     * Add autoincrement to the column
     *
     * @param bool $value
     * @return self
     */
    public function autoIncrement(bool $value = true): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'auto_increment' => $value,
        ]);

        return $this;
    }

    /**
     * Allow NULL values to be inserted into the column
     *
     * @param bool $value
     * @return self
     */
    public function nullable(bool $value = true): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'null' => $value,
            'default' => null
        ]);

        return $this;
    }

    /**
     * Add a comment to the column (MySQL/PostgreSQL)
     *
     * @param string $comment
     * @return self
     */
    public function comment(string $comment): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'comment' => $comment || $comment == 0 ? ($this->db->dbdriver === 'sqlite3' ? null : $comment) : null
        ]);

        return $this;
    }

    /**
     * Add a unique index
     *
     * @param bool $value
     * @return self
     */
    public function unique(bool $value = true): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'unique' => $value,
        ]);

        return $this;
    }

    /**
     * Add an unsigned index
     *
     * @param bool $value
     * @return self
     */
    public function unsigned(bool $value = true): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'unsigned' => $value,
        ]);

        return $this;
    }

    /**
     * Specify an index for the table.
     *
     * @param null $table
     * @param array|string $key
     * @return bool|self
     */
    public function index($table = null, $key = [])
    {
        if(!is_null($table)) {
            $key = Arr::wrap($key);

            if (empty($key)) {
                show_error('A column name is required for that operation.');
            }

            foreach ($key as $one) {
                $sql = $this->alterIndex('ADD', $this->db->dbprefix . $table, $one);

                $this->db->query($sql);
            }

            return true;
        }

        $this->keys[] = $this->last_field;

        return $this;
    }

    /**
     * Set the TIMESTAMP column to use CURRENT_TIMESTAMP as default value
     *
     * @return self
     */
    public function useCurrent(): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'default' => 'CURRENT_TIMESTAMP',
        ]);

        return $this;
    }

    /**
     * Set the TIMESTAMP column to use CURRENT_TIMESTAMP when updating (MySQL)
     *
     * @return self
     */
    public function useCurrentOnUpdate(): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'default' => $this->db->dbdriver === 'sqlite3'
                ? 'CURRENT_TIMESTAMP'
                : 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ]);

        return $this;
    }

    /**
     * Create a foreign key constraint on this column referencing the "id" column of the conventionally related table.
     *
     * @param string|null $table
     * @param string $column
     * @return self
     */
    public function constrained(string $table = null, string $column = 'id'): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'field' => $this->last_field,
        ]);

        return $this->references($column)->on($table ?? Str::of($this->last_field)->beforeLast('_')->plural());
    }

    /**
     * Specify which column this foreign ID references on another table.
     *
     * @param string $column
     * @return $this
     */
    public function references(string $column): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'foreign_field' => $column,
        ]);

        return $this;
    }

    /**
     * Specify the referenced table
     *
     * @param \Elegant\Support\Stringable|string $table
     * @return self
     */
    public function on($table): self
    {
        $this->fields[$this->last_field] = array_merge($this->fields[$this->last_field], [
            'foreign_table' => $table instanceof Stringable ? $table->toString() : $table,
        ]);

        $foreignKey = $this->fields[$this->last_field];

        Arr::forget($foreignKey, [
            'type',
            'unsigned',
            'auto_increment',
            'null',
            'default',
        ]);

        Arr::forget($this->fields[$this->last_field], [
            'field',
            'foreign_field',
            'foreign_table',
        ]);

        $this->add_foreign_key($foreignKey);

        return $this;
    }

    /**
     * Add an ON UPDATE action
     *
     * @param string $action
     * @return self
     */
    public function onUpdate(string $action): self
    {
        $key = count($this->foreign_keys) - 1;

        $this->foreign_keys[$key] = array_merge($this->foreign_keys[$key], [
            'update' => $action,
        ]);

        return $this;
    }

    /**
     * Add an ON DELETE action
     *
     * @param string $action
     * @return self
     */
    public function onDelete(string $action): self
    {
        $key = count($this->foreign_keys) - 1;

        $this->foreign_keys[$key] = array_merge($this->foreign_keys[$key], [
            'delete' => $action,
        ]);

        return $this;
    }

    /**
     * Indicate that updates should cascade.
     *
     * @return self
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('cascade');
    }

    /**
     * Indicate that deletes should cascade.
     *
     * @return self
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('cascade');
    }

    /**
     * Indicate that deletes should be restricted.
     *
     * @return self
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete('restrict');
    }

    /**
     * Indicate that deletes should set the foreign key value to null.
     *
     * @return self
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('set null');
    }

    // --------------------------------------------------------------------

    /**
     * Column Drop
     *
     * @param string $table Table name
     * @param array|mixed $columns Columns
     * @return    bool
     */
    public function dropColumn(string $table = '', $columns): bool
    {
        if (!is_array($columns)) {
            $columns = func_get_args();

            unset($columns[0]);

            $columns = array_values($columns);
        }

        if ($table === '') {
            show_error('A table name is required for that operation.');
        }

        if (empty($columns) || $columns[0] === '') {
            show_error('A column name is required for that operation.');
        }

        if ($this->db->dbdriver === 'sqlite3') {
            return $this->sqliteDropColumns($table, $columns);
        }

        $sql = $this->_alter_table('DROP', $this->db->dbprefix . $table, $columns);
        if ($sql === false) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
        }

        return $this->db->query($sql);
    }

    /**
     * SQLite before 3.35 cannot ALTER TABLE ... DROP COLUMN.
     *
     * @param string $table
     * @param array $columns
     * @return bool
     */
    protected function sqliteDropColumns(string $table, array $columns): bool
    {
        $prefixed = $this->db->dbprefix . $table;
        $quoted = $this->db->escape_identifiers($prefixed);
        $drop = [];

        foreach ($columns as $column) {
            $drop[strtolower((string) $column)] = (string) $column;
        }

        $info = $this->db->query('PRAGMA table_info(' . $quoted . ')');
        if ($info === false) {
            return false;
        }

        $fields = $info->result_array();
        $keep = [];

        foreach ($fields as $field) {
            if (! isset($drop[strtolower($field['name'])])) {
                $keep[] = $field;
            }
        }

        if ($keep === [] || count($keep) === count($fields)) {
            return true;
        }

        if (version_compare((string) $this->db->version(), '3.35.0', '>=')) {
            foreach ($drop as $column) {
                $ok = $this->db->query(
                    'ALTER TABLE ' . $quoted . ' DROP COLUMN ' . $this->db->escape_identifiers($column)
                );

                if ($ok === false) {
                    return $this->sqliteRebuildTableWithoutColumns($prefixed, $keep);
                }
            }

            return true;
        }

        return $this->sqliteRebuildTableWithoutColumns($prefixed, $keep);
    }

    /**
     * Recreate a SQLite table without the dropped columns.
     *
     * @param string $table Prefixed table name
     * @param array $keepFields PRAGMA table_info rows to keep
     * @return bool
     */
    protected function sqliteRebuildTableWithoutColumns(string $table, array $keepFields): bool
    {
        $quoted = $this->db->escape_identifiers($table);
        $temp = $table . '__ci_new';
        $quotedTemp = $this->db->escape_identifiers($temp);

        $createRow = $this->db->query(
            'SELECT sql FROM sqlite_master WHERE type = ' . $this->db->escape('table')
            . ' AND name = ' . $this->db->escape($table)
        )->row();
        $autoincrement = is_object($createRow) && stripos((string) $createRow->sql, 'AUTOINCREMENT') !== false;

        $primary = [];
        $keepNames = [];
        $keepLookup = [];

        foreach ($keepFields as $field) {
            $keepNames[] = $this->db->escape_identifiers($field['name']);
            $keepLookup[strtolower($field['name'])] = true;

            if ((int) $field['pk'] > 0) {
                $primary[(int) $field['pk']] = $field;
            }
        }

        ksort($primary);

        $columnSql = [];
        $soleIntegerPk = count($primary) === 1 && stripos((string) reset($primary)['type'], 'INT') !== false;

        foreach ($keepFields as $field) {
            $name = $this->db->escape_identifiers($field['name']);
            $type = $field['type'] !== '' ? $field['type'] : 'TEXT';
            $isIntegerPk = $soleIntegerPk && isset($primary[1]) && $primary[1]['name'] === $field['name'];

            if ($isIntegerPk) {
                $columnSql[] = $name . ' INTEGER PRIMARY KEY' . ($autoincrement ? ' AUTOINCREMENT' : '');
                continue;
            }

            $definition = $name . ' ' . $type;

            if ((int) $field['notnull'] === 1 && (int) $field['pk'] === 0) {
                $definition .= ' NOT NULL';
            }

            if ($field['dflt_value'] !== null && $field['dflt_value'] !== '') {
                $definition .= ' DEFAULT ' . $field['dflt_value'];
            }

            $columnSql[] = $definition;
        }

        if (count($primary) > 1) {
            $pkCols = [];
            foreach ($primary as $field) {
                $pkCols[] = $this->db->escape_identifiers($field['name']);
            }
            $columnSql[] = 'PRIMARY KEY (' . implode(', ', $pkCols) . ')';
        }

        $indexes = $this->db->query(
            'SELECT sql FROM sqlite_master WHERE type = ' . $this->db->escape('index')
            . ' AND tbl_name = ' . $this->db->escape($table)
            . ' AND sql IS NOT NULL'
        )->result();

        $this->db->query('PRAGMA foreign_keys = OFF');

        if ($this->db->query('CREATE TABLE ' . $quotedTemp . ' (' . implode(', ', $columnSql) . ')') === false) {
            $this->db->query('PRAGMA foreign_keys = ON');
            return false;
        }

        $select = implode(', ', $keepNames);
        if ($this->db->query('INSERT INTO ' . $quotedTemp . ' (' . $select . ') SELECT ' . $select . ' FROM ' . $quoted) === false) {
            $this->db->query('PRAGMA foreign_keys = ON');
            return false;
        }

        if ($this->db->query('DROP TABLE ' . $quoted) === false) {
            $this->db->query('PRAGMA foreign_keys = ON');
            return false;
        }

        if ($this->db->query('ALTER TABLE ' . $quotedTemp . ' RENAME TO ' . $quoted) === false) {
            $this->db->query('PRAGMA foreign_keys = ON');
            return false;
        }

        foreach ($indexes as $index) {
            $sql = isset($index->sql) ? (string) $index->sql : '';
            if ($sql === '' || $this->sqliteIndexReferencesMissingColumn($sql, $keepLookup)) {
                continue;
            }

            $this->db->query($sql);
        }

        $this->db->query('PRAGMA foreign_keys = ON');

        return true;
    }

    /**
     * @param string $sql
     * @param array<string, bool> $keepLookup
     * @return bool
     */
    protected function sqliteIndexReferencesMissingColumn(string $sql, array $keepLookup): bool
    {
        if (! preg_match('/\((.+)\)\s*$/', $sql, $matches)) {
            return false;
        }

        foreach (preg_split('/\s*,\s*/', $matches[1]) as $part) {
            $name = strtolower(trim($part, " \t\n\r\0\x0B\"'`[]"));
            $name = (string) preg_replace('/\s+(ASC|DESC)$/i', '', $name);

            if ($name !== '' && ! isset($keepLookup[$name])) {
                return true;
            }
        }

        return false;
    }

    public function foreign($table = '', $foreign_key = '')
    {
        if ($table === '') {
            show_error('A table name is required for that operation.');
        }

        if ($foreign_key === '') {
            show_error('A fk column name is required for that operation.');
        }

        $sql = $this->alterForeignKey('ADD', $this->db->dbprefix . $table, $foreign_key);

        if ($sql === false) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
        }

        return $this->db->query($sql);
    }

    public function dropForeign($table = '', $foreign_key = '')
    {
        if ($table === '') {
            show_error('A table name is required for that operation.');
        }

        if ($foreign_key === '') {
            show_error('A fk column name is required for that operation.');
        }

        if ($this->db->dbdriver === 'sqlite3') {
            return true;
        }

        $sql = $this->alterForeignKey('DROP', $this->db->dbprefix . $table, $foreign_key);

        if ($sql === false) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
        }

        return $this->db->query($sql);
    }

    public function dropIndex($table = '', $key = [])
    {
        $key = Arr::wrap($key);

        if ($table === '') {
            show_error('A table name is required for that operation.');
        }

        if (empty($key)) {
            show_error('A column name is required for that operation.');
        }

        foreach ($key as $one) {
            $sql = $this->alterIndex('DROP', $this->db->dbprefix . $table, $one);

            if ($sql === false) {
                return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
            }

            $this->db->query($sql);

        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Column Modify
     *
     * @param string $table Table name
     * @param string $field Column definition
     * @return    bool
     */
    public function modify_column($table = '', $field = [])
    {
        if ($table === '') {
            show_error('A table name is required for that operation.');
        }

        // Work-around for literal column definitions
        if (!is_array($field)) {
            $field = [$field];
        }

        foreach (array_keys($field) as $k) {
            $this->add_field([$k => $field[$k]]);
        }

        if (count($this->fields) === 0) {
            show_error('Field information is required.');
        }

        $sqls = $this->_alter_table('CHANGE', $this->db->dbprefix . $table, $this->_process_fields());
        $this->_reset();
        if ($sqls === false) {
            return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : false;
        }

        for ($i = 0, $c = count($sqls); $i < $c; $i++) {
            if ($this->db->query($sqls[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * ALTER TABLE
     *
     * @param string $alter_type ALTER type
     * @param string $table Table name
     * @param mixed $field Column definition
     * @return    string|string[]
     */
    protected function _alter_table($alter_type, $table, $field)
    {
        $sql = 'ALTER TABLE ' . $this->db->escape_identifiers($table) . ' ';

        // DROP has everything it needs now.
        if ($alter_type === 'DROP') {
            $columns = $this->prefixArray('drop', $field);

            return $sql . implode(', ', $columns);
        }

        $sqls = [];
        for ($i = 0, $c = count($field), $sql .= $alter_type . ' COLUMN '; $i < $c; $i++) {
            $sqls[] = $sql
                . ($field[$i]['_literal'] !== false ? $field[$i]['_literal'] : $this->_process_column($field[$i]));
        }

        return $sqls;
    }

    protected function alterForeignKey($alter_type, $table, $field)
    {
        $sql = 'ALTER TABLE ' . $this->db->escape_identifiers($table) . ' ';

        if ($alter_type === 'DROP') {
            // return $sql . 'DROP FOREIGN KEY ' . $table . '_' . $field . '_foreign';
            return $sql . 'DROP FOREIGN KEY ' . $field;
        } else {
            $this->add_foreign_key($field);

            $sql .= 'ADD ' . $this->_process_foreign_keys_custom();

            return $sql;
        }
    }

    protected function alterIndex($alter_type, $table, $field)
    {
        if ($this->db->dbdriver === 'sqlite3') {
            $indexName = $table . '_' . $field;

            if ($alter_type === 'DROP') {
                return 'DROP INDEX IF EXISTS ' . $this->db->escape_identifiers($indexName);
            }

            return 'CREATE INDEX IF NOT EXISTS ' . $this->db->escape_identifiers($indexName)
                . ' ON ' . $this->db->escape_identifiers($table)
                . ' (' . $this->db->escape_identifiers($field) . ')';
        }

        $sql = 'ALTER TABLE ' . $this->db->escape_identifiers($table) . ' ';

        if ($alter_type === 'DROP') {
            return $sql . 'DROP INDEX ' . $field;
        } else {
            $this->add_index($field);

            $sql .= 'ADD INDEX(`' . $field . '`)';

            return $sql;
        }
    }

    /**
     * Add a prefix to an array of values.
     *
     * @param string $prefix
     * @param array $values
     * @return array
     */
    private function prefixArray(string $prefix, array $values): array
    {
        return array_map(function ($value) use ($prefix) {
            return $prefix . ' ' . $value;
        }, $values);
    }

    // --------------------------------------------------------------------

    /**
     * Process fields
     *
     * @param bool $create_table
     * @return    array
     */
    protected function _process_fields($create_table = false)
    {
        $fields = [];

        foreach ($this->fields as $key => $attributes) {
            if (is_int($key) && !is_array($attributes)) {
                $fields[] = ['_literal' => $attributes];
                continue;
            }

            $attributes = array_change_key_case($attributes, CASE_UPPER);

            if ($create_table === true && empty($attributes['TYPE'])) {
                continue;
            }

            $this->normalizeSqliteColumnType($attributes);

            isset($attributes['TYPE']) && $this->_attr_type($attributes);

            $field = [
                'name' => $key,
                'new_name' => isset($attributes['NAME']) ? $attributes['NAME'] : null,
                'type' => isset($attributes['TYPE']) ? $attributes['TYPE'] : null,
                'length' => '',
                'unsigned' => '',
                'null' => '',
                'unique' => '',
                'default' => '',
                'auto_increment' => '',
                '_literal' => false
            ];

            isset($attributes['TYPE']) && $this->_attr_unsigned($attributes, $field);

            $this->_attr_default($attributes, $field);

            if (isset($attributes['NULL'])) {
                if ($attributes['NULL'] === true) {
                    $field['null'] = empty($this->_null) ? '' : ' ' . $this->_null;
                } elseif ($create_table === true) {
                    $field['null'] = ' NOT NULL';
                }
            }

            $this->_attr_auto_increment($attributes, $field);
            $this->_attr_unique($attributes, $field);

            if (isset($attributes['COMMENT'])) {
                $field['comment'] = $this->db->escape($attributes['COMMENT']);
            }

            if (isset($attributes['TYPE']) && !empty($attributes['CONSTRAINT'])) {
                switch (strtoupper($attributes['TYPE'])) {
                    case 'ENUM':
                    case 'SET':
                        $attributes['CONSTRAINT'] = $this->db->escape($attributes['CONSTRAINT']);
                    // no break
                    default:
                        $field['length'] = is_array($attributes['CONSTRAINT'])
                            ? "('" . implode("','", $attributes['CONSTRAINT']) . "')"
                            : '(' . $attributes['CONSTRAINT'] . ')';
                        break;
                }
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Process fields custom
     *
     * @param bool $create_table
     * @return    array
     */
    protected function _process_fields_custom($create_table = false)
    {
        $fields = [];

        foreach ($this->fields as $key => $attributes) {
            if (is_int($key) && !is_array($attributes)) {
                $fields[] = ['_literal' => $attributes];
                continue;
            }

            $attributes = array_change_key_case($attributes, CASE_UPPER);

            if ($create_table === true && empty($attributes['TYPE'])) {
                continue;
            }

            $this->normalizeSqliteColumnType($attributes);

            isset($attributes['TYPE']) && $this->_attr_type($attributes);

            $field = [
                'name' => $key,
                'new_name' => isset($attributes['NAME']) ? $attributes['NAME'] : null,
                'type' => isset($attributes['TYPE']) ? $attributes['TYPE'] : null,
                'length' => '',
                'unsigned' => '',
                'null' => null,
                'unique' => '',
                'default' => '',
                'auto_increment' => '',
                '_literal' => false
            ];

            isset($attributes['TYPE']) && $this->_attr_unsigned($attributes, $field);

            if ($create_table === false) {
                if (isset($attributes['AFTER'])) {
                    $field['after'] = $attributes['AFTER'];
                } elseif (isset($attributes['FIRST'])) {
                    $field['first'] = (bool)$attributes['FIRST'];
                }
            }

            $this->_attr_default($attributes, $field);

            if (isset($attributes['NULL'])) {
                if ($attributes['NULL'] === true) {
                    $field['null'] = empty($this->_null) ? '' : ' ' . $this->_null;
                } else {
                    $field['null'] = ' NOT NULL';
                }
            } elseif ($create_table === true) {
                $field['null'] = ' NOT NULL';
            }

            $this->_attr_auto_increment($attributes, $field);
            $this->_attr_unique($attributes, $field);

            if (isset($attributes['COMMENT'])) {
                $field['comment'] = $this->db->escape($attributes['COMMENT']);
            }

            if (isset($attributes['TYPE']) && !empty($attributes['CONSTRAINT'])) {
                switch (strtoupper($attributes['TYPE'])) {
                    case 'ENUM':
                    case 'SET':
                        $attributes['CONSTRAINT'] = $this->db->escape($attributes['CONSTRAINT']);
                    default:
                        $field['length'] = is_array($attributes['CONSTRAINT'])
                            ? '(' . implode(',', $attributes['CONSTRAINT']) . ')'
                            : '(' . $attributes['CONSTRAINT'] . ')';
                        break;
                }
            }

            $fields[] = $field;
        }

        return $fields;
    }

    // --------------------------------------------------------------------

    /**
     * Process column
     *
     * @param array $field
     * @return    string
     */
    protected function _process_column($field)
    {
        return $this->db->escape_identifiers($field['name'])
            . ' ' . $field['type'] . $field['length']
            . $field['unsigned']
            . $field['default']
            . $field['null']
            . $field['auto_increment']
            . $field['unique'];
    }

    // --------------------------------------------------------------------

    /**
     * Field attribute TYPE
     *
     * Performs a data type mapping between different databases.
     *
     * @param array    &$attributes
     * @return    void
     */
    protected function _attr_type(&$attributes)
    {
        // Usually overriden by drivers
    }

    /**
     * SQLite accepts arbitrary type names, but ENUM()/SET()/JSON are not portable.
     *
     * @param array<string, mixed> $attributes
     * @return void
     */
    protected function normalizeSqliteColumnType(array&$attributes): void
    {
        if ($this->db->dbdriver !== 'sqlite3' || empty($attributes['TYPE'])) {
            return;
        }

        $type = strtoupper((string) $attributes['TYPE']);

        if (strpos($type, 'ENUM') === 0 || strpos($type, 'SET') === 0 || $type === 'JSON') {
            $attributes['TYPE'] = 'TEXT';
            unset($attributes['CONSTRAINT']);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Field attribute UNSIGNED
     *
     * Depending on the _unsigned property value:
     *
     *    - TRUE will always set $field['unsigned'] to 'UNSIGNED'
     *    - FALSE will always set $field['unsigned'] to ''
     *    - array(TYPE) will set $field['unsigned'] to 'UNSIGNED',
     *        if $attributes['TYPE'] is found in the array
     *    - array(TYPE => UTYPE) will change $field['type'],
     *        from TYPE to UTYPE in case of a match
     *
     * @param array    &$attributes
     * @param array    &$field
     * @return    void
     */
    protected function _attr_unsigned(&$attributes, &$field)
    {
        if (empty($attributes['UNSIGNED']) or $attributes['UNSIGNED'] !== true) {
            return;
        }

        // Reset the attribute in order to avoid issues if we do type conversion
        $attributes['UNSIGNED'] = false;

        if (is_array($this->_unsigned)) {
            foreach (array_keys($this->_unsigned) as $key) {
                if (is_int($key) && strcasecmp($attributes['TYPE'], $this->_unsigned[$key]) === 0) {
                    $field['unsigned'] = ' UNSIGNED';
                    return;
                } elseif (is_string($key) && strcasecmp($attributes['TYPE'], $key) === 0) {
                    $field['type'] = $key;
                    return;
                }
            }

            return;
        }

        $field['unsigned'] = ($this->_unsigned === true) ? ' UNSIGNED' : '';
    }

    // --------------------------------------------------------------------

    /**
     * Field attribute DEFAULT
     *
     * @param array    &$attributes
     * @param array    &$field
     * @return    void
     */
    protected function _attr_default(&$attributes, &$field)
    {
        if ($this->_default === false) {
            return;
        }

        if (array_key_exists('DEFAULT', $attributes)) {
            if ($attributes['DEFAULT'] === null) {
                $field['default'] = empty($this->_null) ? '' : $this->_default . $this->_null;

                // Override the NULL attribute if that's our default
                $attributes['NULL'] = null;
                $field['null'] = empty($this->_null) ? '' : ' ' . $this->_null;
            } elseif (isset($attributes['NOT_ESCAPE_STRING']) && $attributes['NOT_ESCAPE_STRING'] === true) {
                $field['default'] = $this->_default . $attributes['DEFAULT'];
            } else {
                $field['default'] = $this->_default . $this->db->escape($attributes['DEFAULT']);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Field attribute UNIQUE
     *
     * @param array    &$attributes
     * @param array    &$field
     * @return    void
     */
    protected function _attr_unique(&$attributes, &$field)
    {
        if (!empty($attributes['UNIQUE']) && $attributes['UNIQUE'] === true) {
            $field['unique'] = ' UNIQUE';
        }
    }

    // --------------------------------------------------------------------

    /**
     * Field attribute AUTO_INCREMENT
     *
     * @param array    &$attributes
     * @param array    &$field
     * @return    void
     */
    protected function _attr_auto_increment(&$attributes, &$field)
    {
        if (!empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === true && stripos($field['type'], 'int') !== false) {
            $field['auto_increment'] = ' AUTO_INCREMENT';
        }
    }

    // --------------------------------------------------------------------

    /**
     * Process primary keys
     *
     * @param string $table Table name
     * @return    string
     */
    protected function _process_primary_keys($table)
    {
        $sql = '';

        for ($i = 0, $c = count($this->primary_keys); $i < $c; $i++) {
            if (!isset($this->fields[$this->primary_keys[$i]])) {
                unset($this->primary_keys[$i]);
            }
        }

        if (count($this->primary_keys) > 0) {
            $sql .= ",\n\tCONSTRAINT " . $this->db->escape_identifiers('pk_' . $table)
                . ' PRIMARY KEY(' . implode(', ', $this->db->escape_identifiers($this->primary_keys)) . ')';
        }

        return $sql;
    }

    // --------------------------------------------------------------------

    /**
     * Process Adding of Foreign Keys
     * Called by _create_table()
     *
     * @return string SQL statement component
     */
    protected function _process_foreign_keys()
    {
        if ($this->db->dbdriver === 'sqlite3') {
            return '';
        }

        $sql = '';

        // If we have any foreign keys to process...
        if (count($this->foreign_keys) > 0) {
            foreach ($this->foreign_keys as $key => $foreign_key) {
                //  If this is an array, construct the statement
                if (is_array($foreign_key)) {
                    // Initialize update and delete actions with default values
                    $array_init = ['delete' => 'NO ACTION', 'update' => 'NO ACTION'];

                    // Add the default values if needed
                    $foreign_key = array_merge($array_init, $foreign_key);

                    // Extract the array elements into variables for simplicity
                    extract($foreign_key);

                    // Construct the SQL to add the foreign constraint
                    $sql .= ", \n\tCONSTRAINT FOREIGN KEY "
                        . $this->db->escape_identifiers($key == 0 ? 'fk_' . $foreign_table . '_' . $foreign_field : 'fk' . $key . '_' . $foreign_table . '_' . $foreign_field)
                        . ' (' . $this->db->escape_identifiers($field) . ') '
                        . ' REFERENCES ' . $this->db->dbprefix . $this->db->escape_identifiers($foreign_table
                            . '(' . $this->db->escape_identifiers($foreign_field) . ')')
                        . ' ON DELETE ' . strtoupper($delete) . ' ON UPDATE ' . strtoupper($update) . ' ';
                } else {
                    // Otherwise add the string statement as-is
                    $sql .= ', ' . $foreign_key . ' ';
                }
            }
        }

        return $sql;
    }

    protected function _process_foreign_keys_custom()
    {
        $sql = '';

        // If we have any foreign keys to process...
        if (count($this->foreign_keys) > 0) {
            foreach ($this->foreign_keys as $key => $foreign_key) {
                //  If this is an array, construct the statement
                if (is_array($foreign_key)) {
                    // Initialize update and delete actions with default values
                    $array_init = ['delete' => 'NO ACTION', 'update' => 'NO ACTION'];

                    // Add the default values if needed
                    $foreign_key = array_merge($array_init, $foreign_key);

                    // Extract the array elements into variables for simplicity
                    extract($foreign_key);

                    // Construct the SQL to add the foreign constraint
                    $sql .= "CONSTRAINT FOREIGN KEY "
                        . $this->db->escape_identifiers($key == 0 ? 'fk_' . $foreign_table . '_' . $foreign_field : 'fk' . $key . '_' . $foreign_table . '_' . $foreign_field)
                        . ' (' . $this->db->escape_identifiers($field) . ') '
                        . ' REFERENCES ' . $this->db->dbprefix . $this->db->escape_identifiers($foreign_table
                            . '(' . $this->db->escape_identifiers($foreign_field) . ')')
                        . ' ON DELETE ' . strtoupper($delete) . ' ON UPDATE ' . strtoupper($update) . ' ';
                } else {
                    // Otherwise add the string statement as-is
                    $sql .= $foreign_key . ' ';
                }
            }
        }

        return $sql;
    }

    // --------------------------------------------------------------------

    /**
     * Process indexes
     *
     * @param string $table
     * @return    string
     */
    protected function _process_indexes($table)
    {
        $table = $this->db->escape_identifiers($table);
        $sqls = [];

        for ($i = 0, $c = count($this->keys); $i < $c; $i++) {
            if (is_array($this->keys[$i])) {
                for ($i2 = 0, $c2 = count($this->keys[$i]); $i2 < $c2; $i2++) {
                    if (!isset($this->fields[$this->keys[$i][$i2]])) {
                        unset($this->keys[$i][$i2]);
                        continue;
                    }
                }
            } elseif (!isset($this->fields[$this->keys[$i]])) {
                unset($this->keys[$i]);
                continue;
            }

            is_array($this->keys[$i]) or $this->keys[$i] = [$this->keys[$i]];

            $indexName = implode('_', $this->keys[$i]);
            if ($this->db->dbdriver === 'sqlite3') {
                $indexName = trim($table, '`"') . '_' . $indexName;
            }

            $sqls[] = 'CREATE INDEX ' . ($this->db->dbdriver === 'sqlite3' ? 'IF NOT EXISTS ' : '')
                . $this->db->escape_identifiers($indexName)
                . ' ON ' . $this->db->escape_identifiers($table)
                . ' (' . implode(', ', $this->db->escape_identifiers($this->keys[$i])) . ');';
        }

        return $sqls;
    }

    // --------------------------------------------------------------------

    /**
     * Reset
     *
     * Resets table creation vars
     *
     * @return    void
     */
    protected function _reset()
    {
        $this->fields = $this->keys = $this->primary_keys = [];
    }

    /**
     * @param array $allowed
     * @return array
     */
    private function extractAllowedValues(array $allowed): array
    {
        if (!$allowed) {
            return [];
        }

        return collect($allowed)->map(function ($item) {
            return '\'' . $item . '\'';
        })->toArray();
    }
}

if (! class_exists('CI_DB_forge', false)) {
    class_alias(Forge::class, 'CI_DB_forge');
}
