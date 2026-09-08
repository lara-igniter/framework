<?php

/**
 * Initialize the database using root config/ (Laravel-style) and stock CI drivers.
 *
 * @param string|string[] $params
 * @param bool|null $query_builder_override
 * @return object
 */
function &DB($params = '', $query_builder_override = null)
{
    if (is_string($params) && strpos($params, '://') === false) {
        if (! file_exists($file_path = base_path('config/'.ENVIRONMENT.'/database.php'))
            && ! file_exists($file_path = base_path('config/database.php'))) {
            show_error('The configuration file database.php does not exist.');
        }

        include $file_path;

        if (class_exists('CI_Controller', false)) {
            foreach (get_instance()->load->get_package_paths() as $path) {
                if ($path !== APPPATH) {
                    if (file_exists($file_path = $path.'config/'.ENVIRONMENT.'/database.php')) {
                        include $file_path;
                    } elseif (file_exists($file_path = $path.'config/database.php')) {
                        include $file_path;
                    }
                }
            }
        }

        if (! isset($db) || count($db) === 0) {
            show_error('No database connection settings were found in the database config file.');
        }

        if ($params !== '') {
            $active_group = $params;
        }

        if (! isset($active_group)) {
            show_error('You have not specified a database connection group via $active_group in your config/database.php file.');
        } elseif (! isset($db[$active_group])) {
            show_error('You have specified an invalid database connection group ('.$active_group.') in your config/database.php file.');
        }

        $params = $db[$active_group];
    } elseif (is_string($params)) {
        if (($dsn = @parse_url($params)) === false) {
            show_error('Invalid DB Connection String');
        }

        $params = [
            'dbdriver' => $dsn['scheme'],
            'hostname' => isset($dsn['host']) ? rawurldecode($dsn['host']) : '',
            'port' => isset($dsn['port']) ? rawurldecode($dsn['port']) : '',
            'username' => isset($dsn['user']) ? rawurldecode($dsn['user']) : '',
            'password' => isset($dsn['pass']) ? rawurldecode($dsn['pass']) : '',
            'database' => isset($dsn['path']) ? rawurldecode(substr($dsn['path'], 1)) : '',
        ];

        if (isset($dsn['query'])) {
            parse_str($dsn['query'], $extra);

            foreach ($extra as $key => $val) {
                if (is_string($val) && in_array(strtoupper($val), ['TRUE', 'FALSE', 'NULL'])) {
                    $val = var_export($val, true);
                }

                $params[$key] = $val;
            }
        }
    }

    if (empty($params['dbdriver'])) {
        show_error('You have not selected a database type to connect to.');
    }

    if ($query_builder_override !== null) {
        $query_builder = $query_builder_override;
    } elseif (! isset($query_builder) && isset($active_record)) {
        $query_builder = $active_record;
    }

    require_once BASEPATH.'database/DB_driver.php';

    if (! isset($query_builder) || $query_builder === true) {
        require_once BASEPATH.'database/DB_query_builder.php';
        if (! class_exists('CI_DB', false)) {
            class CI_DB extends CI_DB_query_builder
            {
                public function query($sql, $binds = false, $return_object = null)
                {
                    if ($this->dbdriver === 'sqlite3') {
                        $sql = $this->rewriteMysqlAlterForSqlite($sql);
                        if ($sql === '') {
                            return true;
                        }
                    }

                    return parent::query($sql, $binds, $return_object);
                }

                protected function rewriteMysqlAlterForSqlite($sql)
                {
                    $trimmed = trim($sql);

                    if (preg_match('/^ALTER TABLE [`"]?(\w+)[`"]? ADD UNIQUE KEY [`"]?(\w+)[`"]? \((.+)\)$/i', $trimmed, $m)) {
                        $table = $this->prefixedSqliteTable($m[1]);

                        return 'CREATE UNIQUE INDEX IF NOT EXISTS '.$this->escape_identifiers($table.'_'.$m[2])
                            .' ON '.$this->escape_identifiers($table).' ('.$m[3].')';
                    }

                    if (preg_match('/^ALTER TABLE [`"]?(\w+)[`"]? ADD INDEX [`"]?(\w+)[`"]? \((.+)\)$/i', $trimmed, $m)) {
                        $table = $this->prefixedSqliteTable($m[1]);

                        return 'CREATE INDEX IF NOT EXISTS '.$this->escape_identifiers($table.'_'.$m[2])
                            .' ON '.$this->escape_identifiers($table).' ('.$m[3].')';
                    }

                    if (preg_match('/^ALTER TABLE [`"]?(\w+)[`"]? ADD INDEX\([`"]?(\w+)[`"]?\)$/i', $trimmed, $m)) {
                        $table = $this->prefixedSqliteTable($m[1]);

                        return 'CREATE INDEX IF NOT EXISTS '.$this->escape_identifiers($table.'_'.$m[2])
                            .' ON '.$this->escape_identifiers($table).' ('.$this->escape_identifiers($m[2]).')';
                    }

                    if (preg_match('/^ALTER TABLE [`"]?(\w+)[`"]? DROP INDEX [`"]?(\w+)[`"]?$/i', $trimmed, $m)) {
                        return 'DROP INDEX IF EXISTS '.$this->escape_identifiers($m[2]);
                    }

                    if (preg_match('/^ALTER TABLE .+ CHANGE /i', $trimmed)) {
                        return '';
                    }

                    return $sql;
                }

                protected function prefixedSqliteTable($table)
                {
                    if ($this->dbprefix !== '' && strpos($table, $this->dbprefix) !== 0) {
                        return $this->dbprefix.$table;
                    }

                    return $table;
                }
            }
        }
    } elseif (! class_exists('CI_DB', false)) {
        class CI_DB extends CI_DB_driver
        {
        }
    }

    $driver_file = BASEPATH.'database/drivers/'.$params['dbdriver'].'/'.$params['dbdriver'].'_driver.php';

    file_exists($driver_file) or show_error('Invalid DB driver');
    require_once $driver_file;

    $driver = 'CI_DB_'.$params['dbdriver'].'_driver';
    $DB = new $driver($params);

    if (! empty($DB->subdriver)) {
        $driver_file = BASEPATH.'database/drivers/'.$DB->dbdriver.'/subdrivers/'.$DB->dbdriver.'_'.$DB->subdriver.'_driver.php';

        if (file_exists($driver_file)) {
            require_once $driver_file;
            $driver = 'CI_DB_'.$DB->dbdriver.'_'.$DB->subdriver.'_driver';
            $DB = new $driver($params);
        }
    }

    $DB->initialize();

    return $DB;
}
