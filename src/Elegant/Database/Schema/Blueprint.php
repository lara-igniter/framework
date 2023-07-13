<?php

namespace Elegant\Database\Schema;

use Elegant\Support\Traits\Macroable;

class Blueprint
{
    use Macroable;

    /**
     * The table the blueprint describes.
     *
     * @var string
     */
    protected $table = '';

    /**
     * The columns that should be added to the table.
     *
     * @var ColumnDefinition[]
     */
    protected $columns = [];

    /**
     * The keys that should be added to the table.
     *
     * @var array []
     */
    protected $keys = [];


    public function __construct($table)
    {
        $this->table = $table;
    }

    public function createTable()
    {
        ci()->load->dbforge();

        ci()->dbforge->add_field($this->getColumns());

        foreach ($this->getKeys() as $key => $primary) {
            ci()->dbforge->add_key($key, $primary);
        }

        ci()->dbforge->create_table($this->getTable());
    }

    public function dropTable($exists = false)
    {
        ci()->load->dbforge();

        ci()->dbforge->drop_table($this->getTable(), $exists);
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function id(string $column = 'id')
    {
        return $this->bigIncrements($column);
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function increments(string $column)
    {
        $this->primary($column);

        return $this->unsignedInteger($column, true);
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function integerIncrements(string $column)
    {
        $this->primary($column);

        return $this->unsignedInteger($column, true);
    }

    /**
     * Create a new auto-incrementing tiny integer (1-byte) column on the table
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function tinyIncrements(string $column)
    {
        $this->primary($column);

        return $this->unsignedTinyInteger($column, true);
    }

    /**
     * Create a new auto-incrementing small integer (2-byte) column on the table
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function smallIncrements(string $column)
    {
        $this->primary($column);

        return $this->unsignedSmallInteger($column, true);
    }

    /**
     * Create a new auto-incrementing medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function mediumIncrements(string $column)
    {
        $this->primary($column);

        return $this->unsignedMediumInteger($column, true);
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function bigIncrements(string $column)
    {
        $this->primary($column);

        return $this->unsignedBigInteger($column, true);
    }

    /**
     * Create a new char column on the table.
     *
     * @param string $column
     * @param int|null $length
     * @return ColumnDefinition
     */
    public function char(string $column, int $length = null)
    {
        $length = $length ?: Schema::$defaultStringLength;

        return $this->addColumn('CHAR', $column, [
            'constraint' => $length
        ]);
    }


    /**
     * Create a new string column on the table.
     *
     * @param string $column
     * @param int|null $length
     * @return ColumnDefinition
     */
    public function string(string $column, int $length = null)
    {
        $length = $length ?: Schema::$defaultStringLength;

//        return $this->addColumn('string', $column, compact('length'));

        return $this->addColumn('VARCHAR', $column, [
            'constraint' => $length
        ]);
    }

    /**
     * Create a new tiny text column on the table.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function tinyText(string $column)
    {
        return $this->addColumn('TINYTEXT', $column);
    }

    /**
     * Create a new text column on the table.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function text(string $column)
    {
        return $this->addColumn('TEXT', $column);
    }

    /**
     * Create a new medium text column on the table.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function mediumText(string $column)
    {
        return $this->addColumn('MEDIUMTEXT', $column);
    }

    /**
     * Create a new long text column on the table.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function longText(string $column)
    {
        return $this->addColumn('LONGTEXT', $column);
    }

    /**
     * Create a new integer (4-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return ColumnDefinition
     */
    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false)
    {
        return $this->addColumn('INT', $column, [
            'auto_increment' => $autoIncrement,
            'unsigned' => $unsigned
        ]);
    }

    /**
     * Create a new tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return ColumnDefinition
     */
    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false)
    {
        return $this->addColumn('TINYINT', $column, [
            'auto_increment' => $autoIncrement,
            'unsigned' => $unsigned
        ]);
    }

    /**
     * Create a new small integer (2-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return ColumnDefinition
     */
    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false)
    {
        return $this->addColumn('SMALLINT', $column, [
            'auto_increment' => $autoIncrement,
            'unsigned' => $unsigned
        ]);
    }

    /**
     * Create a new medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return ColumnDefinition
     */
    public function mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false)
    {
        return $this->addColumn('MEDIUMINT', $column, [
            'auto_increment' => $autoIncrement,
            'unsigned' => $unsigned
        ]);
    }

    /**
     * Create a new big integer (8-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return ColumnDefinition
     */
    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false)
    {
        return $this->addColumn('BIGINT', $column, [
            'auto_increment' => $autoIncrement,
            'unsigned' => $unsigned
        ]);
    }

    /**
     * Create a new unsigned integer (4-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return ColumnDefinition
     */
    public function unsignedInteger(string $column, bool $autoIncrement = false)
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return ColumnDefinition
     */
    public function unsignedTinyInteger(string $column, bool $autoIncrement = false)
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned small integer (2-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return ColumnDefinition
     */
    public function unsignedSmallInteger(string $column, bool $autoIncrement = false)
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return ColumnDefinition
     */
    public function unsignedMediumInteger(string $column, bool $autoIncrement = false)
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return ColumnDefinition
     */
    public function unsignedBigInteger(string $column, bool $autoIncrement = false)
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }


    // TODO: Make fk keys work
    public function foreignId($column)
    {
        //
    }

    public function foreignIdFor($model, $column = null)
    {
        //
    }

    /**
     * Create a new float column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @param bool $unsigned
     * @return ColumnDefinition
     */
    public function float(string $column, int $total = 8, int $places = 2, bool $unsigned = false)
    {
        return $this->addColumn('FLOAT', $column, [
            'constraint' => $total . ',' . $places,
            'unsigned' => $unsigned
        ]);
    }

    /**
     * Create a new double column on the table.
     *
     * @param string $column
     * @param int|null $total
     * @param int|null $places
     * @param bool $unsigned
     * @return ColumnDefinition
     */
    public function double(string $column, int $total = null, int $places = null, bool $unsigned = false)
    {
        return $this->addColumn('DOUBLE', $column, [
            'constraint' => $total . ',' . $places,
            'unsigned' => $unsigned
        ]);
    }

    /**
     * Create a new decimal column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @param bool $unsigned
     * @return ColumnDefinition
     */
    public function decimal(string $column, int $total = 8, int $places = 2, bool $unsigned = false)
    {
        return $this->addColumn('DECIMAL', $column, [
            'constraint' => $total . ',' . $places,
            'unsigned' => $unsigned
        ]);
    }

    /**
     * Create a new unsigned float column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return ColumnDefinition
     */
    public function unsignedFloat(string $column, int $total = 8, int $places = 2)
    {
        return $this->float($column, $total, $places, true);
    }

    /**
     * Create a new unsigned double column on the table.
     *
     * @param string $column
     * @param int|null $total
     * @param int|null $places
     * @return ColumnDefinition
     */
    public function unsignedDouble(string $column, int $total = null, int $places = null)
    {
        return $this->double($column, $total, $places, true);
    }

    /**
     * Create a new unsigned decimal column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return ColumnDefinition
     */
    public function unsignedDecimal(string $column, int $total = 8, int $places = 2)
    {
        return $this->decimal($column, $total, $places, true);
    }

    /**
     * Create a new boolean column on the table.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function boolean(string $column)
    {
        return $this->addColumn('TINYINT', $column);
    }

    /**
     * Create a new enum column on the table.
     */
//    public function enum($column, array $allowed)
//    {
//        return $this->addColumn('enum', $column, compact('allowed'));
//    }

    /**
     * Create a new set column on the table.
     */
//    public function set($column, array $allowed)
//    {
//        return $this->addColumn('set', $column, compact('allowed'));
//    }

    /**
     * Create a new json column on the table.
     */
//    public function json($column)
//    {
//        return $this->addColumn('json', $column);
//    }

    /**
     * Create a new jsonb column on the table.
     */
//    public function jsonb($column)
//    {
//        return $this->addColumn('jsonb', $column);
//    }

    /**
     * Create a new date column on the table.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function date(string $column)
    {
        return $this->addColumn('DATE', $column);
    }

    /**
     * Create a new date-time column on the table.
     */
    public function dateTime($column, $precision = 0)
    {
        return $this->addColumn('DATETIME', $column, [
            'precision' => $precision
        ]);
    }

    /**
     * Create a new date-time column (with time zone) on the table.
     */
//    public function dateTimeTz($column, $precision = 0)
//    {
//        return $this->addColumn('dateTimeTz', $column, compact('precision'));
//    }

    /**
     * Create a new time column on the table.
     */
//    public function time($column, $precision = 0)
//    {
//        return $this->addColumn('time', $column, compact('precision'));
//    }

    /**
     * Create a new time column (with time zone) on the table.
     */
//    public function timeTz($column, $precision = 0)
//    {
//        return $this->addColumn('timeTz', $column, compact('precision'));
//    }

    /**
     * Create a new timestamp column on the table.
     *
     * @param string $column
     * @param array $options
     * @return ColumnDefinition
     */
    public function timestamp(string $column, array $options = [])
    {
        return $this->addColumn('TIMESTAMP', $column, $options);
    }

    /**
     * Create a new timestamp (with time zone) column on the table.
     */
//    public function timestampTz($column, $precision = 0)
//    {
//        return $this->addColumn('timestampTz', $column, compact('precision'));
//    }

    /**
     * Add nullable creation and update timestamps to the table.
     *
     * @return void
     */
    public function timestamps()
    {
        $this->timestamp('created_at', [
            'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' => true
        ]);

        $this->timestamp('updated_at', [
            'TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NULL DEFAULT NULL' => true
        ]);

//        $this->timestamp('created_at', $precision)->nullable();
//        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Add nullable creation and update timestamps to the table.
     */
//    public function nullableTimestamps($precision = 0)
//    {
//        $this->timestamps($precision);
//    }

    /**
     * Add creation and update timestampTz columns to the table.
     */
//    public function timestampsTz($precision = 0)
//    {
//        $this->timestampTz('created_at', $precision)->nullable();
//
//        $this->timestampTz('updated_at', $precision)->nullable();
//    }

    /**
     * Add a "deleted at" timestamp for the table.
     */
//    public function softDeletes($column = 'deleted_at', $precision = 0)
//    {
//        return $this->timestamp($column, $precision)->nullable();
//    }

    /**
     * Add a "deleted at" timestampTz for the table.
     */
//    public function softDeletesTz($column = 'deleted_at', $precision = 0)
//    {
//        return $this->timestampTz($column, $precision)->nullable();
//    }

    /**
     * @param $type
     * @param $name
     * @param array $parameters
     * @return ColumnDefinition
     */
    public function addColumn($type, $name, array $parameters = [])
    {
//        return $this->columns[$name] = array_merge(compact('type'), $parameters);

        return $this->addColumnDefinition($name, new ColumnDefinition(
            array_merge(compact('type'), $parameters)
        ));
    }

    /**
     * @param $name
     * @param $definition
     * @return ColumnDefinition
     */
    protected function addColumnDefinition($name, $definition)
    {
        $this->columns[$name] = $definition->attributes;


//        if ($this->after) {
//            $definition->after($this->after);
//
//            $this->after = $definition->name;
//        }

        return $this->columns[$name];
    }

    /**
     * Specify the primary key for the table.
     *
     * @param string $column
     * @return void
     */
    public function primary(string $column)
    {
        $this->index($column, TRUE);
    }

    /**
     * Specify an index for the table.
     *
     * @param string $column
     * @param bool $primary
     * @return void
     */
    public function index(string $column, bool $primary = false)
    {
        $this->keys[$column] = $primary;
    }

    /**
     * Get the columns on the blueprint.
     *
     * @return ColumnDefinition[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the keys on the blueprint.
     *
     * @return array []
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    /**
     * Get the table the blueprint describes.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
