<?php

namespace Elegant\Database;

use InvalidArgumentException;

class Seeder
{
    public function __construct()
    {
        log_message('info', 'Elegant Seeder Class Initialized');
    }

    /**
     * Run the given seeder class.
     *
     * @param  string  $class
     * @return void
     */
    public function call($class)
    {
        $seeder = $this->resolve($class);

        $name = (new \ReflectionClass(get_class($seeder)))->getShortName();

        $startTime = microtime(true);

//        if ($silent === false) {
//            echo "Seeding: {$name} \n";
//        }

        $seeder->__invoke();

        $runTime = number_format((microtime(true) - $startTime), 2);

        echo "Seeded: {$name} ({$runTime} seconds) \n";
    }

    /**
     * Resolve an instance of the given seeder class.
     *
     * @param  string  $class
     * @return \Elegant\Database\Seeder
     */
    protected function resolve($class)
    {
        return new $class;
    }

    /**
     * Run the database seeds.
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function __invoke()
    {
        if (! method_exists($this, 'run')) {
            throw new InvalidArgumentException('Method [run] missing from '.get_class($this));
        }

        return $this->run();
    }

    /**
     * Enables the use of CI super-global without having to define an extra variable.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return get_instance()->$key;
    }
}
