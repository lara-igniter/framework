<?php

namespace Elegant\Database\Schema;

use Closure;

class Schema
{
    /**
     * The default string length for migrations.
     *
     * @var int
     */
    public static $defaultStringLength = 255;

    public static $types = array(
        'integer' => 'INT',
        'int' => 'INT',
        'bigint' => 'BIGINT',
        'decimal' => 'DECIMAL',
        'string' => 'VARCHAR',
        'varchar' => 'VARCHAR',
        'char' => 'CHAR',
        'text' => 'TEXT',
        'longtext' => 'LONGTEXT',
        'date' => 'DATE',
        'datetime' => 'DATETIME',
        'boolean' => 'TINYINT',
        'tinyint' => 'TINYINT'
    );

    public static function create($table_name, Closure $callback)
    {
        $table_definition = new Blueprint($table_name);

        $callback($table_definition);

        $table_definition->createTable();
    }

    public static function dropIfExist($table_name)
    {
        (new Blueprint($table_name))->dropTable(true);
    }

    static public function addColumn($table, $name, $type, $options = [], $after_column = '')
    {
        $column = [];

        if (isset(self::$types[strtolower($type)])) {
            $column = array('type' => self::$types[$type]);
        } elseif ($type == 'auto_increment_integer') {
            $column = array('type' => 'INT', 'unsigned' => TRUE, 'auto_increment' => TRUE);
        } elseif ($type == 'timestamps') {
            self::addColumn($table, 'created_at', 'datetime');
            self::addColumn($table, 'updated_at', 'datetime');

            return;
        }

        ci()->load->dbforge();

        ci()->dbforge->add_column($table, array($name => array_merge($column, $options)), $after_column);
    }

    public static function removeColumn($table, $name)
    {
        ci()->load->dbforge();

        ci()->dbforge->drop_column($table, $name);
    }

    static public function renameColumn($table, $name, $new_name)
    {
        ci()->load->dbforge();

        $field_data = ci()->db->field_data($table);
        $types = [];

        foreach ($field_data as $col) {
            $types[$col->name] = $col->type;
        }

        ci()->dbforge->modify_column($table, [$name => ['name' => $new_name, 'type' => $types[$name]]]);
    }

    static public function modifyColumn($table, $name, $type, $options = array())
    {
        $column = ['type' => self::$types[strtolower($type)]];

        ci()->load->dbforge();

        ci()->dbforge->modify_column($table, [$name => array_merge($column, $options)]);
    }
}
